<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 *
 * ATTENTION - NO LONGER USE THE DROPLET [[confirmation_log]] !!!
 *
 * Please use the droplet [[syncdata_confirmation]] instead, this is a
 * compatibillity code which calls the new ConfirmationLog used by
 * SyncData
 */

global $app;

if (!file_exists(WB_PATH.'/syncdata/vendor/phpManufaktur/ConfirmationLog/bootstrap.droplet.php')) {
    return 'SyncData is not installed, therefore the Droplet `syncdata_confirmation` is out of function.';
}

require_once WB_PATH.'/syncdata/vendor/phpManufaktur/ConfirmationLog/bootstrap.droplet.php';

use phpManufaktur\ConfirmationLog\Control\Droplet\Confirmation;

$parameter = array(
    'email' => array(
        // support the old parameter `use_email` instead of the new `email`
        'active' => (isset($use_email) && (strtolower($use_email) == 'false')) ? false : true
    ),
    'name' => array(
        // support the old parameter `use_name` instead of the new `name`
        'active' => (isset($use_name) && (strtolower($use_name) == 'false')) ? false : true
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
