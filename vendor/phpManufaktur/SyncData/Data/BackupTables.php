<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data;

use phpManufaktur\SyncData\Control\Application;

class BackupTables
{
    protected $app = null;
    protected static $table_name = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'syncdata_backup_tables';
    }

    public function createTable ()
    {
        $table = self::$table_name;
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `backup_id` VARCHAR(16) NOT NULL DEFAULT '',
      `index_id` INT(11) NOT NULL DEFAULT '0',
      `checksum` VARCHAR(32) NOT NULL DEFAULT '',
      `table_name` VARCHAR(128) NOT NULL DEFAULT '',
      `timestamp` TIMESTAMP,
      PRIMARY KEY (`id`)
    )
    COMMENT='SyncData - master table for tracking table records'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."' for the class BackupTables");
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage(), 0, $e);
        }
    } // createTable()

    public function insert($data, &$id=null)
    {
        try {
            $this->app['db']->insert(self::$table_name, $data);
            $id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

}
