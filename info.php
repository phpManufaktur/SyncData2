<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 *
 * ATTENTION: This is no WebsiteBaker or LEPTON CMS Add-on!
 *
 * The files info.php and precheck.php exists only to give the user
 * a hint if he try to install SyncData as a Add-on.
 */

if (file_exists(__DIR__.'/config/syncdata.json')) {
    // if the config file exists SyncData is installed regular
    include __DIR__.'/bootstrap.php';
}
else {
    // assume: user try to install SyncData as a add-on, so give the CMS some information
    $module_directory     = 'sync_data';
    $module_name          = 'SyncData';
    $module_function      = 'tool';
    $module_version       = '2.00';
    $module_status        = 'Stable';
    $module_platform      = '2.8';
    $module_author        = 'Team phpManufaktur <team@phpmanufaktur.de>';
    $module_license       = 'MIT License (MIT)';
    $module_description   = 'Save, restore and synchronize WebsiteBaker and LEPTON CMS installations';
    $module_home          = 'https://addons.phpmanufaktur.de/syncdata';
    $module_guid          = '88188907-B927-432A-AF7C-311FDD91F749';
}
