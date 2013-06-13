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

class BackupFiles
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
        self::$table_name = CMS_TABLE_PREFIX.'syncdata_backup_files';
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
      `relative_path` TEXT NOT NULL,
      `file_name` VARCHAR(128) NOT NULL DEFAULT '0',
      `file_checksum_origin` VARCHAR(32) NOT NULL DEFAULT '',
      `file_checksum_last` VARCHAR(32) NOT NULL DEFAULT '',
      `file_date` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
      `file_size` INT(11) NOT NULL DEFAULT '0',
      `action` ENUM('BACKUP','SYNCHRONIZE') NOT NULL DEFAULT 'BACKUP',
      `timestamp` TIMESTAMP,
      PRIMARY KEY (`id`)
    )
    COMMENT='SyncData - master table for tracking the CMS files'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."' for the class BackupFiles");
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

    public function selectFilesByBackupID($backup_id)
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `backup_id`='$backup_id'";
            $result = $this->app['db']->fetchAll($SQL);
            return is_array($result) ? $result : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    public function update($id, $data)
    {
        try {
            $update = array();
            foreach ($data as $key => $value)
                $update[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->sanitizeText($value) : $value;
            $this->app['db']->update(self::$table_name, $update, array('id' => $id));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

}
