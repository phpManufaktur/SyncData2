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
use phpManufaktur\SyncData\Data\Configuration\ConfigurationException;
use phpManufaktur\SyncData\Data\CMS\Settings;
use phpManufaktur\SyncData\Control\JSON\JSONFormat;
use phpManufaktur\SyncData\Data\Setup\Setup;

/**
 * Create and read the configuration files for SyncData
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class Configuration
{

    protected $app = null;
    protected static $config_array = null;
    protected static $config_file = null;
    protected static $key = null;
    protected static $executed_setup = false;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app, $config_array=null)
    {
        $this->app = $app;
        self::$config_file = SYNCDATA_PATH.'/config/syncdata.json';
        self::$config_array = $config_array;
    }

    public function executedSetup()
    {
        return self::$executed_setup;
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
     * Get the configuration information from the parent CMS
     * and create syncdata.json
     *
     * @throws \Exception
     * @throws ConfigurationException
     */
    protected function getConfigurationFromCMS()
    {
        $cmsSettings = new Settings($this->app);
        $cms_settings = $cmsSettings->getSettings();
        if (file_exists(realpath(SYNCDATA_PATH.'/../config.php'))) {
            include_once realpath(SYNCDATA_PATH.'/../config.php');
        }
        else {
            throw new \Exception("Can't read the CMS configuration, SyncData stopped.");
        }

        // Windows OS?
        $is_WIN = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? true : false;

        // generate a KEY
        self::$key = $this->app['utils']->generatePassword(9, false, 'lud');

        self::$config_array = array(
            'CMS' => array(
                'CMS_SERVER_EMAIL' => $cms_settings['server_email'],
                'CMS_SERVER_NAME' => $cms_settings['wbmailer_default_sendername'],
                'CMS_TYPE' => (isset($cms_settings['lepton_version'])) ? 'LEPTON' : 'WebsiteBaker',
                'CMS_VERSION' => (isset($cms_settings['lepton_version'])) ? $cms_settings['lepton_version'] : $cms_settings['wb_version'],
                'CMS_MEDIA_DIRECTORY' => $cms_settings['media_directory'],
                'CMS_PAGES_DIRECTORY' => $cms_settings['pages_directory'],
                'CMS_URL' => WB_URL,
                'CMS_PATH' => WB_PATH,
                'CMS_ADMIN_URL' => (isset($cms_settings['lepton_version'])) ? WB_URL.'/admins' : WB_URL.'/admin',
                'CMS_ADMIN_PATH' => (isset($cms_settings['lepton_version'])) ? WB_PATH.'/admins' : WB_PATH.'/admin',
                'INSTALLATION_NAME' => defined('INSTALLATION_NAME') ? INSTALLATION_NAME : ''
            ),
            'email' => array(
                'active' => $is_WIN ? false : true
            ),
            'monolog' => array(
                'level' => 200,
                'email' => array(
                    'active' => $is_WIN ? false : true,
                    'level' => 400,
                    'to' => $cms_settings['server_email'],
                    'subject' => 'SyncData Alert'
                )
            ),
            'general' => array(
                'client_id' => $this->app['utils']->generatePassword(9, false, 'ld'),
                'memory_limit' => '256M',
                'max_execution_time' => '300',
                'time_zone' => 'Europe/Berlin',
                'debug' => false,
                'templates' => array(
                    'default'
                )
            ),
            'security' => array(
                'active' => true,
                'key' => self::$key
            ),
            'backup' => array(
                'settings' => array(
                    'replace_table_prefix' => true,
                    'add_if_not_exists' => true,
                    'replace_cms_url' => true
                ),
                'files' => array(
                    'ignore' => array(
                        '.buildpath',
                        '.project',
                        'desktop.ini'
                    )
                ),
                'directories' => array(
                    'ignore' => array(
                        'directory' => array(
                            'temp',
                            'syncdata',
                            'kit2',
                            'nbproject'
                        ),
                        'subdirectory' => array(
                            '.git'
                        )
                    )
                ),
                'tables' => array(
                    'ignore' => array(
                        'table' => array(
                            ),
                        'sub_prefix' => array(
                            'kit2_',
                            'syncdata_'
                            )
                    )
                )
            ),
            'restore' => array(
                'settings' => array(
                    'replace_table_prefix' => true,
                    'replace_cms_url' => true,
                    'ignore_cms_config' => true
                ),
                'files' => array(
                    'ignore' => array(
                        '.buildpath',
                        '.project',
                        'desktop.ini'
                    )
                ),
                'directories' => array(
                    'ignore' => array(
                        'directory' => array(
                            'temp',
                            'syncdata',
                            'kit2',
                            'nbproject'
                        ),
                        'subdirectory' => array(
                            '.git'
                        )
                    )
                ),
                'tables' => array(
                    'ignore' => array(
                        'table' => array(
                            ),
                        'sub_prefix' => array(
                            'kit2_',
                            'syncdata_'
                            )
                    )
                )
            )
        );
        // encode a formatted JSON file
        $jsonFormat = new JSONFormat();
        $json = $jsonFormat->format(self::$config_array);
        if (!@file_put_contents(self::$config_file, $json)) {
            throw new ConfigurationException("Can't write the configuration file for SyncData!");
        }
        $this->app['monolog']->addInfo("Create configuration file syncdata.json for SyncData",
            array('method' => __METHOD__, 'line' => __LINE__));
    }

    /**
     * Initialize the Doctrine configuration settings
     *
     * @throws ConfigurationException
     */
    public function initConfiguration()
    {
        if (!file_exists(self::$config_file)) {
            // get the configuration directly from CMS
            $this->getConfigurationFromCMS();
            // because the configuration file does not exist we also execute the setup!
            $Setup = new Setup($this->app);
            $Setup->exec();
            self::$executed_setup = true;
        }
        elseif (is_null(self::$config_array) &&
            (false === (self::$config_array = json_decode(@file_get_contents(self::$config_file), true))) ||
            !is_array(self::$config_array)) {
            throw new ConfigurationException("Can't read the SyncData configuration file!");
        }

        // set constants for important config values
        define('CMS_URL', self::$config_array['CMS']['CMS_URL']);
        define('CMS_PATH', self::$config_array['CMS']['CMS_PATH']);
        define('CMS_TYPE', self::$config_array['CMS']['CMS_TYPE']);
        define('CMS_ADMIN_PATH', self::$config_array['CMS']['CMS_ADMIN_PATH']);
        define('CMS_ADMIN_URL', self::$config_array['CMS']['CMS_ADMIN_URL']);
        define('CMS_VERSION', self::$config_array['CMS']['CMS_VERSION']);
        define('CMS_MEDIA_DIRECTORY', self::$config_array['CMS']['CMS_MEDIA_DIRECTORY']);
        define('CMS_PAGES_DIRECTORY', self::$config_array['CMS']['CMS_PAGES_DIRECTORY']);
        define('CMS_SERVER_EMAIL', self::$config_array['CMS']['CMS_SERVER_EMAIL']);
        define('CMS_SERVER_NAME', self::$config_array['CMS']['CMS_SERVER_NAME']);
        define('TEMP_PATH', SYNCDATA_PATH.'/temp');
        define('SYNCDATA_DEBUG', isset(self::$config_array['general']['debug']) ? self::$config_array['general']['debug'] : false);
        define('SYNCDATA_TEMPLATES', isset(self::$config_array['general']['templates']) ? implode(',', self::$config_array['general']['templates']) : 'default');
        if (!defined('INSTALLATION_NAME')) {
            define('INSTALLATION_NAME', self::$config_array['CMS']['INSTALLATION_NAME']);
        }

        if (false === ini_set('memory_limit', self::$config_array['general']['memory_limit'])) {
            throw new ConfigurationException(sprintf("Can't set the memory limit to %s", self::$config_array['general']['memory_limit']));
        }
        else {
            $this->app['monolog']->addInfo(sprintf("Set the memory limit to %s", self::$config_array['general']['memory_limit']),
                array('method' => __METHOD__, 'line' => __LINE__));
        }
        if (false === ini_set('max_execution_time', self::$config_array['general']['max_execution_time'])) {
            throw new ConfigurationException(sprintf("Can't set the max_execution_time to %s seconds", self::$config_array['general']['max_execution_time']));
        }
        else {
            $this->app['monolog']->addInfo(sprintf("Set the max_execution_time to %s seconds", self::$config_array['general']['max_execution_time']),
                array('method' => __METHOD__, 'line' => __LINE__));
        }

        $cfg = $this->getConfiguration();
        $this->app['config'] = $this->app->share(function() use ($cfg) {
            return $cfg;
        });
    }


}
