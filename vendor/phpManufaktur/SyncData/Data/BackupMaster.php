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

/**
 * The master class for syncdata_backup_master
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class BackupMaster
{
    const NO_INDEX_FIELD = 'NO_INDEX_FIELD';
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
        self::$table_name = CMS_TABLE_PREFIX.'syncdata_backup_master';
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
            $this->app['monolog']->addInfo("Created table '".self::$table_name."' for the class BackupMaster",
                array('method' => __METHOD__, 'line' => __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Delete table - switching check for foreign keys off before executing
     *
     * @throws \Exception
     */
    public function dropTable()
    {
        try {
            $table = self::$table_name;
            $SQL = <<<EOD
    SET foreign_key_checks = 0;
    DROP TABLE IF EXISTS `$table`;
    SET foreign_key_checks = 1;
EOD;
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Drop table ".self::$table_name, array(__METHOD__, __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Insert a table to the backup master
     *
     * @param array $data
     * @param string reference $id return the new ID
     * @throws \Exception
     */
    public function insert($data, &$id=null)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value)
                $insert[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->sanitizeText($value) : $value;
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
    public function selectLastBackupID()
    {
        try {
            $SQL = "SELECT DISTINCT `backup_id` FROM `".self::$table_name."` ORDER BY `backup_id` DESC LIMIT 1";
            $result = $this->app['db']->fetchAssoc($SQL);
            return (isset($result['backup_id'])) ? $result['backup_id'] : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Select all tables from the given backup ID
     *
     * @param string $backup_id
     * @throws \Doctrine\DBAL\DBALException
     * @return Ambigous <boolean, unknown>
     */
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

    /**
     * Update the specified master ID with a data record
     *
     * @param string $master_id
     * @param array $data associative array with the fields and data
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update($master_id, $data)
    {
        try {
            $update = array();
            foreach ($data as $key => $value)
                $update[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->sanitizeText($value) : $value;
            $this->app['db']->update(self::$table_name, $update, array('id' => $master_id));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Get the creation date for the given backup ID
     *
     * @param string $backup_id
     * @throws \Doctrine\DBAL\DBALException
     */
    public function selectBackupDate($backup_id)
    {
        try {
            $SQL = "SELECT `date` FROM `".self::$table_name."` WHERE `backup_id`='$backup_id' ORDER BY `date` ASC LIMIT 1";
            return $this->app['db']->fetchColumn($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }
}
