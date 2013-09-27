<?php

if (!defined('WB_PATH')) {
    trigger_error('This script can only executed within the CMS environment!', E_USER_ERROR);
}

include_once __DIR__.'/../../autoloader.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use phpManufaktur\SyncData\Control\Utils;
use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Data\Configuration\Configuration;
use phpManufaktur\SyncData\Data\Configuration\Doctrine;
use phpManufaktur\SyncData\Data\Configuration\SwiftMailer;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;

require_once __DIR__.'/../../Twig/Autoloader.php';
\Twig_Autoloader::register();

require_once __DIR__.'/../../SwiftMailer/lib/swift_required.php';

// set the error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // init the application
    $app = new Application();

    $app['debug'] = true;

    $app['utils'] = $app->share(function() use($app) {
        return new Utils($app);
    });

    define('SYNCDATA_PATH', $app['utils']->sanitizePath(WB_PATH.'/syncdata'));  //__DIR__.'/../../..'));
    define('SYNCDATA_URL', WB_URL.'/syncdata');
    define('MANUFAKTUR_PATH', SYNCDATA_PATH.'/vendor/phpManufaktur');
    define('MANUFAKTUR_URL', SYNCDATA_URL.'/vendor/phpManufaktur');

    // set the default time zone
    if (file_exists(SYNCDATA_PATH.'/config/syncdata.json') &&
    (false !== ($config_array = json_decode(@file_get_contents(SYNCDATA_PATH.'/config/syncdata.json'), true))) &&
    is_array($config_array) && isset($config_array['general']['time_zone'])) {
        // set the default timezone from the syncdata.json
        date_default_timezone_set($config_array['general']['time_zone']);
    }
    else {
        // syncdata.json does not exists, set 'Europe/Berlin' as default
        $config_array = null;
        date_default_timezone_set('Europe/Berlin');
    }

    // set monolog logging level
    define('LOGGER_LEVEL', isset($config_array['monolog']['level']) ? $config_array['monolog']['level'] : Logger::INFO);

    // check the logfile size
    $max_size = 5*1024*1024; // 5 MB
    $log_file = SYNCDATA_PATH.'/logfile/syncdata.log';
    if (file_exists($log_file) && (filesize($log_file) > $max_size)) {
        // delete existing backup file
        @unlink(SYNCDATA_PATH.'/logfile/syncdata.bak');
        // rename the logfile to *.bak
        @rename($log_file, SYNCDATA_PATH.'/logfile/syncdata.bak');
    }
    elseif (!file_exists(SYNCDATA_PATH.'/logfile') && (true !== @mkdir(SYNCDATA_PATH.'/logfile'))) {
        throw new \Exception("Can not create the directory for the logfiles!");
    }

    // initialize the logger
    $app['monolog'] = $app->share(function($app) {
        return new Logger('SyncData');
    });
    $app['monolog']->pushHandler(new StreamHandler($log_file, LOGGER_LEVEL));
    $app['monolog']->addInfo('Monolog initialized');
    $app['monolog']->addInfo('SyncData will be initialized over `bootstrap.droplet.php`.', array(__FILE__, __LINE__));


    // get the version number
    $syncdata_version = (file_exists(SYNCDATA_PATH.'/VERSION') && (false !== ($ver = file_get_contents(SYNCDATA_PATH.'/VERSION')))) ? $ver : '0.0.0';
    define('SYNCDATA_VERSION', $syncdata_version);

    // check directories and create protection
    $check_directories = array('/config', '/temp', '/temp/cache', '/logfile', '/vendor');
    foreach ($check_directories as $directory) {
        if (!file_exists(SYNCDATA_PATH.$directory.'/.htaccess') || !file_exists(SYNCDATA_PATH.$directory.'/.htpasswd')) {
            $app['utils']->createDirectoryProtection(SYNCDATA_PATH.$directory);
        }
    }

    // check if the /inbox and /outbox exists
    $check_directories = array('/inbox', '/outbox');
    foreach ($check_directories as $directory) {
        if (!file_exists(SYNCDATA_PATH.$directory) && !@mkdir(SYNCDATA_PATH.$directory)) {
            throw new \Exception("Can' create the directory ".SYNCDATA_PATH.$directory);
        }
    }

    // initialize the translation service
    $app['translator'] = $app->share(function() {
        // default language
        $locale = 'en';
        // quick and dirty ... try to detect the favorised language - to be improved!
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = array();
            // break up string into pieces (languages and q factors)
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);
            if (count($lang_parse[1]) > 0) {
                foreach ($lang_parse[1] as $lang) {
                    if (false === (strpos($lang, '-'))) {
                        // only the country sign like 'de'
                        $locale = strtolower($lang);
                    } else {
                        // perhaps something like 'de-DE'
                        $locale = strtolower(substr($lang, 0, strpos($lang, '-')));
                    }
                    break;
                }
            }
        }
        $translator = new Translator($locale, new MessageSelector());
        $translator->setFallbackLocale('en');
        $translator->addLoader('array', new ArrayLoader());
        return $translator;
    });
    $app['monolog']->addInfo('Initialized the translator service', array(__FILE__, __LINE__));

    // load the /SyncData language files
    $app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale');
    // load the /SyncData/Basic language files
    $app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale/Custom');

    // initialize the Twig template engine
    $app['twig'] = $app->share(function() use($app) {
        $loader = new Twig_Loader_Filesystem(MANUFAKTUR_PATH);
        $twig = new Twig_Environment($loader, array(
            'cache' => $app['debug'] ? false : SYNCDATA_PATH.'/temp/cache',
            'strict_variables' => $app['debug'] ? true : false,
            'debug' => $app['debug'] ? true : false,
            'autoescape' => false
        ));
        if (isset($app['translator'])) {
            $twig->addExtension(new TranslationExtension($app['translator']));
        }
        return $twig;
    });
    $app['monolog']->addInfo('Initialized the Twig Template engine', array(__FILE__, __LINE__));

    // initialize Doctrine
    $initDoctrine = new Doctrine($app);
    $initDoctrine->initDoctrine();

    // initialize the SyncData configuration
    $initConfig = new Configuration($app, $config_array);
    $initConfig->initConfiguration();

    // initialize the SwiftMailer
    $initSwiftMailer = new SwiftMailer($app);
    $initSwiftMailer->initSwiftMailer();

    $app['monolog']->addInfo('SyncData is initialized');
}
catch (\Exception $e) {
    throw $e;
}


