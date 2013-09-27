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
use phpManufaktur\SyncData\Data\SynchronizeArchives;
use phpManufaktur\SyncData\Data\SynchronizeClient;

/**
 * Setup routines for SyncData
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class Setup
{

    protected $app = null;

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
     * Action handler for the setup routines
     *
     * @throws Exception
     * @return string
     */
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
            // Synchronize Archives
            $SynchronizeArchives = new SynchronizeArchives($this->app);
            $SynchronizeArchives->createTable();
            // Synchronize Client
            $SynchronizeClient = new SynchronizeClient($this->app);
            $SynchronizeClient->createTable();

            $this->app['monolog']->addInfo('Setup is complete',
                array('method' => __METHOD__, 'line' => __LINE__));
            return 'Setup is complete';
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }
}
