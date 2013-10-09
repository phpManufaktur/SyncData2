<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Filter;

class Persons
{
    protected $app = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get all USERGROUPS from the CMS
     *
     * @throws \Exception
     * @return array|boolean FALSE if no group was found
     */
    public function getGroups()
    {
        try {
            $SQL = "SELECT DISTINCT `name`, `group_id` FROM `".CMS_TABLE_PREFIX."groups` ORDER BY `name` ASC";
            $results = $this->app['db']->fetchAll($SQL);
            $groups = array();
            foreach ($results as $result) {
                $groups[] = array(
                    'id' => $result['group_id'],
                    'name' => $this->app['utils']->unsanitizeText($result['name'])
                    );
            }
            return !empty($groups) ? $groups : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    public function getGroupByName($group_name)
    {
        try {
            $SQL = "SELECT * FROM `".CMS_TABLE_PREFIX."groups` WHERE `name`='$group_name'";
            $result = $this->app['db']->fetchAssoc($SQL);
            if (!isset($result['group_id'])) {
                return false;
            }
            $group = array();
            foreach ($result as $key => $value) {
                $group[$key] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
            }
            return $group;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Get all users/persons which belong to the CMS USERGROUP with the given ID
     *
     * @param integer $group_id
     * @throws \Exception
     * @return array|boolean
     */
    public function getPersonsByGroupID($group_id)
    {
        try {
            $SQL = "SELECT * FROM `".CMS_TABLE_PREFIX."users` WHERE (`groups_id`='$group_id' OR
                `groups_id` LIKE '$group_id,%' OR `groups_id` LIKE '%,$group_id'  OR
                `groups_id` LIKE '%,$group_id,%') ORDER BY `display_name` ASC";
            $results = $this->app['db']->fetchAll($SQL);
            $persons = array();
            foreach ($results as $key => $value) {
                $persons[$key] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
            }
            return (!empty($persons)) ? $persons : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
}
