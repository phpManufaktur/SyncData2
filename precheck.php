<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 *
 * ATTENTION: This is not a installable WebsiteBaker or LEPTON CMS Add-on!
 *
 * The files info.php and precheck.php exists only to give the user
 * a hint for the setup if he try to install SyncData as a Add-on.
 */

if (file_exists(dirname(__FILE__).'/config/syncdata.json')) {
    // if the config file exists SyncData is installed regular, just ignore this file!
    include dirname(__FILE__).'/bootstrap.php';
}
else {
    // assume: user try to install SyncData as a add-on, so give the CMS some information
    $response = <<<EOD
    <div style="margin:5px;border:1px solid #da251d;padding:10px;color:#000;background-color:#ffffe0;">
        <p>You can no longer install SyncData as a regular WebsiteBaker or LEPTON CMS add-on!</p>
        <p>Please have a look at the <b><a href="https://github.com/phpManufaktur/SyncData2/wiki" target="_blank">SyncData Documentation</a></b> to get information on how to install SyncData and get help for your first steps with this powerfull extension for your Content Management System.</p>
        <p>Furthermore you will get any help at the <b><a href="https://support.phpmanufaktur.de" target="_blank">Support Group of the phpManufaktur</a></b>.</p>
    </div>
EOD;

    $PRECHECK['CUSTOM_CHECKS'][$response] = array(
        'REQUIRED' => 'OK',
        'ACTUAL' => 'PROBLEM',
        'STATUS' => false
    );
}
