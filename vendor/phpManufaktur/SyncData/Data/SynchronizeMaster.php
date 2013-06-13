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

class SynchronizeMaster
{
    protected $app = null;
    protected static $table_name = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'syncdata_synchronize_master';
    }

    /**
     * Create the table
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createTable ()
    {
        $table = self::$table_name;
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `backup_id` VARCHAR(16) NOT NULL DEFAULT '',
      `checksum` VARCHAR(32) NOT NULL DEFAULT '',
      `table_name` VARCHAR(128) NOT NULL DEFAULT '',
      `action` ENUM('CREATE','DELETE','ALTER') NOT NULL DEFAULT 'CREATE',
      `sql_create_table` TEXT NOT NULL,
      `sync_status` ENUM('CHECKED','ARCHIVED') NOT NULL DEFAULT 'CHECKED',
      `sync_archive_name` VARCHAR(128) NOT NULL DEFAULT '',
      `sync_archive_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
      `timestamp` TIMESTAMP,
      PRIMARY KEY (`id`)
    )
    COMMENT='SyncData - table for tracking changed table records'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."' for the class SynchronizeMaster");
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    } // createTable()

    /**
     * Insert a new record into the table
     *
     * @param array $data
     * @param string reference $id return the new ID
     * @throws \Doctrine\DBAL\DBALException
     */
    public function insert($data, &$id=null)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value)
                $insert[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->sanitizeText($value) : $value;
            $this->app['db']->insert(self::$table_name, $insert);
            $id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    public function isTableMarkedAsDeleted($table_name, $backup_id)
    {
        try {
            $SQL = "SELECT `table_name` FROM `".self::$table_name."` WHERE `table_name`='$table_name' AND `backup_id`='$backup_id' AND `action`='DELETE'";
            $result = $this->app['db']->fetchColumn($SQL);
            return ($result === $table_name) ? true : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

}
