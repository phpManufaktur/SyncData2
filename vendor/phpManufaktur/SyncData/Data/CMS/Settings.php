<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data\CMS;

use phpManufaktur\SyncData\Control\Application;

/**
 * Get the basic settings of the parent CMS from the settings table
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class Settings {

    protected $app = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the Settings from the parent CMS
     *
     * @throws \Exception
     * @return array CMS settings
     */
    public function getSettings()
    {
        try {
            $SQL = "SELECT * FROM `".CMS_TABLE_PREFIX."settings`";
            $result = $this->app['db']->fetchAll($SQL);
            $settings = array();
            if (is_array($result)) {
                foreach ($result as $setting) {
                    $settings[$setting['name']] = $setting['value'];
                }
            }
            $this->app['monolog']->addInfo('Read the CMS settings from DB', array('method' => __METHOD__, 'line' => __LINE__));
            return $settings;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
}