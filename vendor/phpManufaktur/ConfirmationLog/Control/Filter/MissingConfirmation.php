<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Filter;

use phpManufaktur\ConfirmationLog\Data\Confirmation;
use phpManufaktur\ConfirmationLog\Data\Config;

class MissingConfirmation
{
    protected $app = null;
    protected $ConfirmationData = null;
    protected static $config = null;
    private static $message = '';

    public function __construct($app)
    {
        $this->app = $app;
        $this->ConfirmationData = new Confirmation($app);

        $Config = new Config($app);
        self::$config = $Config->getConfiguration();
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


    public function missingGroups($group, $group_by='title')
    {
        if (false ===($titles = $this->ConfirmationData->getAllTitles())) {
            $this->setMessage('There exists no page titles which can be checked for a report!');
            return false;
        }

        // loop through the titles and check for missing confirmations
        $missing = array();
        foreach ($titles as $title) {
            foreach ($group as $name) {
                if (!$this->ConfirmationData->hasInstallationNameConfirmedTitle($title, $name)) {
                    $missing[($group_by == 'title') ? $title : $name][] = array(
                        'page_title' => $title,
                        'installation_name' => $name
                    );
                }
            }
        }

        return (!empty($missing)) ? $missing : false;
    }


}
