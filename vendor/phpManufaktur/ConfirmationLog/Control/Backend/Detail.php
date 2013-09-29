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

class Detail extends Backend
{
    public function controllerDetail($app)
    {
        $this->initialize($app);
        $this->clearMessage();

        if (false === ($confirmation = $this->ConfirmationData->select($this->getParameter('id', -1)))) {
            $this->setMessage('The confirmation with the ID %id% does not exists!', array('%id%' => $this->getParameter('id', -1)));
            $confirmation = null;
        }

        return $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/detail.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('list'),
                'confirmation' => $confirmation,
                'message' => $this->getMessage(),
                'link_list' => self::$link.'&action=list&usage='.self::$usage
            ));
    }
}
