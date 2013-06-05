<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Server\Control;

use phpManufaktur\SyncData\Server\Data\General;
use phpManufaktur\SyncData\Server\Control\Zip\Zip;
use phpManufaktur\SyncData\Server\Data\BackupMaster;
use phpManufaktur\SyncData\Server\Control\JSON\JSONFormat;

class Backup
{
    protected $app = null;
    protected $tables = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Try to get the primary index field of the given table
     *
     * @param string $table
     * @return Ambigous <string, boolean> return the name of the field or false
     */
    protected function getPrimaryIndexField($table)
    {
        $general = new General($this->app);
        $indexes = $general->listTableIndexes($table);
        $indexField = false;
        foreach ($indexes as $index) {
            if ($index->isPrimary()) {
                $columns = $index->getColumns();
                $indexField = $columns[0];
            }
        }
        return $indexField;
    }

    /**
     * Process the backup of the whole database
     *
     * @param $backup_id datetime string to identify the backup set
     */
    protected function backupDatabase($backup_id)
    {
        if (!file_exists(TEMP_PATH.'/backup/tables')) {
            @mkdir(TEMP_PATH.'/backup/tables');
        }

        $general = new General($this->app);
        $this->tables = $general->getTables();
        $this->app['monolog']->addInfo('Got all table names of the database');

        // get the tables to ignore
        $ignore_tables = $this->app['config']['syncdata']['server']['backup']['tables']['ignore'];

        $this->app['utils']->setCountTables();
        foreach ($this->tables as $table) {
            // $table contains also the table prefix!
            $table = substr($table, strlen(CMS_TABLE_PREFIX));
            if (!is_null($ignore_tables) && in_array($table, $ignore_tables)) continue;
            $this->backupTable($table, $backup_id);
            $this->app['utils']->increaseCountTables();
        }
        $this->app['monolog']->addInfo('Saved all tables in the temporary directory');
    }

    /**
     * Backup the given table. Create an entry in the backup master table with
     * the checksum, the primary field and the SQL code to create this table.
     * Save the records of the table as JSON file in the temporary directory.
     *
     * @param string $table
     * @param string $backup_id the ID of the backup set
     * @throws \Exception
     */
    protected function backupTable($table, $backup_id=null)
    {
        try {
            $general = new General($this->app);
            $BackupMaster = new BackupMaster($this->app);
            if (!$general->tableExists(CMS_TABLE_PREFIX.'syncdata_backup_master')) {
                $BackupMaster->createTable();
            }

            $rows = $general->getTableContent(CMS_TABLE_PREFIX.$table);
            $content = array();
            // loop through the records
            foreach ($rows as $row) {
                $new_row = array();
                foreach ($row as $key => $value) {
                    if ($this->app['config']['syncdata']['server']['backup']['settings']['replace_cms_url']) {
                        // replace all real URLs of the CMS  with a placeholder
                        $new_row[$key] = is_string($value) ? str_replace(CMS_URL, '{{ SyncData:CMS_URL }}', $value) : $value;
                    }
                    else {
                        $new_row[$key] = $value;
                    }
                }
                $content[] = $new_row;
            }

            $replace_table_prefix = $this->app['config']['syncdata']['server']['backup']['settings']['replace_table_prefix'];
            $add_if_not_exists = $this->app['config']['syncdata']['server']['backup']['settings']['add_if_not_exists'];

            if (!is_null($backup_id)) {
                // add a record to backup master
                $data = array(
                    'backup_id' => $backup_id,
                    'date' => date('Y-m-d H:i:s'),
                    'sql_create_table' => $general->getCreateTableSQL(CMS_TABLE_PREFIX.$table, $replace_table_prefix, $add_if_not_exists),
                    'index_field' => (false === $indexField = $this->getPrimaryIndexField(CMS_TABLE_PREFIX.$table)) ? 'NO_INDEX_FIELD' : $indexField,
                    'checksum' => $general->getTableContentChecksum(CMS_TABLE_PREFIX.$table),
                    'table_name' => $table
                );
                $id = -1;
                $BackupMaster->insert($data, $id);
                $this->app['monolog']->addInfo("Add table $table to backup master");
            }


            if (!file_put_contents(TEMP_PATH."/backup/tables/$table.json", json_encode($content))) {
                throw new \Exception(sprintf("Can't create the backup file for %s", $table));
            }
            $this->app['monolog']->addInfo("Create backup of table $table and saved it temporary");
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Save the files of the CMS in the temporary directory
     *
     */
    protected function backupFiles()
    {
        $this->app['monolog']->addInfo('Start processing files');
        // create the temporary directory
        mkdir(TEMP_PATH.'/backup/cms');

        $ignore_directories = array();
        foreach ($this->app['config']['syncdata']['server']['backup']['directories']['ignore']['directory'] as $directory) {
            // take the real path for the directory
            $ignore_directories[] = CMS_PATH.DIRECTORY_SEPARATOR.$directory;
        }
        $ignore_subdirectories = $this->app['config']['syncdata']['server']['backup']['directories']['ignore']['subdirectory'];
        $ignore_files = $this->app['config']['syncdata']['server']['backup']['files']['ignore'];

        $this->app['utils']->setCountFiles();
        $this->app['utils']->setCountDirectories();
        $this->app['utils']->copyRecursive(CMS_PATH, TEMP_PATH.'/backup/cms', $ignore_directories, $ignore_subdirectories, $ignore_files);

        $this->app['monolog']->addInfo(sprintf('Processed %d files in %d directories',
            $this->app['utils']->getCountFiles(),
            $this->app['utils']->getCountDirectories()
            ));
    }

    public function exec()
    {
        $this->app['monolog']->addInfo('Backup started');

        // delete an existing backup directory an all content
        if (file_exists(TEMP_PATH.'/backup') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/backup'))) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/backup'));
        }
        // create the backup directory
        mkdir(TEMP_PATH.'/backup');
        $this->app['monolog']->addInfo('Prepared temporary directory for the backup');

        // backup the database with all tables
        $backup_id = date('Ymd-Hi');
        $this->backupDatabase($backup_id);

        // backup all files
        $this->backupFiles();

        $data = array();
        $data['backup'] = $this->app['config']['syncdata']['server']['backup'];
        $data['backup']['id'] = $backup_id;

        $jsonFormat = new JSONFormat();
        $json = $jsonFormat->format($data);
        if (!file_put_contents(TEMP_PATH.'/backup/syncdata.json', $json)) {
            throw new \Exception("Can\'t write the syncdata.json file for the backup!");
        }

        if (!file_exists(SYNC_DATA_PATH.'/data/backup')) {
            if (!@mkdir(SYNC_DATA_PATH.'/data/backup', 0755, true)) {
                throw new \Exception("Can't create the directory ".SYNC_DATA_PATH.'/data/backup');
            }
        }
        if (!file_exists(SYNC_DATA_PATH.'/data/backup/.htaccess') || !file_exists(SYNC_DATA_PATH.'/data/backup/.htpasswd')) {
            $this->app['utils']->createDirectoryProtection(SYNC_DATA_PATH.'/data/backup');
        }
        if (file_exists(SYNC_DATA_PATH."/data/backup/$backup_id.zip")) {
            @unlink(SYNC_DATA_PATH."/data/backup/$backup_id.zip");
        }

        $zip = new Zip($this->app);
        $zip->zipDir(TEMP_PATH.'/backup', SYNC_DATA_PATH."/data/backup/$backup_id.zip");

        // delete an existing backup directory an all content
        if (file_exists(TEMP_PATH.'/backup') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/backup'))) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/backup'));
        }

        $this->app['monolog']->addInfo('Backup finished');
        return "Processed ".$this->app['utils']->getCountTables()." tables and create a backup file.";
    }

}