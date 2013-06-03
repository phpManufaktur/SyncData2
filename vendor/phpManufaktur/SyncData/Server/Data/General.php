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
}
