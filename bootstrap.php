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

use phpManufaktur\SyncData\Control\Backup;
use phpManufaktur\SyncData\Control\Utils;
use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Control\Restore;
use phpManufaktur\SyncData\Control\Check;
use phpManufaktur\SyncData\Data\Setup\Setup;
use phpManufaktur\SyncData\Control\CreateSynchronizeArchive;
use phpManufaktur\SyncData\Control\SynchronizeClient;
use phpManufaktur\SyncData\Control\CheckKey;
use phpManufaktur\SyncData\Control\Template;
use phpManufaktur\ConfirmationLog\Data\Setup\Setup as confirmationSetup;
use phpManufaktur\ConfirmationLog\Data\Setup\Update as confirmationUpdate;
use phpManufaktur\ConfirmationLog\Data\Setup\Uninstall as confirmationUninstall;
use phpManufaktur\ConfirmationLog\Data\Setup\SetupTool;
use phpManufaktur\ConfirmationLog\Data\Import\ImportOldLog;
use phpManufaktur\SyncData\Control\Confirmations;
use phpManufaktur\SyncData\Data\Setup\Uninstall;

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

    include SYNCDATA_PATH.'/bootstrap.inc';

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
            $ConfirmationUpdate = new confirmationUpdate();
            $app_result = $ConfirmationUpdate->exec($app);
            break;
        case '/uninstall':
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $ConfirmationUninstall = new confirmationUninstall();
            $app_result = $ConfirmationUninstall->exec($app);
            $Uninstall = new Uninstall($app);
            $app_result = $Uninstall->exec();
            break;
        case '/import_log':
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $ImportLog = new ImportOldLog();
            $app_result = $ImportLog->exec($app);
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
        case '/update_tool':
        case '/setup_tool':
            // install the admin-tool for the ConfirmationLog
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $SetupTool = new SetupTool();
            $app_result = $SetupTool->exec($app);
            break;
        case '/send_confirmations':
            // send confirmations to the outbox
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $Confirmations = new Confirmations($app);
            $app_result = $Confirmations->sendConfirmations();
            break;
        case '/get_confirmations':
            // send confirmations to the outbox
            if (!$CheckKey->check()) {
                $app_result = $CheckKey->getKeyHint();
                break;
            }
            $Confirmations = new Confirmations($app);
            $app_result = $Confirmations->getConfirmations();
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
