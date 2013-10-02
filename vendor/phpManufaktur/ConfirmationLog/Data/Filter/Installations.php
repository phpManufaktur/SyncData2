<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Filter;

class Installations
{
    protected $app = null;
    protected static $table_name = null;

    public function __construct($app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'kit2_confirmation_log';
    }

    public function getAllNamedInstallations()
    {
        try {
            $SQL = "SELECT DISTINCT `installation_name` FROM `".self::$table_name."` WHERE `installation_name` != '' ORDER BY `installation_name` ASC";
            $results = $this->app['db']->fetchAll($SQL);
            $installations = array();
            foreach ($results as $result) {
                $installations[] = $this->app['utils']->unsanitizeText($result['installation_name']);
            }
            return !empty($installations) ? $installations : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
}
