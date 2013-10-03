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

use phpManufaktur\ConfirmationLog\Data\Setup\Addons;

class UninstallTool
{
    protected $app = null;

    /**
     * Uninstall the Admin-Tool for the ConfirmationLog and the droplet
     * [[syncdata_confirmation_report]]
     *
     * @param Application $app
     */
    public function exec($app)
    {
        $this->app = $app;

        $Addons = new Addons($app);
        $Addons->delete('syncdata_confirmationlog');

        $this->app['utils']->rrmdir(CMS_PATH.'/modules/syncdata_confirmationlog');

        // this is a SyncData installation remove the droplet
        $Droplet = new Droplet($app);
        $Droplet->setDropletInfo(
            'syncdata_confirmation_report',
            MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation_report.php',
            'Show reports to the confirmations in the frontend',
            'Please visit https://addons.phpmanufaktur.de/syncdata'
        );
        $Droplet->uninstall();

        $message = 'Successfull removed the SyncData Admin-Tool for the ConfirmationLog.';
        $app['monolog']->addInfo($message, array(__METHOD__, __LINE__));
        return $app['translator']->trans($message);
    }
}
