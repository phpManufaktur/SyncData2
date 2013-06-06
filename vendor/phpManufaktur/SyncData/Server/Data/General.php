<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Server\Data;

use phpManufaktur\SyncData\Server\Control\Application;

class General {

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Return the tables of the configured database
     *
     * @throws \Exception
     * @return array
     */
    public function getTables()
    {
        try {
            $result = $this->app['db']->fetchAll("SHOW TABLES");
            $tables = array();
            if (is_array($result)) {
                foreach ($result as $item) {
                    foreach ($item as $show => $table) {
                        $tables[] = $table;
                    }
                }
            }
            return $tables;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getTableContent($table)
    {
        try {
            $result = $this->app['db']->fetchAll("SELECT * FROM `$table`");
            return $result;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getTableContentChecksum($table)
    {
        try {
            $result = $this->app['db']->fetchAll("SELECT * FROM `$table`");
            $checksum = false;
            $content = '';
            if (is_array($result)) {
                foreach ($result as $row) {
                    $content .= md5(str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', implode(',', $row)));
                }
                $checksum = md5($content);
            }
            return $checksum;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function listTableIndexes($table)
    {
        try {
            $shemaManager = $this->app['db']->getSchemaManager();
            $result = $shemaManager->listTableIndexes($table);
            return $result;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getCreateTableSQL($table, $replaceTablePrefix=true, $useIfNotExists=true)
    {
        try {
            $result = $this->app['db']->fetchAssoc("SHOW CREATE TABLE `$table`");
            $SQL = false;
            if (isset($result['Create Table']) && isset($result['Table'])) {
                // get the table name
                $table = $result['Table'];
                $no_prefix = $result['Table'];
                $not_exists = '';
                if ($replaceTablePrefix && (CMS_TABLE_PREFIX !== '')) {
                    $no_prefix = str_replace(CMS_TABLE_PREFIX, '{{ SyncData:TABLE_PREFIX }}', $table);
                }
                if ($useIfNotExists) {
                    $not_exists = ' IF NOT EXISTS';
                }
                $SQL = str_replace(sprintf("CREATE TABLE `%s`", $table), sprintf("CREATE TABLE%s `%s`", $not_exists, $no_prefix), $result['Create Table']);
            }
            return $SQL;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function tableExists($table)
    {
        try {
            $result = $this->app['db']->fetchAssoc("DESCRIBE `$table`");
            return true;
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->app['monolog']->addInfo("The table $table does not exists!");
            return false;
        }
    }

    public function dropTable($table)
    {
        try {
            $this->app['db']->query("DROP TABLE IF EXISTS `$table`");
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function query($SQL)
    {
        try {
            $this->app['db']->query($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function insertRows($table, $rows, $replace_cms_url=true)
    {
        try {
            foreach ($rows as $row) {
                if ($replace_cms_url) {
                    $content = array();
                    foreach ($row as $key => $value) {
                        $content[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? str_replace('{{ SyncData:CMS_URL }}', CMS_URL, $value) : $value;
                    }
                    $row = $content;
                }
                $this->app['db']->insert($table, $row);
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e->getMessage());
        }
    }

}
