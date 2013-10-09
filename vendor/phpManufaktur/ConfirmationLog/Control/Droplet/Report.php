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
use phpManufaktur\ConfirmationLog\Data\Filter\Persons;

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

        if ($parameter['filter'] == 'persons') {
            // filter for confirmations of PERSONS
            $Persons = new Persons($app);

            if (empty($parameter['group'])) {
                $this->setMessage('Please define a group!');
            }
            elseif (false === ($group = $Persons->getGroupByName($parameter['group']))) {
                $this->setMessage('The user group with the name %group% does not exists!',
                    array('%group%' => $parameter['group']));
            }
            else {
                $MissingConfirmation = new MissingConfirmation($app);
                $missing = $MissingConfirmation->missingPersons($group['group_id'], $parameter['group_by'], $parameter['identifier']);
            }
        }
        elseif ($parameter['filter'] == 'installations') {
            // filter for confirmations of INSTALLATIONS
            if (empty($parameter['group'])) {
                $this->setMessage('Please define a group!');
            }
            elseif (!isset($config['filter']['installations']['groups'][$parameter['group']])) {
                $this->setMessage('The group with the name %group% does not exists!',
                    array('%group%' => $parameter['group']));
            }
            else {
                $MissingConfirmation = new MissingConfirmation($app);
                $missing = $MissingConfirmation->missingGroups($config['filter']['installations']['groups'][$parameter['group']], $parameter['group_by']);
            }
        }
        else {
            // filter group does not exists!
            $this->setMessage('The filter group with the name %filter% is not defined!',
                array('%filter%' => $parameter['filter']));
        }

        return $app['twig']->render('ConfirmationLog/Template/default/droplet/report.twig', array(
            'TEMPLATE_URL' => WB_URL.'/ConfirmationLog/Template/default/droplet',
            'locale' => $app['translator']->getLocale(),
            'message' => $this->getMessage(),
            'missing' => $missing,
            'group_by' => $parameter['group_by'],
            'filter' => $parameter['filter']
            ));
    }


}
