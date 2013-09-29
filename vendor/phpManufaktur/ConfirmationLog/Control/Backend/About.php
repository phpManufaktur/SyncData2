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

class About extends Backend
{
    public function controllerAbout($app)
    {
        $this->initialize($app);

        return $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/about.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('about')
            ));
    }
}
