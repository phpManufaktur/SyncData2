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

use phpManufaktur\SyncData\Server\Control\Zip\unZip;
use phpManufaktur\SyncData\Server\Data\General;

class Restore
{

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function restoreTables()
    {
        $tables = array();
        $directory_handle = dir(TEMP_PATH.'/restore/backup/tables');
        while (false !== ($file = $directory_handle->read())) {
            // get all files into an array
            if (($file == '.') || ($file == '..')) continue;
            $path = $this->app['utils']->sanitizePath(TEMP_PATH."/restore/backup/tables/$file");
            if (is_dir($path)) continue;
            $name = substr($file, 0, strrpos($file, '.'));
            if (!in_array($name, $tables)) {
                $tables[] = $name;
            }
        }
        // sort the array ascending
        sort($tables);

        $general = new General($this->app);

        // start the outer transaction
        $this->app['db']->beginTransaction();
        $this->app['monolog']->addInfo('Begin outer transaction');

        try {
            // restore the tables
            foreach ($tables as $table) {
                if ($table != 'kit2_propangas24_zip_list') continue;

                if (file_exists(TEMP_PATH."/restore/backup/tables/$table.sql") &&
                    file_exists(TEMP_PATH."/restore/backup/tables/$table.json")) {

                    // delete the existing table
                    $this->app['db']->beginTransaction();
                    try {
                        $this->app['db']->query("DROP TABLE IF EXISTS `".CMS_TABLE_PREFIX."$table`");
                        $this->app['monolog']->addInfo("Drop table $table");

                        // create the table
                        $this->app['db']->beginTransaction();
                        try {
                            // get the SQL to create the table
                            if (false === ($SQL = file_get_contents(TEMP_PATH."/restore/backup/tables/$table.sql"))) {
                                throw new \Exception("Can't read the SQL for table $table");
                            }
                            // replace the placeholder with the real table prefix
                            $SQL = str_replace('{{ SyncData:TABLE_PREFIX }}', CMS_TABLE_PREFIX, $SQL);
                            $this->app['db']->query($SQL);
                            $this->app['monolog']->addInfo("Create table $table");

                            // insert the rows
                            $this->app['db']->beginTransaction();
                            try {
                                $this->app['monolog']->addInfo("Start processing table $table");
                                // get the table rows from JSON
                                if (false === ($rows = json_decode(file_get_contents(TEMP_PATH."/restore/backup/tables/$table.json"), true))) {
                                    throw new \Exception("Can't read the data rows for table $table");
                                }
                                $i=1;
                                /*
                                foreach ($rows as $row) {
                                    $this->app['db']->beginTransaction();
                                    try {
                                        $this->app['db']->insert(CMS_TABLE_PREFIX.$table, $row);
                                        if ($i==10) throw new \Exception('test!');
                                        $this->app['db']->commit();
                                    } catch (\Exception $e) {
                                        $this->app['db']->rollback();
                                        $this->app['monolog']->addInfo("Rollback insert row $i");
                                        throw $e;
                                    }
                                    $i++;
                                }
                                */
                                $this->app['db']->beginTransaction();
                                $i=1;
                                try {
                                    foreach ($rows as $row) {
                                        $this->app['db']->insert(CMS_TABLE_PREFIX.$table, $row);
                                        if ($i==10) throw new \Exception('test!');
                                        $i++;
                                    }
                                    $this->app['db']->commit();
                                } catch (\Exception $e) {
                                    $this->app['db']->rollback();
                                    $this->app['monolog']->addInfo("Rollback insert row $i");
                                    throw $e;
                                }

                                $this->app['monolog']->addInfo(sprintf("Inserted %d rows into table %s", count($rows), $table));
                                if (file_exists(TEMP_PATH."/restore/backup/tables/$table.md5")) {
                                    if (false === ($md5 = file_get_contents(TEMP_PATH."/restore/backup/tables/$table.md5"))) {
                                        throw new \Exception("Can't read the MD5 checksum for table $table");
                                    }
                                    if ($md5 == $general->getTableContentChecksum(CMS_TABLE_PREFIX.$table)) {
                                        throw new \Exception("MD5 checksum comparison for table $table failed!");
                                    }
                                    $this->app['monolog']->addInfo("MD5 checksum comparison for table $table was successfull");
                                }
                                $this->app['db']->commit();
                            } catch (\Exception $e) {
                                // rollback the transaction
                                $this->app['db']->rollback();
                                $this->app['monolog']->addInfo("Rollback insert data rows to table $table!");
                                // throw the exception ahead
                                throw $e;
                            }

                            $this->app['db']->commit();
                        } catch (\Exception $e) {
                            // rollback the transaction
                            $this->app['db']->rollback();
                            $this->app['monolog']->addInfo("Rollback create table $table!");
                            // throw the exception ahead
                            throw $e;
                        }

                        $this->app['db']->commit();
                    } catch (\Exception $e) {
                        // rollback the transaction
                        $this->app['db']->rollback();
                        $this->app['monolog']->addInfo("Rollback drop table $table!");
                        // throw the exception ahead
                        throw $e;
                    }




                }
                echo "$table<br>";
            }
            // commit the outer transaction
            $this->app['db']->commit();
        } catch (\Exception $e) {
            // rollback the transaction
            $this->app['db']->rollback();
            $this->app['monolog']->addInfo('Rollback outer transaction!');
            // throw the exception ahead
            throw $e;
        }
    }

    protected function processArchive($archive)
    {
        if (file_exists(TEMP_PATH.'/restore') && !$this->app['utils']->rrmdir(TEMP_PATH.'/restore')) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/restore'), error_get_last());
        }
        if (!file_exists(TEMP_PATH.'/restore') && (false === @mkdir(TEMP_PATH.'/restore'))) {
            throw new \Exception("Can't create the directory ".TEMP_PATH."/restore", error_get_last());
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
        $this->restoreTables();

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