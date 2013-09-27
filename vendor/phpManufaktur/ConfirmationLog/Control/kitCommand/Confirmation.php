<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\kitCommand;

use Silex\Application;

class Confirmation
{

    public function controllerDialog(Application $app)
    {
        return $app['utils']->createGUID();
        return 'Here we are!';
    }
}
