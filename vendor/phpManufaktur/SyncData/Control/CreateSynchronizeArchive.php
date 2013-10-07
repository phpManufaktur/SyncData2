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

use phpManufaktur\SyncData\Data\SynchronizeMaster;
use phpManufaktur\SyncData\Data\BackupMaster;
use phpManufaktur\SyncData\Data\SynchronizeTables;
use phpManufaktur\SyncData\Data\SynchronizeFiles;
use phpManufaktur\SyncData\Data\SynchronizeArchives;
use phpManufaktur\SyncData\Control\Zip\Zip;

class CreateSynchronizeArchive
{

    protected $app = null;
    protected static $backup_id = null;
    protected static $backup_date = null;
    protected static $archive_id = null;
    protected static $archive_date = null;
    protected static $archive_name = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    protected function processMaster(&$syncMaster)
    {
        $temp_master = array();
        foreach ($syncMaster as $master) {
            $master['sync_status'] = 'ARCHIVED';
            $master['sync_archive_name'] = self::$archive_name;
            $master['sync_archive_date'] = self::$archive_date;
            $temp_master[] = $master;
        }
        $syncMaster = $temp_master;
        if (!file_put_contents(TEMP_PATH.'/synchronize/master.json', json_encode($syncMaster))) {
            throw new \Exception("Can't write the master.json file for the synchronize archive!");
        }
    }

    protected function processTables(&$syncTables)
    {
        $temp_tables = array();
        foreach ($syncTables as $table) {
            $table['sync_status'] = 'ARCHIVED';
            $table['sync_archive_name'] = self::$archive_name;
            $table['sync_archive_date'] = self::$archive_date;
            $temp_tables[] = $table;
        }
        $syncTables = $temp_tables;
        if (!file_put_contents(TEMP_PATH.'/synchronize/tables.json', json_encode($syncTables))) {
            throw new \Exception("Can't write the tables.json file for the synchronize archive!");
        }
    }

    protected function processFiles(&$syncFiles)
    {
        $temp_files = array();
        foreach ($syncFiles as $file) {
            $file['sync_status'] = 'ARCHIVED';
            $file['sync_archive_name'] = self::$archive_name;
            $file['sync_archive_date'] = self::$archive_date;
            $temp_files[] = $file;
            if ($file['action'] !== 'DELETE') {
                // copy the file to the archive
                $source = CMS_PATH.$file['relative_path'];
                $target = TEMP_PATH.'/synchronize/CMS'.$file['relative_path'];
                if (!file_exists(dirname($target)) && !@mkdir(dirname($target), 0755, true)) {
                    throw new \Exception("Can't create the directory ".dirname($target));
                }
                if (!@copy($source, $target)) {
                    throw new \Exception("Can't copy the file $source to $target");
                }
            }
        }
        $syncFiles = $temp_files;
        if (!file_put_contents(TEMP_PATH.'/synchronize/files.json', json_encode($syncFiles))) {
            throw new \Exception("Can't write the files.json file for the synchronize archive!");
        }
    }

    public function exec()
    {
        // get the last backup ID
        $BackupMaster = new BackupMaster($this->app);
        if (false === (self::$backup_id = $BackupMaster->selectLastBackupID())) {
            $result = "Got no valid backup ID - please create a backup first!";
            $this->app['monolog']->addInfo($result, array('method' => __METHOD__, 'line' => __LINE__));
            return $result;
        }
        self::$backup_date = $BackupMaster->selectBackupDate(self::$backup_id);

        // get the next Archive ID
        $SynchronizeArchives = new SynchronizeArchives($this->app);
        self::$archive_id = $SynchronizeArchives->selectLastID();
        self::$archive_id++;

        self::$archive_date = date('Y-m-d H:i:s');

        self::$archive_name = sprintf('syncdata_synchronize_%05d', self::$archive_id);

        // check if there are data for synchronizing pending
        $SynchronizeMaster = new SynchronizeMaster($this->app);
        $syncMaster = $SynchronizeMaster->selectByBackupIDandStatus(self::$backup_id);

        $SynchronizeTables = new SynchronizeTables($this->app);
        $syncTables = $SynchronizeTables->selectByBackupIDandStatus(self::$backup_id);

        $SynchronizeFiles = new SynchronizeFiles($this->app);
        $syncFiles = $SynchronizeFiles->selectByBackupIDandStatus(self::$backup_id);

        if ((is_array($syncMaster) && (count($syncMaster) > 0)) ||
            (is_array($syncTables) && (count($syncTables) > 0)) ||
            (is_array($syncFiles) && (count($syncFiles) > 0))) {

            // process the sync informations
            $this->app['monolog']->addInfo("Start creating the new archive ".self::$archive_name,
                array('method' => __METHOD__, 'line' => __LINE__));
            if (file_exists(TEMP_PATH.'/synchronize') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/synchronize'))) {
                throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/synchronize'));
            }
            // create the backup directory
            if (false === @mkdir(TEMP_PATH.'/synchronize', 0755, true)) {
                throw new \Exception("Can't create the directory ".TEMP_PATH."/synchronize");
            }
            $this->app['monolog']->addInfo('Prepared temporary directory for the synchronize archive',
                array('method' => __METHOD__, 'line' => __LINE__));

            // process master
            $this->processMaster($syncMaster);

            // process tables
            $this->processTables($syncTables);

            // process files
            $this->processFiles($syncFiles);

            $data = array(
                'archive_id' => self::$archive_id,
                'archive_name' => self::$archive_name,
                'archive_date' => self::$archive_date,
                'backup_id' => self::$backup_id
            );
            if (!file_put_contents(TEMP_PATH.'/synchronize/archive.json', json_encode($data))) {
                throw new \Exception("Can't write the archive.json file for the synchronize archive!");
            }

            if (!file_exists(SYNCDATA_PATH.'/data/synchronize/.htaccess') || !file_exists(SYNCDATA_PATH.'/data/synchronize/.htpasswd')) {
                $this->app['utils']->createDirectoryProtection(SYNCDATA_PATH.'/data/synchronize');
            }
            if (file_exists(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".zip")) {
                @unlink(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".zip");
            }

            // create the archive and the MD5 file
            $zip = new Zip($this->app);
            $zip->zipDir(TEMP_PATH.'/synchronize', SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".zip");

            $md5 = md5_file(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".zip");
            if (!file_put_contents(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".md5", $md5)) {
                throw new \Exception("Can't write the MD5 checksum file for the synchronize archive!");
            }

            // copy archive and MD5 to /outbox
            if (!@copy(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".zip",
                SYNCDATA_PATH."/outbox/".self::$archive_name.".zip")) {
                throw new \Exception("Can't copy the archive ".self::$archive_name.".zip to the outbox!");
            }
            if (!@copy(SYNCDATA_PATH."/data/synchronize/".self::$archive_name.".md5",
                SYNCDATA_PATH."/outbox/".self::$archive_name.".md5")) {
                throw new \Exception("Can't copy the MD5 file ".self::$archive_name.".md5 to the outbox!");
            }

            // ok - all done, now update the sync tables
            $sync_master = array();
            foreach ($syncMaster as $master) {
                $SynchronizeMaster->update($master['id'], $master);
                $sync_master[] = $master['id'];
            }
            $sync_tables = array();
            foreach ($syncTables as $table) {
                $SynchronizeTables->update($table['id'], $table);
                $sync_tables[] = $table['id'];
            }
            $sync_files = array();
            foreach ($syncFiles as $file) {
                $SynchronizeFiles->update($file['id'], $file);
                $sync_files[] = $file['id'];
            }
            // add the synchronize archive
            $data = array(
                'backup_id' => self::$backup_id,
                'backup_date' => self::$backup_date,
                'archive_name' => self::$archive_name,
                'archive_date' => self::$archive_date,
                'sync_files' => implode(',', $sync_files),
                'sync_tables' => implode(',', $sync_tables),
                'sync_master' => implode(',', $sync_master)
            );
            $archive_id = -1;
            $SynchronizeArchives->insert($data, $archive_id);
            if ($archive_id != self::$archive_id) {
                throw new \Exception("Fatal: got not the expected archive ID!");
            }

            $this->app['monolog']->addInfo("Finished, synchronize archive created: ".self::$archive_name,
                array('method' => __METHOD__, 'line' => __LINE__));
            return 'ok';
        }
        else {
            // nothing to do...
            $result = "Create: there is nothing to do!";
            $this->app['monolog']->addInfo($result, array('method' => __METHOD__, 'line' => __LINE__));
            return $result;
        }
    }
}
