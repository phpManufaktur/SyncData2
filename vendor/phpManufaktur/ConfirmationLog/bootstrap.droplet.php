<?php

if (!defined('WB_PATH')) {
    trigger_error('This script can only executed within the CMS environment!', E_USER_ERROR);
}

include_once __DIR__.'/../../autoloader.php';

use phpManufaktur\SyncData\Control\Utils;
use phpManufaktur\SyncData\Control\Application;

// set the error handling
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // init the application
    $app = new Application();

    $app['utils'] = $app->share(function() use($app) {
        return new Utils($app);
    });

    define('SYNCDATA_PATH', $app['utils']->sanitizePath(WB_PATH.'/syncdata'));  //__DIR__.'/../../..'));
    define('SYNCDATA_URL', WB_URL.'/syncdata');
    define('MANUFAKTUR_PATH', SYNCDATA_PATH.'/vendor/phpManufaktur');
    define('MANUFAKTUR_URL', SYNCDATA_URL.'/vendor/phpManufaktur');

    include_once SYNCDATA_PATH.'/bootstrap.inc';
}
catch (\Exception $e) {
    throw $e;
}


