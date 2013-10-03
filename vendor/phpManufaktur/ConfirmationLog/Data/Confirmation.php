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
        `received_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
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

    /**
     * Return the column names of the table
     *
     * @throws \Exception
     * @return multitype:unknown
     */
    public function getColumns()
    {
        try {
            $result = $this->app['db']->fetchAll("SHOW COLUMNS FROM `".self::$table_name."`");
            $columns = array();
            foreach ($result as $column) {
                $columns[] = $column['Field'];
            }
            return $columns;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Count the records in the table
     *
     * @param array $status flags, i.e. array('ACTIVE','LOCKED')
     * @throws \Exception
     * @return integer number of records
     */
    public function count($status=null)
    {
        try {
            $SQL = "SELECT COUNT(*) FROM `".self::$table_name."`";
            if (is_array($status) && !empty($status)) {
                $SQL .= " WHERE ";
                $use_status = false;
                if (is_array($status) && !empty($status)) {
                    $use_status = true;
                    $SQL .= '(';
                    $start = true;
                    foreach ($status as $stat) {
                        if (!$start) {
                            $SQL .= " OR ";
                        }
                        else {
                            $start = false;
                        }
                        $SQL .= "`status`='$stat'";
                    }
                    $SQL .= ')';
                }
            }
            return $this->app['db']->fetchColumn($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }


    /**
     * Calculate the checksum for the given data record
     *
     * @param array $data
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return string
     */
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
        $check = array();
        foreach ($data as $key => $value) {
            if (in_array($key, array('page_id', 'page_type', 'second_id', 'installation_name',
                'page_url', 'typed_name', 'typed_email', 'confirmed_at', 'time_on_page'))) {
                $check[$key] = $value;
            }
        }
        return md5(json_encode($check));
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

    /**
     * Check if a Checksum exists in table. Return the ID or FALSE
     *
     * @param string $checksum
     * @throws \Exception
     * @return Ambigous <boolean, integer>
     */
    public function existsChecksum($checksum, $ignore_id=null)
    {
        try {
            if (!is_null($ignore_id)) {
                $SQL = "SELECT `id` FROM `".self::$table_name."` WHERE `checksum`='$checksum' AND `id`!='$ignore_id'";
            }
            else {
                $SQL = "SELECT `id` FROM `".self::$table_name."` WHERE `checksum`='$checksum'";
            }
            return (($id = $this->app['db']->fetchColumn($SQL)) > 0) ? $id : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Get the checksum from table for the given ID
     *
     * @param integer $id
     * @throws \Exception
     * @return Ambigous <boolean, unknown>
     */
    public function getChecksum($id)
    {
        try {
            $SQL = "SELECT `checksum` FROM `".self::$table_name."` WHERE `id`='$id'";
            $checksum = $this->app['db']->fetchColumn($SQL);
            return (!empty($checksum)) ? $checksum : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Select the record with the given ID
     *
     * @param integer $id
     * @throws \Exception
     * @return multitype:unknown |boolean
     */
    public function select($id)
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `id`='$id'";
            $result = $this->app['db']->fetchAssoc($SQL);
            if (is_array($result) && isset($result['id'])) {
                $confirmation = array();
                foreach ($result as $key => $value) {
                    $confirmation[$key] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
                }
                return $confirmation;
            }
            else {
                return false;
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Delete the record with the given ID physically
     *
     * @param integer $id
     * @throws \Exception
     */
    public function delete($id)
    {
        try {
            $this->app['db']->delete(self::$table_name, array('id' => $id));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Select a list from table ConfirmationLog in paging view
     *
     * @param integer $limit_from start selection at position
     * @param integer $rows_per_page select max. rows per page
     * @param array $select_status tags, i.e. array('PENDING','SUBMITTED')
     * @param array $order_by fields to order by
     * @param string $order_direction 'ASC' (default) or 'DESC'
     * @throws \Exception
     * @return array selected records
     */
    public function selectList($limit_from, $rows_per_page, $select_status=null, $order_by=null, $order_direction='ASC')
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."`";
            if (is_array($select_status) && !empty($select_status)) {
                $SQL .= " WHERE (";
                $use_status = false;
                if (is_array($select_status) && !empty($select_status)) {
                    $use_status = true;
                    $SQL .= '(';
                    $start = true;
                    foreach ($select_status as $stat) {
                        if (!$start) {
                            $SQL .= " OR ";
                        }
                        else {
                            $start = false;
                        }
                        $SQL .= "`status`='$stat'";
                    }
                    $SQL .= ')';
                }
                $SQL .= ")";
            }
            if (is_array($order_by) && !empty($order_by)) {
                $SQL .= " ORDER BY ";
                $start = true;
                foreach ($order_by as $by) {
                    if (!$start) {
                        $SQL .= ", ";
                    }
                    else {
                        $start = false;
                    }
                    $SQL .= "`$by`";
                }
                $SQL .= " $order_direction";
            }
            $SQL .= " LIMIT $limit_from, $rows_per_page";
            return $this->app['db']->fetchAll($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Get the page titles of all submissions
     *
     * @param string $order_by
     * @param string $direction
     * @throws \Exception
     * @return Ambigous <boolean, multitype:NULL >
     */
    public function getAllTitles($order_by='received_at', $direction='DESC')
    {
        try {
            $SQL = "SELECT DISTINCT `page_title` FROM `".self::$table_name."` ORDER BY `$order_by` $direction";
            $results = $this->app['db']->fetchAll($SQL);
            $titles = array();
            foreach ($results as $result) {
                $titles[] = $this->app['utils']->unsanitizeText($result['page_title']);
            }
            return (!empty($titles)) ? $titles : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Check wether the given installation name has confirmed the page or article title
     *
     * @param string $page_title
     * @param string $installation_name
     * @throws \Exception
     * @return boolean
     */
    public function hasInstallationNameConfirmedTitle($page_title, $installation_name)
    {
        try {
            $SQL = "SELECT DISTINCT `installation_name` FROM `".self::$table_name."` WHERE `page_title`='$page_title' AND `installation_name`='$installation_name'";
            $result = $this->app['db']->fetchColumn($SQL);
            return ($result == $installation_name);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Select all records with the status 'PENDING'
     *
     * @throws \Exception
     * @return multitype:unknown
     */
    public function selectPendings()
    {
        try {
            $SQL = "SELECT * FROM `".self::$table_name."` WHERE `status`='PENDING'";
            $results = $this->app['db']->fetchAll($SQL);
            $pendings = array();
            foreach ($results as $key => $value) {
                $pendings[$key] = (is_string($value)) ? $this->app['utils']->unsanitizeText($value) : $value;
            }
            return $pendings;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Update a confirmation record
     * @param integer $id
     * @param array $data
     * @throws \Exception
     */
    public function update($id, $data)
    {
        try {
            $update = array();
            foreach ($data as $key => $value) {
                if (($key == 'id') || ($key == 'timestamp')) continue;
                $update[$key] = (is_string($value)) ? $this->app['utils']->sanitizeText($value) : $value;
            }
            $this->app['db']->update(self::$table_name, $update, array('id' => $id));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}
