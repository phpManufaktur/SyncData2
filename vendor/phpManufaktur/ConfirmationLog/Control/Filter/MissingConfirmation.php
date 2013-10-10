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
use phpManufaktur\ConfirmationLog\Data\Filter\Persons;
use phpManufaktur\ConfirmationLog\Data\Documents;

class MissingConfirmation
{
    protected $app = null;
    protected $ConfirmationData = null;
    protected static $config = null;
    private static $message = '';
    protected $Persons = null;
    protected $Documents = null;

    public function __construct($app)
    {
        $this->app = $app;
        $this->ConfirmationData = new Confirmation($app);

        $Config = new Config($app);
        self::$config = $Config->getConfiguration();

        $this->Persons = new Persons($app);

        $this->Documents = new Documents($app);
        if ($this->Documents->checkIfDocumentsNeedUpdate()) {
            $this->Documents->parseForNeededConfirmations();
        }

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
     * Execute a filter for missing confirmations for the given group array and
     * return a result array with page or article titles which are not confirmed
     * by the specified group member.
     * Set a message and return false if the filter fails
     *
     * @param array $group of installation names
     * @param string $group_by 'title' or 'name'
     * @return boolean|array
     */
    public function missingGroups($group, $group_by='title')
    {
        if (false === ($titles = $this->ConfirmationData->getAllTitles())) {
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

    /**
     * Execute a filter for missing confirmations for the given CMS USERGROUP ID and
     * return a result with page or article titles which are not confirmed
     * by the specific member of the usergroup
     *
     * @param integer $group_id
     * @param string $group_by default 'title' alternate 'name'
     * @param string $identifier default 'EMAIL', alternate 'USERNAME'
     * @return boolean|array FALSE if no user is missing
     */
    public function missingPersons($group_id, $group_by='title', $identifier='EMAIL')
    {
        /*
        if (false === ($titles = $this->ConfirmationData->getAllTitles())) {
            $this->setMessage('There exists no page titles which can be checked for a report!');
            return false;
        }
        */

        if (false === ($titles = $this->Documents->getAllTitles())) {
            $this->setMessage('There exists no page titles which can be checked for a report!');
            return false;
        }

        // get all persons which belong to the group with the given ID
        $persons = $this->Persons->getPersonsByGroupID($group_id);

        // loop through the titles and check for missing confirmations
        $missing = array();
        foreach ($titles as $title) {
            foreach ($persons as $person) {
                if ($identifier == 'EMAIL') {
                    // identify user by email
                    if (!$this->ConfirmationData->hasUserEMailConfirmedTitle($title, $person['email'])) {
                        $missing[($group_by == 'title') ? $title : $person['display_name']][] = array(
                            'page_title' => $title,
                            'installation_name' => $person['display_name']
                        );
                    }
                }
                else {
                    // identify user by username
                    if (!$this->ConfirmationData->hasUserNameConfirmedTitle($title, $person['username'])) {
                        $missing[($group_by == 'title') ? $title : $person['display_name']][] = array(
                            'page_title' => $title,
                            'installation_name' => $person['display_name']
                        );
                    }
                }
            }
        }

        return (!empty($missing)) ? $missing : false;
    }
}
