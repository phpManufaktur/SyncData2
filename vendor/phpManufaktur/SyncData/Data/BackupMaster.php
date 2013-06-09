<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data;

use phpManufaktur\SyncData\Control\Application;

class BackupMaster
{
    protected $app = null;
    protected static $table_name = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'syncdata_backup_master';
    }

    public function createTable ()
    {
        $table = self::$table_name;
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `backup_id` VARCHAR(16) NOT NULL DEFAULT '',
      `date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
      `sql_create_table` TEXT NOT NULL,
      `index_field` VARCHAR(64) NOT NULL DEFAULT 'NO_INDEX_FIELD',
      `origin_checksum` VARCHAR(32) NOT NULL DEFAULT '',
      `last_checksum` VARCHAR(32) NOT NULL DEFAULT '',
      `table_name` VARCHAR(128) NOT NULL DEFAULT '',
      `timestamp` TIMESTAMP,
      PRIMARY KEY (`id`)
    )
    COMMENT='SyncDataServer - master table for backups'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."' for the class BackupMaster");
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    } // createTable()

    /**
     * Insert a table to the backup master
     *
     * @param array $data
     * @param string $id
     * @throws \Exception
     */
    public function insert($data, &$id=null)
    {
        try {
            $this->app['db']->insert(self::$table_name, $data);
            $id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Get the last backup ID from the backup master
     *
     * @throws \Exception
     * @return Ambigous <boolean, unknown>
     */
    public function getLastBackupID()
    {
        try {
            $SQL = "SELECT DISTINCT `backup_id` FROM `".self::$table_name."` ORDER BY `backup_id` DESC LIMIT 1";
            $result = $this->app['db']->fetchAssoc($SQL);
            return (isset($result['backup_id'])) ? $result['backup_id'] : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    public function selectTablesByBackupID($backup_id)
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `backup_id`='$backup_id' ORDER BY `table_name` ASC";
            $result = $this->app['db']->fetchAll($SQL);
            return (is_array($result)) ? $result : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }
}
