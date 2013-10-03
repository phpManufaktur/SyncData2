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
use phpManufaktur\Basic\Control\kitCommand\Basic;
use phpManufaktur\ConfirmationLog\Data\Config;
use phpManufaktur\ConfirmationLog\Control\Filter\MissingConfirmation;

class Report extends Basic
{

    public function controllerReport(Application $app)
    {
        $this->initParameters($app);

        // get the parameters
        $params = $this->getCommandParameters();

        $group = isset($params['group']) ? strtolower(trim($params['group'])) : 'installation_names';
        $group_by = isset($params['group_by']) ? strtolower(trim($params['group_by'])) : 'title';

        $ConfigData = new Config($app);
        $config = $ConfigData->getConfiguration();
        $missing = array();

        if (!isset($config['groups'][$group])) {
            $this->setMessage('The group with the name %group% does not exists!',
                array('%group%' => $group));
        }
        else {
            $MissingConfirmation = new MissingConfirmation($app);
            $missing = $MissingConfirmation->missingGroups($config['groups'][$group], $group_by);
        }

        return $app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/ConfirmationLog/Template',
                '/command/report.twig', $this->getPreferredTemplateStyle()),
            array(
            'basic' => $this->getBasicSettings(),
            'missing' => $missing,
            'group_by' => $group_by
        ));
    }

    /**
     * Create the iFrame for the dialog and execute the route
     * /confirmationlog/dialog
     *
     * @param Application $app
     */
    public function controllerCreateIFrame(Application $app)
    {
        $this->initParameters($app);
        return $this->createIFrame('/confirmationlog/report');
    }
}
