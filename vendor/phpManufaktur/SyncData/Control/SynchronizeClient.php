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

class SynchronizeClient
{

    protected $app = null;
    protected static $backup_id = null;
    protected static $backup_date = null;
    protected static $archive_id = null;
    protected static $archive_date = null;
    protected static $archive_name = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function exec()
    {
        return 'ok';
    }
}