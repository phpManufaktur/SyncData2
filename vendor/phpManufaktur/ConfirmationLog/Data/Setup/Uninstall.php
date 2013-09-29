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

class Uninstall
{

    public function exec($app)
    {
        // uninstall the confirmation log table
        $Confirmation = new Confirmation($app);
        $Confirmation->dropTable();

        if (defined('SYNCDATA_PATH')) {
            // this is a SyncData installation remove droplet
            $Droplet = new Droplet($app);
            $Droplet->setDropletInfo(
                'syncdata_confirmation',
                MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation.php',
                'Get a confirmation from the user that he has read a page or article',
                'Please visit https://addons.phpmanufaktur.de/syncdata'
            );
            $Droplet->uninstall();
        }

        return $app['translator']->trans('Successfull uninstalled the extension %extension%.',
            array('%extension%' => 'ConfirmationLog'));
    }
}
