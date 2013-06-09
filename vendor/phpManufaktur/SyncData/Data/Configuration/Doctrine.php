<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data\Configuration;

use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Control\JSON\JSONFormat;

class Doctrine
{
    protected $app = null;
    protected static $config_file = null;
    protected static $config_array = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$config_file = SYNC_DATA_PATH.'/config/doctrine.json';
        if (!$this->app->offsetExists('monolog')) {
            // missing the logging!
            throw new ConfigurationException("Monolog is not available!");
        }
        $this->initConfiguration();
    }

    /**
     * Return the configuration array for Doctrine
     *
     * @return array configuration array
     */
    public function getConfiguration()
    {
        return self::$config_array;
    }

    /**
     * Get the database settings from the parent CMS and save them as doctrine.json
     *
     * @throws ConfigurationException
     */
    protected function getConfigurationFromCMS()
    {
        $this->app['monolog']->addInfo(sprintf("The doctrine config file %s does not exists!", self::$config_file));
        // try to get the configuration from the CMS
        try {
            $this->app['monolog']->addInfo("Search for the CMS configuration file");
            if (file_exists(realpath(SYNC_DATA_PATH.'/../config.php'))) {
                include_once realpath(SYNC_DATA_PATH.'/../config.php');
                self::$config_array = array(
                    'DB_TYPE' => DB_TYPE,
                    'DB_HOST' => DB_HOST,
                    'DB_PORT' => DB_PORT,
                    'DB_USERNAME' => DB_USERNAME,
                    'DB_PASSWORD' => DB_PASSWORD,
                    'DB_NAME' => DB_NAME,
                    'TABLE_PREFIX' => TABLE_PREFIX
                );
                $this->app['monolog']->addInfo('Read the database configuration from the CMS config.php');
                // encode a formatted JSON file
                $jsonFormat = new JSONFormat();
                $json = $jsonFormat->format(self::$config_array);
                if (!@file_put_contents(self::$config_file, $json)) {
                    throw new ConfigurationException("Can't write the configuration file for Doctrine!");
                }
                $this->app['monolog']->addInfo("Create configuration file doctrine.json for Doctrine");
            }
            else {
                throw new ConfigurationException("Can't read the CMS configuration, SyncData stopped.");
            }
        } catch (ConfigurationException $e) {
            throw $e;
        }
    }

    /**
     * Initialize the Doctrine configuration settings
     *
     * @throws ConfigurationException
     */
    protected function initConfiguration()
    {
        if (!file_exists(self::$config_file)) {
            // get the configuration directly from CMS
            $this->getConfigurationFromCMS();
        }
        elseif ((false === (self::$config_array = json_decode(@file_get_contents(self::$config_file), true))) || !is_array(self::$config_array)) {
            throw new ConfigurationException("Can't read the Doctrine configuration file!");
        }
    }

    /**
     * Initialize and configure Doctrine as shared service for the application
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function initDoctrine()
    {
        // initialize Doctrine
        $config = new \Doctrine\DBAL\Configuration();
        $connectionParams = array(
            'dbname' => self::$config_array['DB_NAME'],
            'user' => self::$config_array['DB_USERNAME'],
            'password' => self::$config_array['DB_PASSWORD'],
            'host' => self::$config_array['DB_HOST'],
            'port' => self::$config_array['DB_PORT'],
            'driver' => 'pdo_mysql',
        );
        define('CMS_TABLE_PREFIX', self::$config_array['TABLE_PREFIX']);

        $this->app['db'] = $this->app->share(function() use($connectionParams, $config) {
            return \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
        });
        $this->app['monolog']->addInfo("Doctrine initialized");
    }

}