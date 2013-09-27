<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data;

class Confirmation
{
    protected $app = null;
    protected static $table_name = null;

    /**
     * Constructor - this class can be called as Silex\Application or as
     * SyncData\Application, therefore we dont specify the $app variable!
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'kit2_confirmation_log';
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
        `page_id` INT(11) NOT NULL DEFAULT '-1',
        `page_type` ENUM('PAGE','TOPICS','NEWS','OTHER') NOT NULL DEFAULT 'PAGE',
        `second_id` INT(11) NOT NULL DEFAULT '0',
        `installation_name` VARCHAR(255) NOT NULL DEFAULT '',
        `user_name` VARCHAR(255) NOT NULL DEFAULT '',
        `user_email` VARCHAR(255) NOT NULL DEFAULT '',
        `page_title` VARCHAR(255) NOT NULL DEFAULT '',
        `page_url` TEXT NOT NULL,
        `typed_name` VARCHAR(255) NOT NULL DEFAULT '',
        `typed_email` VARCHAR(255) NOT NULL DEFAULT '',
        `confirmed_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `time_on_page` INT(11) NOT NULL DEFAULT '0',
        `checksum` VARCHAR(32) NOT NULL DEFAULT '',
        `transmitted_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `status` ENUM('PENDING', 'SUBMITTED', 'DELETED') NOT NULL DEFAULT 'PENDING',
        `timestamp` TIMESTAMP,
        PRIMARY KEY (`id`)
    )
    COMMENT='Confirmation logfile for SyncData and kitFramework'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."'",
                array('method' => __METHOD__, 'line' => __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
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
            $this->app['monolog']->addInfo("Drop table '".self::$table_name."'", array(__METHOD__, __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    public function calculateChecksum($data)
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('The variable $data must be of type array!');
        }
        if (!isset($data['page_id']) || !isset($data['page_type']) || !isset($data['second_id']) ||
            !isset($data['installation_name']) || !isset($data['page_url']) || !isset($data['typed_name']) ||
            !isset($data['typed_email']) || !isset($data['confirmed_at']) || !isset($data['time_on_page'])) {
            throw new \Exception('To create the checksum the fields: page_id, page_type, second_id, '.
                'installation_name, page_url, typed_name, typed_email, confirmed_at and time_on_page '.
                'must be set, missing one or more fields!');
        }
        $check = implode('#', $data);
        return md5($check);
    }

    /**
     * Insert a new confirmation record
     *
     * @param array $data
     * @param integer $confirmation_id
     * @throws \Exception
     */
    public function insert($data, &$confirmation_id=-1)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value) {
                if (($key == 'id') || ($key == 'timestamp') || $key == 'checksum') continue;
                $insert[$key] = (is_string($value)) ? $this->app['utils']->sanitizeText($value) : $value;
            }
            // create the checksum
            $insert['checksum'] = $this->calculateChecksum($insert);
            // insert the record
            $this->app['db']->insert(self::$table_name, $insert);
            $confirmation_id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}
