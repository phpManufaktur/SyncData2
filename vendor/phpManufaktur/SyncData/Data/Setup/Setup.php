<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data\Setup;

use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Data\BackupMaster;
use phpManufaktur\SyncData\Data\BackupTables;
use phpManufaktur\SyncData\Data\SynchronizeTables;
use phpManufaktur\SyncData\Data\BackupFiles;
use phpManufaktur\SyncData\Data\SynchronizeMaster;
use phpManufaktur\SyncData\Data\SynchronizeFiles;

class Setup
{

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function exec()
    {
        // create the needed tables
        try {
            // Backup Master table
            $BackupMaster = new BackupMaster($this->app);
            $BackupMaster->createTable();
            // Backup Tables
            $BackupTables = new BackupTables($this->app);
            $BackupTables->createTable();
            // Synchronize Tables
            $SynchronizeTables = new SynchronizeTables($this->app);
            $SynchronizeTables->createTable();
            // Backup files
            $BackupFiles = new BackupFiles($this->app);
            $BackupFiles->createTable();
            // Synchronize Master
            $SynchronizeMaster = new SynchronizeMaster($this->app);
            $SynchronizeMaster->createTable();
            // Synchronize Files
            $SynchronizeFiles = new SynchronizeFiles($this->app);
            $SynchronizeFiles->createTable();

            $this->app['monolog']->addInfo('Setup is complete');
            return 'Setup is complete';
        } catch (\Exception $e) {
            throw $e;
        }
    }
}