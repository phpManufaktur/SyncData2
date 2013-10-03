<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Droplet;

use phpManufaktur\ConfirmationLog\Control\Control;
use phpManufaktur\ConfirmationLog\Control\Filter\MissingConfirmation;
use phpManufaktur\ConfirmationLog\Data\Config;

class Report extends Control
{

    /**
     * Execute the droplet [[syncdata_confirmation_report]]
     *
     * @param Application $app
     * @param array $parameter
     * @return string rendered table with results
     */
    public function exec($app, $parameter)
    {
        if (isset($_SESSION['DROPLET_EXECUTED_BY_DROPLETS_EXTENSION'])) {
            // ignore the scan function of the DropletsExtension
            return null;
        }

        $this->initialize($app);
        $this->app['translator']->setLocale(strtolower(LANGUAGE));

        $ConfigData = new Config($app);
        $config = $ConfigData->getConfiguration();
        $missing = array();

        if (!isset($config['groups'][$parameter['group']])) {
            $this->setMessage('The group with the name %group% does not exists!',
                array('%group%' => $parameter['group']));
        }
        else {
            $MissingConfirmation = new MissingConfirmation($app);
            $missing = $MissingConfirmation->missingGroups($config['groups'][$parameter['group']], $parameter['group_by']);
        }

        return $app['twig']->render('ConfirmationLog/Template/default/droplet/report.twig', array(
            'TEMPLATE_URL' => WB_URL.'/ConfirmationLog/Template/default/droplet',
            'locale' => $app['translator']->getLocale(),
            'message' => $this->getMessage(),
            'missing' => $missing,
            'group_by' => $parameter['group_by']
            ));
    }


}
