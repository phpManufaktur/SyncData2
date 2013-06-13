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
use phpManufaktur\SyncData\Control\CreateArchive;
use phpManufaktur\SyncData\Control\SynchronizeClient;

require_once __DIR__.'/vendor/SwiftMailer/lib/swift_required.php';

// set the error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

$script_start = microtime(true);

try {
    define('LOGGER_LEVEL', Logger::INFO);
    define('SYNC_DATA_PATH', __DIR__);

    // init the application
    $app = new Application();
    // check the logfile size
    $max_size = 5*1024*1024; // 5 MB
    $log_file = SYNC_DATA_PATH.'/logfile/syncdata.log';
    if (file_exists($log_file) && (filesize($log_file) > $max_size)) {
        // delete existing backup file
        @unlink(SYNC_DATA_PATH.'/logfile/syncdata.bak');
        // rename the logfile to *.bak
        @rename($log_file, SYNC_DATA_PATH.'/logfile/syncdata.bak');
    }
    elseif (!file_exists(SYNC_DATA_PATH.'/logfile') && (true !== @mkdir(SYNC_DATA_PATH.'/logfile'))) {
        throw new \Exception("Can not create the directory for the logfiles!");
    }

    // initialize the logger
    $app['monolog'] = $app->share(function($app) {
        return new Logger('SyncData');
    });
    $app['monolog']->pushHandler(new StreamHandler($log_file, LOGGER_LEVEL));
    $app['monolog']->addInfo('Monolog initialized');

    // initialize the utils
    $app['utils'] = $app->share(function() use($app) {
        return new Utils($app);
    });
    $app['monolog']->addInfo('SyncData Utils initialized');

    // check directories and create protection
    $check_directories = array('/config', '/temp', '/logfile', '/vendor');
    foreach ($check_directories as $directory) {
        if (!file_exists(SYNC_DATA_PATH.$directory.'/.htaccess') || !file_exists(SYNC_DATA_PATH.$directory.'/.htpasswd')) {
            $app['utils']->createDirectoryProtection(SYNC_DATA_PATH.$directory);
        }
    }

    // check if the /inbox and /outbox exists
    $check_directories = array('/inbox', '/outbox');
    foreach ($check_directories as $directory) {
        if (!file_exists(SYNC_DATA_PATH.$directory) && !@mkdir(SYNC_DATA_PATH.$directory)) {
            throw new \Exception("Can' create the directory ".SYNC_DATA_PATH.$directory);
        }
    }

    // initialize Doctrine
    $initDoctrine = new Doctrine($app);
    $initDoctrine->initDoctrine();

    // initialize the SyncData configuration
    $initConfig = new Configuration($app);
    $initConfig->initConfiguration();

    // initialize the SwiftMailer
    $initSwiftMailer = new SwiftMailer($app);
    $initSwiftMailer->initSwiftMailer();

    // configuration is finished
    $app['monolog']->addInfo('SyncData READY');

    // get the SyncDataServer directory
    $syncdata_directory = substr(SYNC_DATA_PATH, strrpos(SYNC_DATA_PATH, DIRECTORY_SEPARATOR)+1);
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
    $route = substr($_SERVER['REQUEST_URI'], strlen($syncdata_directory)+1,
        (false !== ($pos = strpos($_SERVER['REQUEST_URI'], '?'))) ? $pos-strlen($syncdata_directory)-1 : strlen($_SERVER['REQUEST_URI']));

    define('SYNC_DATA_URL', $app['config']['CMS']['CMS_URL'].DIRECTORY_SEPARATOR.$route);

    switch ($route) {
        case '/setup':
            $setup = new Setup($app);
            $result = $setup->exec();
            break;
        case '/update':
            $result = 'Update is not implemented';
            break;
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
        case '/create':
            $createArchive = new CreateArchive($app);
            $result = $createArchive->exec();
            break;
        case '/sync':
            $synchronizeClient = new SynchronizeClient($app);
            $result = $synchronizeClient->exec();
            break;
        case '/':
        default:
            $result = '- nothing to do -';
            break;
    }

    $execution_time = sprintf('Execution time: %s seconds (max: %s)', number_format(microtime(true) - $script_start, 2), $app['config']['general']['max_execution_time']);
    $app['monolog']->addInfo($execution_time);
    $peak_usage = sprintf('Memory peak usage: %s MB (Limit: %s)', memory_get_peak_usage(true)/(1024*1024), $app['config']['general']['memory_limit']);
    $app['monolog']->addInfo($peak_usage);

    // exit with result
    $exit = <<<EOD
        $execution_time<br />
        $peak_usage<br />
        <br />
        Executed: $result<br />
        <br />
        SyncData: Ready
EOD;
    exit($exit);
} catch (\Exception $e) {
    if ($app->offsetExists('monolog')) {
        $app['monolog']->addError(strip_tags($e->getMessage()), array('file' => $e->getFile(), 'line' => $e->getLine()));
    }
    exit($e->getMessage()."<br />Please check the logfile for further information!");
}
