<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Setup;

use Silex\Application;
use phpManufaktur\ConfirmationLog\Data\Documents;

class Update
{
    protected $app = null;
    protected static $version = null;

    /**
     * Check if the give column exists in the table
     *
     * @param string $table
     * @param string $column_name
     * @return boolean
     */
    protected function columnExists($table, $column_name)
    {
        try {
            $query = $this->app['db']->query("DESCRIBE `$table`");
            while (false !== ($row = $query->fetch())) {
                if ($row['Field'] == $column_name) return true;
            }
            return false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Check if the given $table exists
     *
     * @param string $table
     * @throws \Exception
     * @return boolean
     */
    protected function tableExists($table)
    {
        try {
            $query = $this->app['db']->query("SHOW TABLES LIKE '$table'");
            return (false !== ($row = $query->fetch())) ? true : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Update for Release 0.11
     */
    protected function release_011()
    {
        if (!$this->columnExists(CMS_TABLE_PREFIX.'kit2_confirmation_log', 'received_at')) {
            // add release_status
            $SQL = "ALTER TABLE `".CMS_TABLE_PREFIX."kit2_confirmation_log` ADD `received_at` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00' AFTER `confirmed_at`";
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo('[ConfirmationLog Update] Add field `received_at` to table `kit2_confirmation_log`');
        }
    }

    /**
     * Update for Releas 0.19
     */
    protected function release_019()
    {
        if (!$this->tableExists(CMS_TABLE_PREFIX.'kit2_confirmation_documents')) {
            // create the documents table
            $Documents = new Documents($this->app);
            $Documents->createTable();
        }
    }

    /**
     * Execute the update
     *
     * @param Application $app
     */
    public function exec($app)
    {
        $this->app = $app;

        // get the VERSION of this release
        self::$version = trim(file_get_contents(MANUFAKTUR_PATH.'/ConfirmationLog/VERSION'));

        // Release 0.11
        $this->release_011();

        // Release 0.19
        $this->release_019();

        // Always update the droplet at SyncData installations
        if (defined('SYNCDATA_PATH')) {
            // this is a SyncData installation and we need droplets
            $Droplet = new Droplet($app);
            $Droplet->setDropletInfo(
                'syncdata_confirmation',
                MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation.php',
                'Get a confirmation from the user that he has read a page or article',
                'Please visit https://addons.phpmanufaktur.de/syncdata'
            );
            $Droplet->update();

            // change the old droplet to the actual code
            $Droplet->setDropletInfo(
                'confirmation_log',
                MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/confirmation_log.php',
                'Get a confirmation from the user that he has read a page or article',
                'Please visit https://addons.phpmanufaktur.de/syncdata'
                );
            $Droplet->checkOldConfirmationLogDroplet();

            // add the report droplet
            $Droplet->setDropletInfo(
                'syncdata_confirmation_report',
                MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation_report.php',
                'Enable to place the confirmation reports at the frontend',
                'Please visit https://addons.phpmanufaktur.de/syncdata'
            );
            // install the droplet
            $Droplet->install();
        }

        if (self::$version == '0.18') {
            // must delete the configuration file because it was restructured, will be restored automatically
            @unlink(MANUFAKTUR_PATH.'/ConfirmationLog/config.confirmation.json');
        }

        return $app['translator']->trans('Successfull updated the extension %extension%.',
            array('%extension%' => 'ConfirmationLog'));
    }

    /**
     * Controller for the kitFramework update
     *
     * @param Application $app
     */
    public function controllerUpdate(Application $app)
    {
        return $this->exec($app);
    }
}
