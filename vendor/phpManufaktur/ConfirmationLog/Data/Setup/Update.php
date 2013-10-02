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

use phpManufaktur\ConfirmationLog\Data\Confirmation;

class Update
{
    protected $app = null;

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

    public function exec($app)
    {
        $this->app = $app;

        $this->release_011();

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
            $Droplet->checkOldConfirmationLogDroplet();
        }

        return $app['translator']->trans('Successfull updated the extension %extension%.',
            array('%extension%' => 'ConfirmationLog'));
    }
}
