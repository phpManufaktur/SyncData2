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
use phpManufaktur\ConfirmationLog\Data\Filter\Persons;

class Report extends Backend
{
    public function controllerReport($app)
    {
        $this->initialize($app);

        $ConfigurationData = new Config($app);
        $config = $ConfigurationData->getConfiguration();


        $active_filter = (isset($_POST['filter'])) ? $_POST['filter'] : -1;

        $select_filter = array();
        $select_filter[] = array(
            'title' => $app['translator']->trans('- no selection -'),
            'selected' => (int) ($active_filter == -1),
            'value' => -1
        );

        // can be 'title' or 'name'
        $use_group_by = null;
        // the array with the missing confirmations
        $missing = array();
        // the group name (installation group or CMS user group)
        $use_group = null;
        // counter for the filter
        $filter_counter = 0;
        // filter type - needed by the template
        $filter_type = null;

        if ($config['filter']['installations']['active']) {
            // the filter for the INSTALLATION_NAME is active

            $Installations = new Installations($app);

            if (false === ($installation_names = $Installations->getAllNamedInstallations())) {
                // nothing to do - no installation_names!
                $this->setMessage('There exists no installation names in the records which can be used for the reports!');
            }
            else {
                // get all installation names to the configuration
                $has_changed = false;
                foreach ($installation_names as $installation_name) {
                    if (!in_array($installation_name, $config['filter']['installations']['groups']['installation_names'])) {
                        $config['filter']['installations']['groups']['installation_names'][] = $installation_name;
                        $has_changed = true;
                    }
                }
                if ($has_changed) {
                    $ConfigurationData->setConfiguration($config);
                    $ConfigurationData->saveConfiguration();
                }
            }


            foreach ($config['filter']['installations']['groups'] as $group_name => $group) {
                // loop through the installation groups defined in config.confirmation.json
                foreach (array('title', 'name') as $group_by) {
                    // create the filter ID
                    $filter_id = sprintf('%d_%s', $filter_counter, $group_by);
                    if (false !== ($selected = ($filter_id == $active_filter))) {
                        // execute the filter for this ID
                        $MissingConfirmation = new MissingConfirmation($app);
                        $missing = $MissingConfirmation->missingGroups($group, $group_by);
                        $use_group_by = $group_by;
                        $use_group = $group;
                        $filter_type = 'installations';
                    }
                    $select_filter[] = array(
                        'title' => $app['translator']->trans('Group: %group_name%, group by: %group_by%',
                            array('%group_name%' => $app['translator']->trans($group_name),
                                '%group_by%' => $app['translator']->trans($group_by))),
                        'selected' => $selected,
                        'value' => $filter_id
                    );
                }
                $filter_counter++;
            }
        }

        if ($config['filter']['persons']['active']) {
            // filter for the PERSONS is active

            $Persons = new Persons($app);
            $groups = $Persons->getGroups();


            foreach ($groups as $group) {
                if (in_array($group['name'], $config['filter']['persons']['cms']['ignore_groups'])) {
                    // ignore this group
                    continue;
                }
                foreach (array('title', 'name') as $group_by) {
                    $filter_id = sprintf('%d_%s', $filter_counter, $group_by);
                    if (false !== ($selected = ($filter_id == $active_filter))) {
                        // execute the filter for this ID
                        $MissingConfirmation = new MissingConfirmation($app);
                        $missing = $MissingConfirmation->missingPersons($group['id'], $group_by,
                            $config['filter']['persons']['cms']['identifier']);
                        $use_group_by = $group_by;
                        $use_group = $group['name'];
                        $filter_type = 'persons';
                    }
                    $select_filter[] = array(
                        'title' => $app['translator']->trans('Persons: %group_name%, group by: %group_by%',
                            array('%group_name%' => $app['translator']->trans($group['name']),
                                '%group_by%' => $app['translator']->trans(($group_by == 'title') ? 'title' : 'person_name'))),
                        'selected' => $selected,
                        'value' => $filter_id
                    );
                }
                $filter_counter++;
            }

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
                'missing' => $missing,
                'filter' => $filter_type
            ));
    }

}
