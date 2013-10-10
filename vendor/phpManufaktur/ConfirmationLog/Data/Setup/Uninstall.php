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
use phpManufaktur\Basic\Control\CMS\UninstallAdminTool;
use phpManufaktur\ConfirmationLog\Data\Documents;

class Uninstall
{

    /**
     * Uninstall the ConfirmationLog table and the droplet [[syncdata_confirmation]]
     *
     * @param Application $app
     */
    public function exec($app)
    {
        // uninstall the confirmation log table
        $Confirmation = new Confirmation($app);
        $Confirmation->dropTable();

        // uninstall the documents table
        $Documents = new Documents($app);
        $Documents->dropTable();

        if (defined('SYNCDATA_PATH')) {
            // this is a SyncData installation remove droplet
            $Droplet = new Droplet($app);
            $Droplet->setDropletInfo('syncdata_confirmation', '', '', '');
            $Droplet->uninstall();
            $Droplet->setDropletInfo('syncdata_confirmation_report', '', '', '');
            $Droplet->uninstall();
            $Droplet->setDropletInfo('confirmation_log', '', '', '');
            $Droplet->uninstall();
        }
        else {
            // this is a kitFramework installation, also remove the admin tool
            $AdminTool = new UninstallAdminTool($app);
            $AdminTool->exec(MANUFAKTUR_PATH.'/ConfirmationLog/extension.json');
        }

        return $app['translator']->trans('Successfull uninstalled the extension %extension%.',
            array('%extension%' => 'ConfirmationLog'));
    }

    /**
     * Controller for the kitFramework
     *
     * @param Application $app
     */
    public function controllerUninstall(Application $app)
    {
        return $this->exec($app);
    }
}
