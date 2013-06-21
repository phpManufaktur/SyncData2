<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Control;

use phpManufaktur\SyncData\Data\General;
use phpManufaktur\SyncData\Control\Zip\Zip;
use phpManufaktur\SyncData\Data\BackupMaster;
use phpManufaktur\SyncData\Control\JSON\JSONFormat;
use phpManufaktur\SyncData\Data\BackupTables;
use phpManufaktur\SyncData\Data\BackupFiles;
use phpManufaktur\SyncData\Data\SynchronizeArchives;

/**
 * Class to create a Backup archive for itself or for clients
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class Backup
{
    protected $app = null;
    protected $tables = null;
    protected static $backup_id = null;
    protected static $backup_date = null;
    protected $General = null;
    protected $BackupMaster = null;
    protected $BackupTables = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->General = new General($this->app);
        $this->BackupMaster = new BackupMaster($this->app);
        $this->BackupTables = new BackupTables($this->app);
    }

    /**
     * Create a new Backup ID based on the actual date and time
     *
     * @return string
     */
    public function createBackupID()
    {
        self::$backup_id = date('Ymd_Hi');
        self::$backup_date = date('Y-m-d H:i:s');
        return self::$backup_id;
    }

    /**
     * Try to get the primary index field of the given table
     *
     * @param string $table
     * @return Ambigous <string, boolean> return the name of the field or false
     */
    protected function getPrimaryIndexField($table)
    {
        $indexes = $this->General->listTableIndexes($table);
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
    public function backupDatabase($backup_id=null)
    {
        if (!file_exists(TEMP_PATH.'/backup/tables')) {
            if (!@mkdir(TEMP_PATH.'/backup/tables', 0755, true)) {
                throw new \Exception("Can't create the directory ".TEMP_PATH.'/backup/tables');
            }
        }

        $this->tables = $this->General->getTables();
        $this->app['monolog']->addInfo('Got all table names of the database',
            array('method' => __METHOD__, 'line' => __LINE__));

        // get the tables to ignore
        $ignore_tables = $this->app['config']['backup']['tables']['ignore'];
        $this->app['utils']->setCountTables();
        foreach ($this->tables as $table) {
            // $table contains also the table prefix!
            $prefix = substr($table, 0, strlen(CMS_TABLE_PREFIX));
            if ($prefix !== CMS_TABLE_PREFIX) {
                // ignore all tables with another table prefix!
                continue;
            }
            $table = substr($table, strlen(CMS_TABLE_PREFIX));
            if (!is_null($ignore_tables) && in_array($table, $ignore_tables)) continue;
            $this->app['monolog']->addInfo("Start backup table $table",
                array('method' => __METHOD__, 'line' => __LINE__));
            $this->backupTable($table, $backup_id);
            $this->app['utils']->increaseCountTables();
        }
        $this->app['monolog']->addInfo('Saved all tables in the temporary directory',
            array('method' => __METHOD__, 'line' => __LINE__));
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

            $indexField = (false === $idx = $this->getPrimaryIndexField(CMS_TABLE_PREFIX.$table)) ? 'NO_INDEX_FIELD' : $idx;
            $rows = $this->General->getTableContent(CMS_TABLE_PREFIX.$table);
            $content = array();
            // loop through the records
            foreach ($rows as $row) {
                $new_row = array();
                foreach ($row as $key => $value) {
                    if ($this->app['config']['backup']['settings']['replace_cms_url']) {
                        // replace all real URLs of the CMS  with a placeholder
                        $count = 0;
                        $new_row[$key] = is_string($value) ? str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', $value, $count) : $value;
                        if ($count > 0) {
                            $this->app['monolog']->addInfo(sprintf("Replaced the CMS URL %d time(s) in row %s of table %s", $count, $key, $table),
                                array('method' => __METHOD__, 'line' => __LINE__));
                        }
                    }
                    else {
                        $new_row[$key] = $value;
                    }
                }
                $content[] = $new_row;

                if (!is_null($backup_id) && ($indexField != 'NO_INDEX_FIELD')) {
                    $checksum = md5(str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', implode(',', $row)));
                    $data = array(
                        'backup_id' => $backup_id,
                        'table_name' => $table,
                        'index_field' => $indexField,
                        'index_id' => $row[$indexField],
                        'origin_checksum' => $checksum,
                        'last_checksum' => $checksum,
                        'action' => 'BACKUP'
                    );
                    $this->BackupTables->insert($data);
                    $this->app['monolog']->addInfo(sprintf("Added field %s of table %s as index field to the backup tables", $row[$indexField], $table),
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
            }

            $replace_table_prefix = $this->app['config']['backup']['settings']['replace_table_prefix'];
            $add_if_not_exists = $this->app['config']['backup']['settings']['add_if_not_exists'];

            $sql = $this->General->getCreateTableSQL(CMS_TABLE_PREFIX.$table, $replace_table_prefix, $add_if_not_exists);
            $md5 = $this->General->getTableContentChecksum(CMS_TABLE_PREFIX.$table);

            // save the SQL code for table creation
            if (!@file_put_contents(TEMP_PATH."/backup/tables/$table.sql", $sql)) {
                throw new \Exception("Can't create the SQL file for $table");
            }
            $this->app['monolog']->addInfo("Saved the SQL code for $table temporary",
                array('method' => __METHOD__, 'line' => __LINE__));
            // save the MD5 checksum
            if (!@file_put_contents(TEMP_PATH."/backup/tables/$table.md5", $md5)) {
                throw new \Exception("Can't create the MD5 file for $table");
            }
            $this->app['monolog']->addInfo("Saved the MD5 checksum for $table temporary",
                array('method' => __METHOD__, 'line' => __LINE__));

            if (!is_null($backup_id)) {
                // add a record to backup master
                $data = array(
                    'backup_id' => $backup_id,
                    'date' => self::$backup_date, //date('Y-m-d H:i:s'),
                    'sql_create_table' => $sql,
                    'index_field' => $indexField,
                    'origin_checksum' => $md5,
                    'last_checksum' => $md5,
                    'table_name' => $table
                );
                $id = -1;
                $this->BackupMaster->insert($data, $id);
                $this->app['monolog']->addInfo("Add table $table to backup master",
                    array('method' => __METHOD__, 'line' => __LINE__));
            }

            if (!@file_put_contents(TEMP_PATH."/backup/tables/$table.json", json_encode($content))) {
                throw new \Exception("Can't create the backup file for $table");
            }
            $this->app['monolog']->addInfo("Create backup of table $table and saved it temporary",
                array('method' => __METHOD__, 'line' => __LINE__));
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Save the files of the CMS in the temporary directory
     *
     */
    public function backupFiles($backup_id=null)
    {
        $this->app['monolog']->addInfo('Start processing files',
            array('method' => __METHOD__, 'line' => __LINE__));
        // create the temporary directory
        if (!file_exists(TEMP_PATH.'/backup/cms') && (false === @mkdir(TEMP_PATH.'/backup/cms', 0755, true))) {
            throw new \Exception("Can't create the directory ".TEMP_PATH."/backup/cms");
        }

        $ignore_directories = array();
        foreach ($this->app['config']['backup']['directories']['ignore']['directory'] as $directory) {
            // take the real path for the directory
            $ignore_directories[] = $this->app['utils']->sanitizePath(CMS_PATH.DIRECTORY_SEPARATOR.$directory);
        }

        $ignore_subdirectories = $this->app['config']['backup']['directories']['ignore']['subdirectory'];
        $ignore_files = $this->app['config']['backup']['files']['ignore'];

        $this->app['utils']->setCountFiles();
        $this->app['utils']->setCountDirectories();
        $copied_files = array();
        $this->app['utils']->copyRecursive(CMS_PATH, TEMP_PATH.'/backup/cms', $ignore_directories, $ignore_subdirectories, $ignore_files, false, $copied_files);

        // process the copied files and save them to the backup table
        if (!is_null($backup_id)) {
            $BackupFiles = new BackupFiles($this->app);
            foreach ($copied_files as $file) {
                if (!file_exists(CMS_PATH.$file)) {
                    throw new \Exception("Can't access the file $file!");
                }
                $checksum = md5_file(CMS_PATH.$file);
                $data = array(
                    'backup_id' => self::$backup_id,
                    'date' => self::$backup_date, //date('Y-m-d H:i:s'),
                    'relative_path' => $file,
                    'file_name' => basename($file),
                    'file_checksum_origin' => $checksum,
                    'file_checksum_last' => $checksum,
                    'file_size' => filesize(CMS_PATH.$file),
                    'file_date' => date('Y-m-d H:i:s', filemtime(CMS_PATH.$file)),
                    'action' => 'BACKUP'
                );
                $BackupFiles->insert($data);
            }
        }
        $this->app['monolog']->addInfo(sprintf('Processed %d files in %d directories',
            $this->app['utils']->getCountFiles(),
            $this->app['utils']->getCountDirectories()
            ),
            array('method' => __METHOD__, 'line' => __LINE__));
    }

    /**
     * Action handler for the class Backup
     *
     * @throws \Exception
     * @return string
     */
    public function exec()
    {
        try{
            $this->app['monolog']->addInfo('Backup started', array('method' => __METHOD__, 'line' => __LINE__));

            // delete an existing backup directory an all content
            if (file_exists(TEMP_PATH.'/backup') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/backup'))) {
                throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/backup'));
            }
            // create the backup directory
            if (!file_exists(TEMP_PATH.'/backup') && (false === @mkdir(TEMP_PATH.'/backup', 0755, true))) {
                throw new \Exception("Can't create the directory ".TEMP_PATH."/backup");
            }
            $this->app['monolog']->addInfo('Prepared temporary directory for the backup',
                array('method' => __METHOD__, 'line' => __LINE__));

            // backup the database with all tables
            $backup_id = $this->createBackupID();
            $this->backupDatabase(self::$backup_id);

            // backup filename
            $backup_file = SYNCDATA_PATH."/data/backup/syncdata_backup_$backup_id.zip";

            // backup all files
            $this->backupFiles(self::$backup_id);

            $data = array();
            $data['backup'] = $this->app['config']['backup'];
            $data['backup']['id'] = self::$backup_id;
            $data['backup']['date'] = self::$backup_date;

            $SynchronizeArchives = new SynchronizeArchives($this->app);
            $data['archive']['last_id'] = $SynchronizeArchives->selectLastID();

            $jsonFormat = new JSONFormat();
            $json = $jsonFormat->format($data);
            if (!file_put_contents(TEMP_PATH.'/backup/syncdata.json', $json)) {
                throw new \Exception("Can't write the syncdata.json file for the backup!");
            }

            if (!file_exists(SYNCDATA_PATH.'/data/backup')) {
                if (!@mkdir(SYNCDATA_PATH.'/data/backup', 0755, true)) {
                    throw new \Exception("Can't create the directory ".SYNCDATA_PATH.'/data/backup');
                }
            }
            if (!file_exists(SYNCDATA_PATH.'/data/backup/.htaccess') || !file_exists(SYNCDATA_PATH.'/data/backup/.htpasswd')) {
                $this->app['utils']->createDirectoryProtection(SYNCDATA_PATH.'/data/backup');
            }

            if (file_exists($backup_file)) {
                @unlink($backup_file);
            }

            $zip = new Zip($this->app);
            $zip->zipDir(TEMP_PATH.'/backup', $backup_file);

            $md5 = md5_file($backup_file);
            if (!file_put_contents(SYNCDATA_PATH."/data/backup/syncdata_backup_$backup_id.md5", $md5)) {
                throw new \Exception("Can't write the MD5 checksum file for the backup!");
            }

            // delete an existing backup directory an all content
            if (file_exists(TEMP_PATH.'/backup') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/backup'))) {
                throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/backup'));
            }

            $this->app['monolog']->addInfo('Backup finished', array('method' => __METHOD__, 'line' => __LINE__));
            return "Processed ".$this->app['utils']->getCountTables()." tables and create a backup file.";
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

}