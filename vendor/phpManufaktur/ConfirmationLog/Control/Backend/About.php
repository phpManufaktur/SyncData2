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

class About extends Backend
{
    /**
     *
     * @param unknown $app
     */
    public function controllerAbout($app)
    {
        $this->initialize($app);

        $extension = $app['utils']->readJSON(MANUFAKTUR_PATH.'/ConfirmationLog/extension.json');

        return $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/about.twig'),
            array(
                'locale' => $app['translator']->getLocale(),
                'extension' => $extension,
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('about')
            ));
    }

    public function controllerAppAbout(Application $app)
    {
        return $this->controllerAbout($app);
    }
}
