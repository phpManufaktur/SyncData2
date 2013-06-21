<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Control;


class CheckKey
{

    protected $app = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function getKeyHint()
    {
        return "Action denied. Please authenticate with the key you have got.";
    }

    public function check()
    {
        if ($this->app['config']['security']['active'] === false) {
            // don't check the security key!
            $this->app['monolog']->addInfo('Passed KEY check because the security is not active.',
                array('method' => __METHOD__, 'line' => __LINE__));
            return true;
        }
        if (isset($_GET['key']) && ($_GET['key'] === $this->app['config']['security']['key'])) {
            $this->app['monolog']->addInfo('KEY check was successfull!',
                array('method' => __METHOD__, 'line' => __LINE__));
            return true;
        }
        $this->app['monolog']->addInfo('Security check failed!',
            array('method' => __METHOD__, 'line' => __LINE__));
        return false;
    }

}