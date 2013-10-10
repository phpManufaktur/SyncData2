<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data;

class Config
{
    protected $app = null;
    protected static $config = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->loadConfiguration();
    }

    /**
     * Set the default values in self::$config
     *
     */
    protected function setDefaultValues()
    {
        self::$config = array(
            'confirmation' => array(
                'only_once' => true,
                'identifier' => 'USERNAME'
            ),
            'filter' => array(
                'installations' => array(
                    // uses the INSTALLATION_NAMES defined in the config.php of the CLIENTS
                    'active' => true,
                    'groups' => array(
                        'installation_names' => array(

                        )
                    )
                ),
                'persons' => array(
                    // uses the group definitions of the CMS (where this config file is placed)
                    'active' => true,
                    'cms' => array(
                        'identifier' => 'USERNAME', // alternate: EMAIL -> table: cms_users
                        'ignore_groups' => array(
                            'Administrators'
                        )
                    )
                )
            )
        );
    }

    /**
     * Save the configuration file config.confirmation.json
     *
     * @throws \Exception
     */
    public function saveConfiguration()
    {
        if (!is_array(self::$config)) {
            throw new \Exception('`self::$config` must be of type array!');
        }

        file_put_contents(MANUFAKTUR_PATH.'/ConfirmationLog/config.confirmation.json',
            $this->app['utils']->JSONFormat(self::$config));
    }

    /**
     * Load the configuration file config.confirmation.json.
     * If the file not exists create a new one with default values
     *
     */
    public function loadConfiguration()
    {
        if (!file_exists(MANUFAKTUR_PATH.'/ConfirmationLog/config.confirmation.json')) {
            $this->setDefaultValues();
            $this->saveConfiguration();
        }

        self::$config = $this->app['utils']->readJSON(MANUFAKTUR_PATH.'/ConfirmationLog/config.confirmation.json');
    }

    /**
     * Return the configuration array
     *
     * @return array configuration
     */
    public function getConfiguration()
    {
        return self::$config;
    }

    /**
     * Set the configuration to the given array
     *
     * @param array $config
     * @throws \Exception
     */
    public function setConfiguration($config)
    {
        if (!is_array($config)) {
            throw new \Exception('$config must be of type array!');
        }
        self::$config = $config;
    }
}
