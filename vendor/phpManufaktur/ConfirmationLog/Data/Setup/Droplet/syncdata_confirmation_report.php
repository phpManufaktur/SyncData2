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

if (!file_exists(WB_PATH.'/syncdata/vendor/phpManufaktur/ConfirmationLog/bootstrap.droplet.php')) {
    return 'SyncData is not installed, therefore the Droplet `syncdata_confirmation` is out of function.';
}

require_once WB_PATH.'/syncdata/vendor/phpManufaktur/ConfirmationLog/bootstrap.droplet.php';

use phpManufaktur\ConfirmationLog\Control\Droplet\Report;

$parameter = array(
    'filter' => isset($filter) ? strtolower($filter) : 'persons',
    'group' => isset($group) ? strtolower($group) : '',
    'group_by' => isset($group_by) ? strtolower($group_by) : 'title',
    'identifier' => isset($identifier) ? strtoupper($identifier) : 'USERNAME'
);

$Report = new Report();
return $Report->exec($app, $parameter);
