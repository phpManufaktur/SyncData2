<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

include_once __DIR__.'/vendor/autoloader.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use phpManufaktur\SyncData\Control\Backup;
use phpManufaktur\SyncData\Control\Utils;
use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Control\Restore;
use phpManufaktur\SyncData\Control\Check;
use phpManufaktur\SyncData\Data\Configuration\Configuration;
use phpManufaktur\SyncData\Data\Configuration\Doctrine;
use phpManufaktur\SyncData\Data\Configuration\SwiftMailer;
use phpManufaktur\SyncData\Data\Setup\Setup;
use phpManufaktur\SyncData\Control\CreateSynchronizeArchive;
use phpManufaktur\SyncData\Control\SynchronizeClient;
use phpManufaktur\SyncData\Control\CheckKey;
use phpManufaktur\SyncData\Control\Template;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\MessageSelector;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use phpManufaktur\ConfirmationLog\Data\Setup\Setup as confirmationSetup;

require_once __DIR__.'/vendor/Twig/Autoloader.php';
\Twig_Autoloader::register();

require_once __DIR__.'/vendor/SwiftMailer/lib/swift_required.php';

// set the error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('SYNCDATA_SCRIPT_START', microtime(true));

try {

    // init the application
    $app = new Application();

    $app['utils'] = $app->share(function() use($app) {
        return new Utils($app);
    });

    define('SYNCDATA_PATH', $app['utils']->sanitizePath(__DIR__));
    define('MANUFAKTUR_PATH', SYNCDATA_PATH.'/vendor/phpManufaktur');

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

    // load the /SyncData language files
    $app['utils']->addLanguageFiles(SYNCDATA_PATH.'/vendor/phpManufaktur/SyncData/Data/Locale');
    // load the /SyncData/Basic language files
    $app['utils']->addLanguageFiles(SYNCDATA_PATH.'/vendor/phpManufaktur/SyncData/Data/Locale/Custom');

    // initialize the Twig template engine
    $app['twig'] = $app->share(function() use($app) {
        $loader = new \Twig_Loader_Filesystem(MANUFAKTUR_PATH);
        $twig = new \Twig_Environment($loader, array(
            'cache' => SYNCDATA_PATH.'/temp/cache',
        ));
        if (isset($app['translator'])) {
            $twig->addExtension(new TranslationExtension($app['translator']));
        }
        return $twig;
    });

    // initialize Doctrine
    $initDoctrine = new Doctrine($app);
    $initDoctrine->initDoctrine();

    // initialize the SyncData configuration
    $initConfig = new Configuration($app, $config_array);
    $initConfig->initConfiguration();

    // initialize the SwiftMailer
    $initSwiftMailer = new SwiftMailer($app);
    $initSwiftMailer->initSwiftMailer();

    // configuration is finished
    $app['monolog']->addInfo('SyncData READY');

    // get the SyncDataServer directory
    $syncdata_directory = dirname($_SERVER['SCRIPT_NAME']);

    if (!in_array($syncdata_directory, $app['config']['backup']['directories']['ignore']['directory'])) {
        // we must grant that the SyncDataServer /temp directory is always ignored (recursion!!!)
        $config = $app['config'];
        $config['backup']['directories']['ignore']['directory'][] = $syncdata_directory.'/temp';
        $app['config'] = $app->share(function($app) use ($config) {
            return $config;
        });
    }

    // got the route dynamically from the real directory where SyncData reside.
    // .htaccess RewriteBase must be equal to the SyncData directory!
    $route = substr($_SERVER['REQUEST_URI'], strlen($syncdata_directory),
        (false !== ($pos = strpos($_SERVER['REQUEST_URI'], '?'))) ? $pos-strlen($syncdata_directory) : strlen($_SERVER['REQUEST_URI']));

    define('SYNCDATA_ROUTE', $route);
    define('SYNCDATA_URL', substr($app['config']['CMS']['CMS_URL'].substr(SYNCDATA_PATH, strlen($app['config']['CMS']['CMS_PATH'])), 0));

    if ($initConfig->executedSetup() && $app['config']['security']['active']) {
        // if SyncData was initialized prompt a message!
        $initConfirmation = new confirmationSetup();
        $initConfirmation->exec($app);
        $route = '#init_syncdata';
    }
    $app_result = null;
    // init the KEY check class
    $CheckKey = new CheckKey($app);

    switch ($route) {
        case '/precheck.php':
        case '/info.php';
            // information for the CMS backward compatibility only
            $app_result = "This is not an WebsiteBaker or LEPTON CMS installation!";
            break;
        case '/phpinfo':
            // show phpinfo()
            phpinfo();
            break;
        case '/precheck':
        case '/systemcheck':
            // execute a systemcheck
            include SYNCDATA_PATH.'/systemcheck.php';
            exit();
        case '/setup':
            // force a setup
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $setup = new Setup($app);
            $app_result = $setup->exec();
            $initConfirmation = new confirmationSetup();
            $app_result = $initConfirmation->exec($app);
            break;
        case '/update':
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $app_result = 'Update is not implemented';
            break;
        case '/backup':
            // create a backup
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $backup = new Backup($app);
            $app_result = $backup->exec();
            break;
        case '/restore':
            // restore a backup to itself or to a client
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $restore = new Restore($app);
            $app_result = $restore->exec();
            break;
        case '/check':
            // check changes in the CMS but don't create an archive yet
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $check = new Check($app);
            $app_result = $check->exec();
            break;
        case '/create':
            // create the synchronize archive for the client
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $createArchive = new CreateSynchronizeArchive($app);
            $app_result = $createArchive->exec();
            break;
        case '/createsync':
            // check and create a synchronize archive
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            // check the system for changes
            $check = new Check($app);
            $check->exec();
            // create a archive for the client
            $createArchive = new CreateSynchronizeArchive($app);
            $app_result = $createArchive->exec();
            break;
        case '/sync':
            // synchronize the client with the server
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $synchronizeClient = new SynchronizeClient($app);
            $app_result = $synchronizeClient->exec();
            break;
        case '#init_syncdata':
            // initialized SyncData2
            $app_result = 'SyncData has successfull initialized and also created a security key: <span class="security_key">'.
                $app['config']['security']['key'].'</span><br />'.
                'Please remember this key, you will need it to execute some commands and to setup cronjobs.';
            if ($app['config']['email']['active']) {
                // send the key also with email
                $message = \Swift_Message::newInstance('SyncData: Key generated')
                ->setFrom(CMS_SERVER_EMAIL, CMS_SERVER_NAME)
                ->setTo(CMS_SERVER_EMAIL)
                ->setBody('SyncData has created a new key: '.$app['config']['security']['key']);
                $app['mailer']->send($message);
                $app_result .= '<br />SyncData has also send the key to '.CMS_SERVER_EMAIL;
            }
            break;
        case '/':
        default:
            $app_result = '- nothing to do -';
            break;
    }

    $execution_time = sprintf('Execution time: %s seconds (max: %s)', number_format(microtime(true) - SYNCDATA_SCRIPT_START, 2), $app['config']['general']['max_execution_time']);
    $app['monolog']->addInfo($execution_time);
    $peak_usage = sprintf('Memory peak usage: %s MB (Limit: %s)', memory_get_peak_usage(true)/(1024*1024), $app['config']['general']['memory_limit']);
    $app['monolog']->addInfo($peak_usage);

    $result = is_null($app_result) ? 'Ooops, unexpected result ...' : $app_result;
    $result = sprintf('<div class="result"><h1>Result</h1>%s</div>', $result);
    $Template = new Template();
    echo $Template->parse($app, $result);
} catch (\Exception $e) {
    if (!isset($route)) {
        // SyncData2 may be not complete initialized
        throw new \Exception($e);
    }
    // regular Exception handling
    if ($app->offsetExists('monolog')) {
        $app['monolog']->addError(strip_tags($e->getMessage()), array('file' => $e->getFile(), 'line' => $e->getLine()));
    }
    $Template = new Template();
    $error = sprintf('<div class="error"><h1>Oooops ...</h1><div class="message">%s</div><div class="logfile">Please check the logfile for further information!</div></div>',
        $e->getMessage());
    echo $Template->parse($app, $error);
}
