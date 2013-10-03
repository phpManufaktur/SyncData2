<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Backend;

use Silex\Application;

class Control extends Backend
{

    public function exec(Application $app)
    {
        $this->initialize($app);

        $action = $app['request']->get('action', 'list');

        switch ($action) {
            case 'about':
                $About = new About();
                return $About->controllerAbout($app);
            case 'report':
                $Report = new Report();
                return $Report->controllerReport($app);
            case 'import':
                $Import = new Import();
                return $Import->controllerImport($app);
            case 'detail':
                $Detail = new Detail();
                return $Detail->controllerDetail($app);
            case 'list':
            default:
                $ShowList = new ShowList();
                return $ShowList->controllerList($app);
        }

    }
}
