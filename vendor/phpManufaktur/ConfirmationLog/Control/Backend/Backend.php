<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/event
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Backend;

use phpManufaktur\ConfirmationLog\Data\Confirmation;

class Backend {

    protected $app = null;
    protected static $usage = null;
    protected static $message = '';
    protected static $link = null;
    protected $ConfirmationData = null;

    /**
     * Constructor
     */
    public function __construct($app=null) {
        if (!is_null($app)) {
            $this->initialize($app);
        }
    }

    /**
     * Initialize the class with the needed parameters
     *
     * @param Application $app
     * @todo route for kitFramework is not defined!
     */
    protected function initialize($app)
    {
        $this->app = $app;
        if (defined('SYNCDATA_PATH')) {
            // executed from SyncData installation
            self::$usage = 'SyncData';
            $app['translator']->setLocale(strtolower(LANGUAGE));
            self::$link = CMS_ADMIN_URL.'/admintools/tool.php?tool=kit_framework_confirmationlog';
        }
        else {
            // executed from kitFramework installation
            self::$usage = $this->app['request']->get('usage', 'framework');
            // set the locale from the CMS locale
            if (self::$usage != 'framework') {
                $app['translator']->setLocale($this->app['session']->get('CMS_LOCALE', 'en'));
            }
            self::$link = null;
        }

        // init Confirmation Data
        $this->ConfirmationData = new Confirmation($app);
    }

    /**
     * Get the toolbar for all backend dialogs
     *
     * @param string $active dialog
     * @return multitype:multitype:string boolean
     */
    public function getToolbar($active)
    {
        $toolbar_array = array(
            'list' => array(
                'text' => 'List',
                'hint' => 'List of confirmations',
                'link' => self::$link.'&action=list&usage='.self::$usage,
                'active' => ($active == 'list')
            ),
            'import' => array(
                'text' => 'Import',
                'hint' => 'Import of data records',
                'link' => self::$link.'&action=import&usage='.self::$usage,
                'active' => ($active == 'import')
            ),
            'about' => array(
                'text' => 'About',
                'hint' => 'About the ConfirmationLog',
                'link' => self::$link.'&action=about&usage='.self::$usage,
                'active' => ($active == 'about')
            )
        );
        return $toolbar_array;
    }

    /**
     * @return the $message
     */
    public function getMessage ()
    {
        return self::$message;
    }

      /**
     * @param string $message
     */
    public function setMessage($message, $params=array())
    {
        self::$message .= $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/message.twig'),
            array('message' => $this->app['translator']->trans($message, $params)));
    }

    public function clearMessage()
    {
        self::$message = '';
    }

    /**
     * Check if a message is active
     *
     * @return boolean
     */
    public function isMessage()
    {
        return !empty(self::$message);
    }

    /**
     * Return the GET paramter depending from the usage with different methods
     *
     * @param string $parameter_name
     * @param string $default_value default NULL
     * @return Ambigous <unknown, string>
     */
    protected function getParameter($parameter_name, $default_value=null)
    {
        if (self::$usage == 'SyncData') {
            return (isset($_GET[$parameter_name])) ? $_GET[$parameter_name] : $default_value;
        }
        else {
            return $this->app['request']->get($parameter_name, $default_value);
        }
    }

    protected function removeParameter($parameter_name)
    {
        if (self::$usage == 'SyncData') {
            unset($_GET[$parameter_name]);
        }
        else {
            $this->app['request']->remove($parameter_name);
        }
    }
 }
