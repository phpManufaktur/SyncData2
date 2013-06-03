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

class Backup
{
    protected $app = null;
    protected $tables = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function backupTable($table)
    {
        try {
            $general = new General($this->app);
            $rows = $general->getTableContent($table);
            $content = array();
            // loop through the records
            foreach ($rows as $row) {
                $new_row = array();
                foreach ($row as $key => $value) {
                    $new_row[$key] = is_string($value) ? str_replace(CMS_URL, '{{ SyncData:CMS_URL }}', $value) : $value;
                }
                $content[] = $new_row;
            }
            if (!file_put_contents(TEMP_PATH."/backup/$table.json", json_encode($content))) {
                throw new \Exception(sprintf("Can't create the backup file for %s", $table));
            }
            $this->app['monolog']->addInfo("Create backup of table $table and saved it temporary");
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

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

        $zip = new Zip($this->app);
        $zip->zipDir(TEMP_PATH.'/backup/cms', TEMP_PATH.'/backup/cms.zip');
    }

    public function exec()
    {
        $this->app['monolog']->addInfo('Backup started');
        $general = new General($this->app);
        $this->tables = $general->getTables();
        $this->app['monolog']->addInfo('Got all table names of the database');

        if (file_exists(TEMP_PATH.'/backup') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/backup'))) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/backup'));
        }
        mkdir(TEMP_PATH.'/backup');
        $this->app['monolog']->addInfo('Prepared temporary directory for the backup');

        $i=0;
        foreach ($this->tables as $table) {
            $this->backupTable($table);
            $i++;
        }
        $this->app['monolog']->addInfo('Saved all tables in the temporary directory');

        $this->backupFiles();

        $this->app['monolog']->addInfo('Backup finished');
        return "Processed $i tables and create a backup file.";
    }

}