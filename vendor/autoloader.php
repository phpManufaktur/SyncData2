<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

class autoloader {

  /**
   * Autoloader function
   *
   * @param string $className
   * @return boolean
   */
  static public function loader($className) {
    $filename = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', '/', $className) . '.php';
    if (file_exists($filename)) {
      include($filename);
      if (class_exists($className)) return true;
    }
    return false;
  }

} // class Autoloader

spl_autoload_register('Autoloader::loader');