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
use Silex\Application;
use phpManufaktur\Basic\Control\CMS\InstallAdminTool;

class Setup
{

    /**
     * General installation routine for the Admin-Tool. Will be called directly
     * by SyncData and also from the kitFramework Controller.
     *
     * @param Application $app
     */
    public function exec($app)
    {
        $Confirmation = new Confirmation($app);
        $Confirmation->createTable();

        if (defined('SYNCDATA_PATH')) {
            // this is a SyncData installation and we need droplets
            $Droplet = new Droplet($app);
            $Droplet->setDropletInfo(
                'syncdata_confirmation',
                MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation.php',
                'Get a confirmation from the user that he has read a page or article',
                'Please visit https://addons.phpmanufaktur.de/syncdata'
                );
            $Droplet->install();
        }
        else {
            // this is the kitFramework installation
            if (file_exists(CMS_PATH.'/modules/syncdata_confirmationlog')) {
                // SyncData has installed the Admin-Tool for the confirmation log
                $Addon = new Addons($app);
                $Addon->delete('syncdata_confirmationlog');
                $app['filesystem']->remove(CMS_PATH.'/modules/syncdata_confirmationlog');
                $app['monolog']->addInfo('Removed the SyncData Admin-Tool for the ConfirmationLog');
            }
            // setup kit_framework_event as Add-on in the CMS
            $admin_tool = new InstallAdminTool($app);
            $admin_tool->exec(MANUFAKTUR_PATH.'/ConfirmationLog/extension.json', '/confirmationlog/cms');
        }

        return $app['translator']->trans('Successfull installed the extension %extension%.',
            array('%extension%' => 'ConfirmationLog'));
    }

    /**
     * Controller for the kitFramework installation
     *
     * @param Application $app
     */
    public function controllerSetup(Application $app)
    {
        return $this->exec($app);
    }
}
