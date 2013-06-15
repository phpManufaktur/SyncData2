<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 *
 */

require_once realpath(dirname(__FILE__).'/vendor/phpManufaktur/SyncData/Control/SystemInformation.php');

$SystemInformation = new SystemInformation();
$SystemInformation->setRequriredPHPVersion('5.3.2');
$SystemInformation->setRequiredMySQLVersion('5.0.0');
$SystemInformation->setRequiredCURL(false);
$SystemInformation->setRequriredZIPArchive(true);
$result = $SystemInformation->exec();
echo "<pre>";
print_r($result);
echo "</pre>";