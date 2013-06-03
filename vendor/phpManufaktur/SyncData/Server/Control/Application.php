<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Server\Control;

require_once SYNC_DATA_PATH.'/vendor/Pimple/Pimple.php';

class Application extends \Pimple {

    private $data;

    /*
    public function __get($varName)
    {


        if (!array_key_exists($varName, $this->data)){
            //this attribute is not defined!
            throw new \Exception("The dynamic variable $varName does not exist in Application!");
        }
        else {
            return $this->data[$varName];
        }

    }

    public function __set($varName,$value){
        $this->data[$varName] = $value;
    }
    */

};