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
class Uninstall
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
     * Action handler for the uninstall routines
     *
     * @throws Exception
     * @return string
     */
    public function exec()
    {
        // delete the tables
        try {
            // Backup Master table
            $BackupMaster = new BackupMaster($this->app);
            $BackupMaster->dropTable();

            // Backup Tables
            $BackupTables = new BackupTables($this->app);
            $BackupTables->dropTable();

            // Synchronize Tables
            $SynchronizeTables = new SynchronizeTables($this->app);
            $SynchronizeTables->dropTable();

            // Backup files
            $BackupFiles = new BackupFiles($this->app);
            $BackupFiles->dropTable();

            // Synchronize Master
            $SynchronizeMaster = new SynchronizeMaster($this->app);
            $SynchronizeMaster->dropTable();

            // Synchronize Files
            $SynchronizeFiles = new SynchronizeFiles($this->app);
            $SynchronizeFiles->dropTable();

            // Synchronize Archives
            $SynchronizeArchives = new SynchronizeArchives($this->app);
            $SynchronizeArchives->dropTable();

            // Synchronize Client
            $SynchronizeClient = new SynchronizeClient($this->app);
            $SynchronizeClient->dropTable();

            $this->app['monolog']->addInfo('All tables removed',
                array('method' => __METHOD__, 'line' => __LINE__));
            return 'All tables removed';
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }
}
