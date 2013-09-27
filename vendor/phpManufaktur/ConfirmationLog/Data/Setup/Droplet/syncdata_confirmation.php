<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

global $app;

require_once WB_PATH.'/syncdata/vendor/phpManufaktur/ConfirmationLog/bootstrap.droplet.php';

use phpManufaktur\ConfirmationLog\Control\Droplet\Confirmation;

$parameter = array(
    'email' => array(
        'active' => (isset($email) && (strtolower($email) == 'false')) ? false : true
    ),
    'name' => array(
        'active' => (isset($name) && (strtolower($name) == 'false')) ? false : true
    ),
    'confirm' => array(
        'active' => (isset($confirm) && (strtolower($confirm) == 'false')) ? false : true
    ),
    'css' => array(
        'active' => (isset($css) && (strtolower($css) == 'false')) ? false : true
    )
);

$Confirmation = new Confirmation();
return $Confirmation->exec($app, $parameter);
