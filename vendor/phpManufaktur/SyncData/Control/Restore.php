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

use phpManufaktur\SyncData\Control\Zip\unZip;
use phpManufaktur\SyncData\Data\General;

class Restore
{

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Restore Tables from the given source path.
     * If $create_backup is true, will create a fresh backup before restoring,
     * this enable a rollback if the restore fails.
     *
     * @param string $source_path
     * @param boolean $create_backup
     * @throws \Exception
     */
    protected function restoreTables($source_path, $create_backup=true)
    {
        if ($create_backup) {
            // first thing: make a backup of the current database!
            try {
                $backup = new Backup($this->app);
                $backup->backupDatabase(null);
            } catch (\Exception $e) {
                // backup failed
                throw $e;
            }
        }

        $tables = array();
        $directory_handle = dir($source_path);
        while (false !== ($file = $directory_handle->read())) {
            // get all files into an array
            if (($file == '.') || ($file == '..')) continue;
            $path = $this->app['utils']->sanitizePath("$source_path/$file");
            if (is_dir($path)) continue;
            $name = substr($file, 0, strrpos($file, '.'));
            if (!in_array($name, $tables)) {
                $tables[] = $name;
            }
        }
        // sort the array ascending
        sort($tables);

        $general = new General($this->app);

        // got the tables to ignore
        $ignore_tables = $this->app['config']['restore']['tables']['ignore'];

        try {
            // restore the tables
            foreach ($tables as $table) {
                //if ($table != 'kit2_propangas24_zip_list') continue;

                if (in_array($table, $ignore_tables)) {
                    $this->app['monolog']->addInfo("Skipped table $table because it is member of the ignore list");
                    continue;
                }

                if (file_exists("$source_path/$table.sql") &&
                    file_exists("$source_path/$table.json")) {

                    // drop the existing table
                    $general->dropTable(CMS_TABLE_PREFIX.$table);

                    // get the SQL to create the table
                    if (false === ($SQL = @file_get_contents("$source_path/$table.sql"))) {
                        throw new \Exception("Can't read the SQL for table $table");
                    }
                    if ($this->app['config']['restore']['settings']['replace_table_prefix']) {
                        // replace the placeholder with the real table prefix
                        $SQL = str_replace('{{ SyncData:TABLE_PREFIX }}', CMS_TABLE_PREFIX, $SQL);
                    }

                    // create the table
                    $general->query($SQL);

                    try {
                        // disable the table keys
                        $this->app['db']->query("ALTER TABLE ".CMS_TABLE_PREFIX."$table DISABLE KEYS");
                        $this->app['monolog']->addInfo("DISABLE KEYS for $table");
                    } catch (\Doctrine\DBAL\DBALException $e) {
                        throw $e->getMessage();
                    }
                    // get the table rows from JSON
                    if (false === ($rows = json_decode(@file_get_contents("$source_path/$table.json"), true))) {
                        throw new \Exception("Can't read the data rows for table $table");
                    }
                    // insert the table rows
                    $replace_cms_url = $this->app['config']['restore']['settings']['replace_cms_url'];
                    $general->insertRows(CMS_TABLE_PREFIX.$table, $rows, $replace_cms_url);
                    $this->app['monolog']->addInfo(sprintf("Inserted %d rows into table %s", count($rows), $table));

                    try{
                        // enable the table keys
                        $this->app['db']->query("ALTER TABLE ".CMS_TABLE_PREFIX."$table ENABLE KEYS");
                        $this->app['monolog']->addInfo("ENABLE KEYS for $table");
                    } catch (\Doctrine\DBAL\DBALException $e) {
                        throw $e->getMessage();
                    }


                    if (file_exists("$source_path/$table.md5")) {
                        if (false === ($md5 = @file_get_contents("$source_path/$table.md5"))) {
                            throw new \Exception("Can't read the MD5 checksum for table $table");
                        }
                        $new_md5 = $general->getTableContentChecksum(CMS_TABLE_PREFIX.$table);
                        if ($md5 != $new_md5) {
                            throw new \Exception("MD5 checksum comparison ($md5 <=> $new_md5) for table $table failed!");
                        }
                        $this->app['monolog']->addInfo("MD5 checksum comparison for table $table was successfull");
                    }
                }
            }
        } catch (\Exception $e) {
            if ($create_backup) {
                // we have created a backup before and can restore!
                $this->app['monolog']->addError($e->getMessage());
                $this->app['monolog']->addCritical("Abort RESTORE, try to restore the previous created BACKUP!");
                //$this->restoreTables(TEMP_PATH.'/backup/tables', false);
                $this->app['monolog']->addInfo("The RESTORE from previous created BACKUP was SUCCESFULL");
                throw new \Exception("The RESTORE process failed with errors. The tables where successfull recovered");
            } else {
                throw $e;
            }
        }
    }

    protected function restoreFiles($source_path, $create_backup=true)
    {
        $backup = new Backup($this->app);

        if ($create_backup) {
            try {
                $backup->backupFiles();
            } catch (\Exception $e) {
                throw $e;
            }
        }

        try {
            $ignore_directories = array();
            foreach ($this->app['config']['restore']['directories']['ignore']['directory'] as $directory) {
                // take the real path for the directory
                $ignore_directories[] = CMS_PATH.DIRECTORY_SEPARATOR.$directory;
            }
            $ignore_subdirectories = $this->app['config']['restore']['directories']['ignore']['subdirectory'];
            $ignore_files = $this->app['config']['restore']['files']['ignore'];

            // in general the CMS config.php should not restored!
            if ($this->app['config']['restore']['settings']['ignore_cms_config'] &&
                file_exists($source_path.'/config.php') && !@unlink($source_path.'/config.php')) {
                throw new \Exception("Can't delete the config.php from the restore path!");
            }

            $this->app['utils']->copyRecursive(
                $source_path,
                CMS_PATH,
                $ignore_directories,
                $ignore_subdirectories,
                $ignore_files,
                true
                );
        } catch (\Exception $e) {
            if ($create_backup) {
                // Restore fails but we have backup the files
                $this->app['monolog']->addError($e->getMessage());
                $this->app['monolog']->addCritical("Abort RESTORE of files, try to restore the previous saved tables and files");
                // restore the tables
                $this->restoreTables(TEMP_PATH.'/backup/tables', false);
                $this->app['monolog']->addInfo("The RESTORE of the previous saved tables was SUCCESSFULL");
                $this->restoreFiles(TEMP_PATH.'/backup/cms', false);
                $this->app['monolog']->addInfo("The RESTORE of the previous saved files was SUCCESSFULL");
                throw new \Exception("The RESTORE process failed with errors. The files and tables where successfull recovered");
            }
            else {
                throw $e;
            }
        }

    }

    protected function processArchive($archive)
    {
        if (file_exists(TEMP_PATH.'/restore') && !$this->app['utils']->rrmdir(TEMP_PATH.'/restore')) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/restore'));
        }
        if (!file_exists(TEMP_PATH.'/restore') && (false === @mkdir(TEMP_PATH.'/restore'))) {
            throw new \Exception("Can't create the directory ".TEMP_PATH."/restore");
        }

        if (file_exists(TEMP_PATH.'/backup') && !$this->app['utils']->rrmdir(TEMP_PATH.'/backup')) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/restore'));
        }

        $this->app['monolog']->addInfo("Start unzipping $archive");
        $unZip = new unZip($this->app);
        $unZip->setUnZipPath(TEMP_PATH.'/restore');
        $unZip->extract($archive);
        $this->app['monolog']->addInfo("Unzipped $archive");

        // check if the syncdata.json exists
        if (!file_exists(TEMP_PATH.'/restore/backup/syncdata.json')) {
            throw new \Exception("Missing the syncdata.json file within the archive!");
        }

        // restore the tables
        $this->restoreTables(TEMP_PATH.'/restore/backup/tables');

        // restore the files
        $this->restoreFiles(TEMP_PATH.'/restore/backup/cms');
        return true;
    }

    public function exec()
    {
        // start restore
        $this->app['monolog']->addInfo('Start RESTORE');

        // check the /inbox
        $files = array();
        $directory_handle = dir(SYNC_DATA_PATH.'/inbox');
        while (false !== ($file = $directory_handle->read())) {
            // get all files into an array
            if (($file == '.') || ($file == '..')) continue;
            $path = $this->app['utils']->sanitizePath(SYNC_DATA_PATH."/inbox/$file");
            if (is_dir($path)) {
                // RESTORE does not scan subdirectories!
                $this->app['monolog']->addInfo("Sipped subdirectory $path, RESTORE search only for files in the /inbox!");
                continue;
            }
            $files[] = $path;
        }
        // sort the array ascending
        sort($files);

        foreach ($files as $file) {
            $fileinfo = pathinfo($file);
            if (strtolower($fileinfo['extension']) !== 'zip') {
                $this->app['monolog']->addInfo(sprintf('RESTORE does only accept ZIP files, %s rejected', basename($file)));
                continue;
            }
            // process the restore file
            $this->processArchive($file);
            // and leave the loop
            break;
        }


        $this->app['monolog']->addInfo('Finished RESTORE');
        return 'Finished RESTORE';
    }

}
