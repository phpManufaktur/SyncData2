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
use phpManufaktur\SyncData\Data\CMS\Settings;
use Monolog\Handler\SwiftMailerHandler;

require_once SYNCDATA_PATH.'/vendor/SwiftMailer/lib/swift_required.php';

/**
 * Get the configuration for the mailer from the parent CMS and create the
 * configuration file swiftmailer.json
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class SwiftMailer
{
    protected $app = null;
    protected static $config_file = null;
    protected static $config_array = null;

    /**
     * Constructor
     *
     * @param Application $app
     * @throws ConfigurationException
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        self::$config_file = SYNCDATA_PATH.'/config/swiftmailer.json';
        if (!$this->app->offsetExists('monolog')) {
            // missing the logging!
            throw new ConfigurationException("Monolog is not available!");
        }
        $this->initConfiguration();
    }

    /**
     * Return the configuration array for the SwiftMailer
     *
     * @return array configuration array
     */
    public function getConfiguration()
    {
        return self::$config_array;
    }

    /**
     * Get the configuration information from the parent CMS
     *
     * @throws ConfigurationException
     */
    protected function getConfigurationFromCMS()
    {
        $cmsSettings = new Settings($this->app);
        $cms_settings = $cmsSettings->getSettings();
        self::$config_array = array(
            'SMTP_HOST' => (isset($cms_settings['wbmailer_smtp_host']) && !empty($cms_settings['wbmailer_smtp_host'])) ? $cms_settings['wbmailer_smtp_host'] : 'localhost',
            'SMTP_PORT' => 25,
            'SMTP_AUTH' => (isset($cms_settings['wbmailer_smtp_auth'])) ? (bool) $cms_settings['wbmailer_smtp_auth'] : false,
            'SMTP_USERNAME' => (isset($cms_settings['wbmailer_smtp_username'])) ? $cms_settings['wbmailer_smtp_username'] : '',
            'SMTP_PASSWORD' => (isset($cms_settings['wbmailer_smtp_password'])) ? $cms_settings['wbmailer_smtp_password'] : '',
            'SMTP_SECURITY' => ''
        );
        // encode a formatted JSON file
        $jsonFormat = new JSONFormat();
        $json = $jsonFormat->format(self::$config_array);
        if (!@file_put_contents(self::$config_file, $json)) {
            throw new ConfigurationException("Can't write the configuration file for SwiftMailer!");
        }
        $this->app['monolog']->addInfo('Create /config/swiftmailer.json', array('method' => __METHOD__, 'line' => __LINE__));
    }

    /**
     * Initialize the SwiftMailer configuration settings
     *
     * @throws ConfigurationException
     */
    protected function initConfiguration()
    {
        if (!file_exists(self::$config_file)) {
            // get the configuration directly from CMS
            $this->app['monolog']->addInfo('SwiftMailer configuration does not exists',
                array('method' => __METHOD__, 'line' => __LINE__));
            $this->getConfigurationFromCMS();
        }
        elseif ((false === (self::$config_array = json_decode(@file_get_contents(self::$config_file), true))) || !is_array(self::$config_array)) {
            throw new ConfigurationException("Can't read the SwiftMailer configuration file!");
        }
    }

    /**
     * Initialize the Swiftmailer for SMTP and create a default message for
     * the Monolog Swiftmailer handler
     *
     * @return Ambigous <Swift_Mailer, Swift_Mailer>
     */
    public function initSwiftMailer()
    {
        $security = !empty(self::$config_array['SMTP_SECURITY']) ? self::$config_array['SMTP_SECURITY'] : null;
        if (self::$config_array['SMTP_AUTH']) {
            $transport = \Swift_SmtpTransport::newInstance(self::$config_array['SMTP_HOST'], self::$config_array['SMTP_PORT'], $security)
            ->setUsername(self::$config_array['SMTP_USERNAME'])
            ->setPassword(self::$config_array['SMTP_PASSWORD']);
            $this->app['monolog']->addInfo('SwiftMailer transport with SMTP authentication initialized',
                array('method' => __METHOD__, 'line' => __LINE__));
        }
        else {
            $transport = \Swift_SmtpTransport::newInstance(self::$config_array['SMTP_HOST'], self::$config_array['SMTP_PORT']);
            $this->app['monolog']->addInfo('SwiftMailer transport without SMTP authentication initialized',
                array('method' => __METHOD__, 'line' => __LINE__));
        }

        $this->app['mailer'] = $this->app->share(function() use ($transport) {
            return \Swift_Mailer::newInstance($transport);
        });
        $this->app['monolog']->addInfo('SwiftMailer initialized',
            array('method' => __METHOD__, 'line' => __LINE__));

        if ($this->app['config']['monolog']['email']['active']) {
            // push handler for SwiftMail to Monolog to prompt errors
            $message = \Swift_Message::newInstance($this->app['config']['monolog']['email']['subject'])
            ->setFrom(CMS_SERVER_EMAIL, CMS_SERVER_NAME)
            ->setTo($this->app['config']['monolog']['email']['to'])
            ->setBody('SyncData errror');
            // 400 = Logger::ERROR
            $level = isset($this->app['config']['monolog']['email']['level']) ? $this->app['config']['monolog']['email']['level'] : 400;
            $this->app['monolog']->pushHandler(new SwiftMailerHandler($this->app['mailer'], $message, $level));
            $this->app['monolog']->addInfo('Monolog handler for SwiftMailer initialized',
                array('method' => __METHOD__, 'line' => __LINE__));
        }
    }

}
