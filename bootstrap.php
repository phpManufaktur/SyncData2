<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

include_once __DIR__.'/vendor/autoloader.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use phpManufaktur\SyncData\Control\Backup;
use phpManufaktur\SyncData\Control\Utils;
use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Data\CMS\Settings;
use phpManufaktur\SyncData\Control\JSON\JSONFormat;
use phpManufaktur\SyncData\Control\Restore;
use phpManufaktur\SyncData\Control\Check;

require_once __DIR__.'/vendor/SwiftMailer/lib/swift_required.php';

// set the error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

$script_start = microtime(true);

try {
    define('LOGGER_LEVEL', Logger::INFO);
    define('SYNC_DATA_PATH', __DIR__);

    // check the logfile size
    $max_size = 2*1024*1024; // 2 MB
    $log_file = SYNC_DATA_PATH.'/logfile/SyncDataServer.log';
    if (file_exists($log_file) && (filesize($log_file) > $max_size)) {
        // delete existing backup file
        @unlink(SYNC_DATA_PATH.'/logfile/SyncDataServer.bak');
        // rename the logfile to *.bak
        @rename($log_file, SYNC_DATA_PATH.'/logfile/SyncDataServer.bak');
    }

    // init the application
    $app = new Application();

    // initialize the logger
    $app['monolog'] = $app->share(function($app) {
        return new Logger('SyncDataServer');
    });
    $app['monolog']->pushHandler(new StreamHandler($log_file, LOGGER_LEVEL));
    $app['monolog']->addInfo('Monolog initialized');

    // initialize the utils
    $app['utils'] = $app->share(function() use($app) {
        return new Utils($app);
    });
    $app['monolog']->addInfo('SyncDataServer Utils initialized');

    // check config directory
    if (!file_exists(SYNC_DATA_PATH.'/config')) {
        if (true !== @mkdir(SYNC_DATA_PATH.'/config')) {
            throw new \Exception("Can not create the directory for the config files!");
        }
        $app['monolog']->addInfo('Create the directory for the config files');
    }

    // check the config protection
    if (!file_exists(SYNC_DATA_PATH.'/config/.htaccess') || !file_exists(SYNC_DATA_PATH.'/config/.htpasswd')) {
        $app['utils']->createDirectoryProtection(SYNC_DATA_PATH.'/config');
    }
    // check the temp protection
    if (!file_exists(SYNC_DATA_PATH.'/temp/.htaccess') || !file_exists(SYNC_DATA_PATH.'/temp/.htpasswd')) {
        $app['utils']->createDirectoryProtection(SYNC_DATA_PATH.'/temp');
    }
    // check the logfile protection
    if (!file_exists(SYNC_DATA_PATH.'/logfile/.htaccess') || !file_exists(SYNC_DATA_PATH.'/logfile/.htpasswd')) {
        $app['utils']->createDirectoryProtection(SYNC_DATA_PATH.'/logfile');
    }
    // check the vendor protection
    if (!file_exists(SYNC_DATA_PATH.'/vendor/.htaccess') || !file_exists(SYNC_DATA_PATH.'/vendor/.htpasswd')) {
        $app['utils']->createDirectoryProtection(SYNC_DATA_PATH.'/vendor');
    }

    // check the Doctrine configuration
    if (!file_exists(SYNC_DATA_PATH.'/config/doctrine.json')) {
        // try to get the configuration from LEPTON/WebsiteBaker
        $app['monolog']->addInfo('/config/doctrine.json does not exists');
        if (!defined('WB_PATH')) {
            $app['monolog']->addInfo('Search for CMS config.php');
            if (file_exists(SYNC_DATA_PATH.'/../config.php')) {
                include_once SYNC_DATA_PATH.'/../config.php';
                $doctrine = array(
                    'DB_TYPE' => DB_TYPE,
                    'DB_HOST' => DB_HOST,
                    'DB_PORT' => DB_PORT,
                    'DB_USERNAME' => DB_USERNAME,
                    'DB_PASSWORD' => DB_PASSWORD,
                    'DB_NAME' => DB_NAME,
                    'TABLE_PREFIX' => TABLE_PREFIX
                );
                $app['monolog']->addInfo('Read the database configuration from the CMS config.php');
                // encode a formatted JSON file
                $jsonFormat = new JSONFormat();
                $json = $jsonFormat->format($doctrine);
                if (!@file_put_contents(SYNC_DATA_PATH.'/config/doctrine.json', $json)) {
                    throw new \Exception("Can't write the configuration file for Doctrine!");
                }
            }
            else {
                throw new \Exception("Can't read the CMS configuration, SyncDataServer stopped.");
            }
        }
    }

    // get the doctrine configuration
    if ((false === ($doctrine = json_decode(@file_get_contents(SYNC_DATA_PATH.'/config/doctrine.json'), true))) || !is_array($doctrine)) {
        throw new \Exception("Can't read the Doctrine configuration file!");
    }

    // initialize Doctrine
    $config = new \Doctrine\DBAL\Configuration();
    $connectionParams = array(
        'dbname' => $doctrine['DB_NAME'],
        'user' => $doctrine['DB_USERNAME'],
        'password' => $doctrine['DB_PASSWORD'],
        'host' => $doctrine['DB_HOST'],
        'port' => $doctrine['DB_PORT'],
        'driver' => 'pdo_mysql',
    );
    define('CMS_TABLE_PREFIX', $doctrine['TABLE_PREFIX']);

    $app['db'] = $app->share(function($app) use($connectionParams, $config) {
        return \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
    });
    $app['monolog']->addInfo("Doctrine initialized");

    if (!file_exists(SYNC_DATA_PATH.'/config/swiftmailer.json')) {
        $app['monolog']->addInfo('/config/swiftmailer.json does not exists!');
        $cmsSettings = new Settings($app);
        $cms_settings = $cmsSettings->getSettings();
        $swiftmailer = array(
            'SMTP_HOST' => (isset($cms_settings['wbmailer_smtp_host']) && !empty($cms_settings['wbmailer_smtp_host'])) ? $cms_settings['wbmailer_smtp_host'] : 'localhost',
            'SMTP_PORT' => 25,
            'SMTP_AUTH' => (isset($cms_settings['wbmailer_smtp_auth'])) ? (bool) $cms_settings['wbmailer_smtp_auth'] : false,
            'SMTP_USERNAME' => (isset($cms_settings['wbmailer_smtp_username'])) ? $cms_settings['wbmailer_smtp_username'] : '',
            'SMTP_PASSWORD' => (isset($cms_settings['wbmailer_smtp_password'])) ? $cms_settings['wbmailer_smtp_password'] : '',
            'SMTP_SECURITY' => ''
        );
        // encode a formatted JSON file
        $jsonFormat = new JSONFormat();
        $json = $jsonFormat->format($swiftmailer);
        if (!@file_put_contents(SYNC_DATA_PATH.'/config/swiftmailer.json', $json)) {
            throw new \Exception("Can\'t write the configuration file for SwiftMailer!");
        }
        $app['monolog']->addInfo('Create /config/swiftmailer.json');
    }

    if (!file_exists(SYNC_DATA_PATH.'/config/syncdata.json')) {
        $app['monolog']->addInfo('/config/syncdata.json does not exists!');
        $cmsSettings = new Settings($app);
        $cms_settings = $cmsSettings->getSettings();
        if (file_exists(SYNC_DATA_PATH.'/../config.php')) {
            include_once SYNC_DATA_PATH.'/../config.php';
        }
        else {
            throw new \Exception("Can't read the CMS configuration, SyncDataServer stopped.");
        }
        $config = array(
            'CMS' => array(
                'CMS_SERVER_EMAIL' => $cms_settings['server_email'],
                'CMS_SERVER_NAME' => $cms_settings['wbmailer_default_sendername'],
                'CMS_TYPE' => (isset($cms_settings['lepton_version'])) ? 'LEPTON' : 'WebsiteBaker',
                'CMS_VERSION' => (isset($cms_settings['lepton_version'])) ? $cms_settings['lepton_version'] : $cms_settings['wb_version'],
                'CMS_MEDIA_DIRECTORY' => $cms_settings['media_directory'],
                'CMS_PAGES_DIRECTORY' => $cms_settings['pages_directory'],
                'CMS_URL' => WB_URL,
                'CMS_PATH' => WB_PATH
                ),
            'monolog' => array(
                'email' => array(
                    'active' => true,
                    'level' => 400,
                    'to' => $cms_settings['server_email'],
                    'subject' => 'SyncDataServer Alert'
                )
            ),
            'general' => array(
                'memory_limit' => '512M',
                'max_execution_time' => '300'
            ),
            'syncdata' => array(
                'server' => array(
                    'backup' => array(
                        'settings' => array(
                            'replace_table_prefix' => true,
                            'add_if_not_exists' => true,
                            'replace_cms_url' => true
                        ),
                        'files' => array(
                            'ignore' => array(
                                '.buildpath',
                                '.project'
                            )
                        ),
                        'directories' => array(
                            'ignore' => array(
                                'directory' => array(
                                    'temp',
                                    'SyncDataServer',
                                    'SyncDataClient',
                                    'kit2'
                                ),
                                'subdirectory' => array(
                                    '.git'
                                )
                            )
                        ),
                        'tables' => array(
                            'ignore' => array(
                                'syncdata_backup_master'
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
                                '.project'
                            )
                        ),
                        'directories' => array(
                            'ignore' => array(
                                'directory' => array(
                                    'temp'
                                ),
                                'subdirectory' => array(
                                    '.git'
                                )
                            )
                        ),
                        'tables' => array(
                            'ignore' => array(
                                'syncdata_backup_master'
                            )
                        )
                    )
                )
            )
        );
        // encode a formatted JSON file
        $jsonFormat = new JSONFormat();
        $json = $jsonFormat->format($config);
        if (!@file_put_contents(SYNC_DATA_PATH.'/config/syncdata.json', $json)) {
            throw new \Exception("Can\'t write the configuration file for SyncData!");
        }
        $app['monolog']->addInfo('Create /config/syncdata.json');
    }

    // get the SwiftMailer configuration
    if ((false === ($swiftmailer = json_decode(@file_get_contents(SYNC_DATA_PATH.'/config/swiftmailer.json'), true))) || !is_array($swiftmailer)) {
        throw new \Exception("Can't read the SwiftMailer configuration file!");
    }

    $security = !empty($swiftmailer['SMTP_SECURITY']) ? $swiftmailer['SMTP_SECURITY'] : null;
    if ($swiftmailer['SMTP_AUTH']) {
        $transport = Swift_SmtpTransport::newInstance($swiftmailer['SMTP_HOST'], $swiftmailer['SMTP_PORT'], $security)
        ->setUsername($swiftmailer['SMTP_USERNAME'])
        ->setPassword($swiftmailer['SMTP_PASSWORD']);
        $app['monolog']->addInfo('SwiftMailer transport with SMTP authentication initialized');
    }
    else {
        $transport = Swift_SmtpTransport::newInstance($swiftmailer['SMTP_HOST'], $swiftmailer['SMTP_PORT']);
        $app['monolog']->addInfo('SwiftMailer transport without SMTP authentication initialized');
    }

    $app['mailer'] = $app->share(function($app) use ($transport) {
        return Swift_Mailer::newInstance($transport);
    });
    $app['monolog']->addInfo('SwiftMailer initialized');

    // get the SyncData configuration
    if ((false === ($config = json_decode(@file_get_contents(SYNC_DATA_PATH.'/config/syncdata.json'), true))) || !is_array($config)) {
        throw new \Exception("Can't read the SyncData configuration file!");
    }
    $app['config'] = $app->share(function($app) use ($config) {
        return $config;
    });
    $app['monolog']->addInfo('Read /config/syncdata.json');

    // set constants for important config values
    define('SYNC_DATA_URL', $app['config']['CMS']['CMS_URL'].'/SyncDataServer');
    define('CMS_URL', $app['config']['CMS']['CMS_URL']);
    define('CMS_PATH', $app['config']['CMS']['CMS_PATH']);
    define('CMS_TYPE', $app['config']['CMS']['CMS_TYPE']);
    define('CMS_VERSION', $app['config']['CMS']['CMS_VERSION']);
    define('CMS_MEDIA_DIRECTORY', $app['config']['CMS']['CMS_MEDIA_DIRECTORY']);
    define('CMS_PAGES_DIRECTORY', $app['config']['CMS']['CMS_PAGES_DIRECTORY']);
    define('CMS_SERVER_EMAIL', $app['config']['CMS']['CMS_SERVER_EMAIL']);
    define('CMS_SERVER_NAME', $app['config']['CMS']['CMS_SERVER_NAME']);
    define('TEMP_PATH', SYNC_DATA_PATH.'/temp');

    if (false === ini_set('memory_limit', $app['config']['general']['memory_limit'])) {
        throw new \Exception(sprintf("Can't set the memory limit to %s", $app['config']['general']['memory_limit']));
    }
    else {
        $app['monolog']->addInfo(sprintf("Set the memory limit to %s", $app['config']['general']['memory_limit']));
    }
    if (false === ini_set('max_execution_time', $app['config']['general']['max_execution_time'])) {
        throw new \Exception(sprintf("Can't set the max_execution_time to %s seconds", $app['config']['general']['max_execution_time']));
    }
    else {
        $app['monolog']->addInfo(sprintf("Set the max_execution_time to %s seconds", $app['config']['general']['max_execution_time']));
    }

    if ($app['config']['monolog']['email']['active']) {
        // push handler for SwiftMail to Monolog to prompt errors
        $message = Swift_Message::newInstance($app['config']['monolog']['email']['subject'])
            ->setFrom(CMS_SERVER_EMAIL, CMS_SERVER_NAME)
            ->setTo($app['config']['monolog']['email']['to'])
            ->setBody('SyncDataServer errror');
        $app['monolog']->pushHandler(new SwiftMailerHandler($app['mailer'], $message, LOGGER::ERROR));
        $app['monolog']->addInfo('Monolog handler for SwiftMailer initialized');
    }

    // check if the /inbox and /outbox exists
    if (!file_exists(SYNC_DATA_PATH.'/inbox')) {
        if (!@mkdir(SYNC_DATA_PATH.'/inbox')) {
            throw new \Exception("Can' create the directory ".SYNC_DATA_PATH.'/inbox');
        }
    }
    if (!file_exists(SYNC_DATA_PATH.'/outbox')) {
        if (!@mkdir(SYNC_DATA_PATH.'/outbox')) {
            throw new \Exception("Can' create the directory ".SYNC_DATA_PATH.'/outbox');
        }
    }

    // configuration is finished
    $app['monolog']->addInfo('SyncDataServer READY');

    // get the SyncDataServer directory
    $syncdata_directory = substr(SYNC_DATA_PATH, strrpos(SYNC_DATA_PATH, DIRECTORY_SEPARATOR)+1);
    if (!in_array($syncdata_directory, $app['config']['syncdata']['server']['backup']['directories']['ignore']['directory'])) {
        // we must grant that the SyncDataServer /temp directory is always ignored (recursion!!!)
        $config = $app['config'];
        $config['syncdata']['server']['backup']['directories']['ignore']['directory'][] = $syncdata_directory.'/temp';
        $app['config'] = $app->share(function($app) use ($config) {
            return $config;
        });
    }

    // got the route dynamically from the real directory where the SyncDataServer reside.
    // .htaccess RewriteBase must be equal to the SyncDataServer directory!
    $route = substr($_SERVER['REQUEST_URI'], strlen($syncdata_directory)+1);

    switch ($route) {
        case '/backup':
            $backup = new Backup($app);
            $result = $backup->exec();
            break;
        case '/restore':
            $restore = new Restore($app);
            $result = $restore->exec();
            break;
        case '/check':
            $check = new Check($app);
            $result = $check->exec();
            break;
        default:
            $result = 'SyncDataServer: Ready.';
            break;
    }

    $script_stop = microtime(true);
    $script_time = (number_format($script_stop - $script_start, 2));
    echo "Execution time: $script_time seconds (max: ".$app['config']['general']['max_execution_time'].").</br>";
    echo "Memory usage: ".(memory_get_usage(true)/(1024*1024))." MB (Limit: ".$app['config']['general']['memory_limit'].")</br>";
    // exit with result
    exit($result);

} catch (\Exception $e) {
    $app['monolog']->addError(strip_tags($e->getMessage()), array('file' => $e->getFile(), 'line' => $e->getLine()));
    exit($e->getMessage()."<br />Please check the logfile for further information!");
}
