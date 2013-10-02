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


use phpManufaktur\ConfirmationLog\Data\Filter\Installations;
use phpManufaktur\ConfirmationLog\Data\Config;
use phpManufaktur\ConfirmationLog\Control\Filter\MissingConfirmation;

class Report extends Backend
{
    public function controllerReport($app)
    {
        $this->initialize($app);

        $ConfigurationData = new Config($app);
        $config = $ConfigurationData->getConfiguration();

        $Installations = new Installations($app);

        if (false === ($installation_names = $Installations->getAllNamedInstallations())) {
            // nothing to do - no installation_names!
            $this->setMessage('There exists no installation names in the records which can be used for the reports!');
        }
        else {
            // get all installation names to the configuration
            $has_changed = false;
            foreach ($installation_names as $installation_name) {
                if (!in_array($installation_name, $config['groups']['installation_names'])) {
                    $config['groups']['installation_names'][] = $installation_name;
                    $has_changed = true;
                }
            }
            if ($has_changed) {
                $ConfigurationData->setConfiguration($config);
                $ConfigurationData->saveConfiguration();
            }
        }

        $active_filter = (isset($_POST['filter'])) ? $_POST['filter'] : -1;

        $select_filter = array();
        $select_filter[] = array(
            'title' => $app['translator']->trans('- no selection -'),
            'selected' => (int) ($active_filter == -1),
            'value' => -1
        );

        $i=0;
        $missing = array();
        $use_group_by = null;
        $use_group = null;

        foreach ($config['groups'] as $group_name => $group) {
            foreach (array('title', 'name') as $group_by) {
                $filter_id = sprintf('%d_%s', $i, $group_by);
                if (false !== ($selected = ($filter_id == $active_filter))) {
                    $MissingConfirmation = new MissingConfirmation($app);
                    $missing = $MissingConfirmation->missingGroups($group, $group_by);
                    $use_group_by = $group_by;
                    $use_group = $group;
                }
                $select_filter[] = array(
                    'title' => $app['translator']->trans('Group: %group_name%, group by: %group_by%',
                        array('%group_name%' => $app['translator']->trans($group_name),
                            '%group_by%' => $app['translator']->trans($group_by))),
                    'selected' => $selected,
                    'value' => $filter_id
                );
            }
            $i++;
        }

        if (($active_filter != -1) && empty($missing)) {
            if (is_null($use_group)) {
                $this->setMessage('No results for filter ID %filter_id%.', array('%filter_id%' => $active_filter));
            }
            else {
                $this->setMessage('No results for the group %group%!', array('%group%' => $use_group));
            }
        }

        return $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/ConfirmationLog/Template', 'backend/report.twig'),
            array(
                'locale' => $app['translator']->getLocale(),
                'usage' => self::$usage,
                'message' => $this->getMessage(),
                'toolbar' => $this->getToolbar('report'),
                'select_filter' => $select_filter,
                'group_by' => $use_group_by,
                'missing' => $missing
            ));
    }

}
