<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Droplet;

use phpManufaktur\ConfirmationLog\Control\Control;
use phpManufaktur\ConfirmationLog\Data\Confirmation as ConfirmationData;

class Confirmation extends Control
{

    /**
     * Get the URL of the actual PAGE_ID - check for special pages like
     * TOPICS and/or NEWS and return the URL of the TOPIC/NEW page if active
     *
     * @return boolean|string
     */
    protected function getPageURL()
    {
        global $post_id;
        try {
            if (defined('TOPIC_ID')) {
                // this is a TOPICS page
                $SQL = "SELECT `link` FROM `".TABLE_PREFIX."mod_topics` WHERE `topic_id`='".TOPIC_ID."'";
                $link = $this->app['db']->fetchColumn($SQL);
                // include TOPICS settings
                global $topics_directory;
                include_once WB_PATH . '/modules/topics/module_settings.php';
                return WB_URL . $topics_directory . $link . PAGE_EXTENSION;
            }
            elseif (!is_null($post_id) || defined('POST_ID')) {
                // this is a NEWS page
                $id = (defined('POST_ID')) ? POST_ID : $post_id;
                $SQL = "SELECT `link` FROM `".TABLE_PREFIX."mod_news_posts` WHERE `post_id`='$id'";
                $link = $this->app['db']->fetchColumn($SQL);
                return WB_URL.PAGES_DIRECTORY.$link.PAGE_EXTENSION;
            }
            else {
                $SQL = "SELECT `link` FROM `".TABLE_PREFIX."pages` WHERE `page_id`='".PAGE_ID."'";
                $link = $this->app['db']->fetchColumn($SQL);
                return WB_URL.PAGES_DIRECTORY.$link.PAGE_EXTENSION;
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Get the page title for the actual PAGE_ID. Check also for NEWS and TOPICS
     *
     * @throws \Exception
     * @return string page title
     */
    protected function getPageTitle() {
        global $post_id;

        try {
            if (defined('TOPIC_ID')) {
                // get title from TOPICS
                $SQL = "SELECT `title` FROM ".TABLE_PREFIX."mod_topics WHERE `topic_id`='".TOPIC_ID."'";
            }
            elseif (!is_null($post_id) || defined('POST_ID')) {
                // get title from NEWS
                $id = (defined('POST_ID')) ? POST_ID : $post_id;
                $SQL = "SELECT `title` FROM ".TABLE_PREFIX."mod_news_posts WHERE `post_id`='$id'";
            }
            else {
                // get regular page title
                $SQL = "SELECT `page_title` FROM ".TABLE_PREFIX."pages WHERE `page_id`='".PAGE_ID."'";
            }
            return $this->app['db']->fetchColumn($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }


    /**
     * Check the submitted confirmation and insert it as new record.
     * Set a message and return false if the check fails.
     *
     * @param array $parameter
     * @return boolean
     */
    protected function checkConfirmation($parameter)
    {
        global $post_id;

        $checked = true;
        if ($parameter['email']['active'] && (!isset($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL))) {
            // invalid email address
            $this->setMessage('The email address %email% is not valid!', array(
                '%email%' => isset($_POST['email']) ? $_POST['email'] : ''
            ));
            $checked = false;
        }
        if ($parameter['name']['active'] && (!isset($_POST['name']) || strlen(trim($_POST['name'])) < 3)) {
            // invalid name
            $this->setMessage('The name must contain at minimum 3 characters.');
            $checked = false;
        }
        if ($parameter['confirm']['active'] && (!isset($_POST['confirm']) || ($_POST['confirm'] != 1))) {
            // missing confirmation
            $this->setMessage('The confirmation box must be checked!');
            $checked = false;
        }

        // problems, we can exit here ...
        if (!$checked) return false;

        if (defined('TOPIC_ID')) {
            $second_id = TOPIC_ID;
            $page_type = 'TOPICS';
        }
        elseif (!is_null($post_id) || defined('POST_ID')) {
            $second_id = (defined('POST_ID')) ? POST_ID : $post_id;
            $page_type = 'NEWS';
        }
        else {
            $second_id = 0;
            $page_type = 'PAGE';
        }

        $data = array(
            'page_id' => PAGE_ID,
            'page_type' => $page_type,
            'second_id' => $second_id,
            'installation_name' => defined('INSTALLATION_NAME') ? INSTALLATION_NAME : '',
            'user_name' => (isset($_SESSION['DISPLAY_NAME'])) ? $_SESSION['DISPLAY_NAME'] : '',
            'user_email' => (isset($_SESSION['EMAIL'])) ? $_SESSION['EMAIL'] : '',
            'page_title' => $this->getPageTitle(),
            'page_url' => $this->getPageURL(),
            'typed_name' => trim($_POST['name']),
            'typed_email' => strtolower(trim($_POST['email'])),
            'confirmed_at' => date('Y-m-d H:i:s'),
            'time_on_page' => time() - $_POST['start_stamp'],
            'status' => 'PENDING'
        );

        $ConfirmationData = new ConfirmationData($this->app);
        $ConfirmationData->insert($data);

        return true;
    }

    /**
     * Execute the droplet [[syncdata_confirmation]]
     *
     * @param Application $app
     * @param parameter $parameter
     * @return string rendered confirmation form
     */
    public function exec($app, $parameter)
    {
        if (isset($_SESSION['DROPLET_EXECUTED_BY_DROPLETS_EXTENSION'])) {
            // ignore the scan function of the DropletsExtension
            return null;
        }

        $this->initialize($app);
        $this->app['translator']->setLocale(strtolower(LANGUAGE));

        if (isset($_POST['start_stamp'])) {
            if ($this->checkConfirmation($parameter)) {
                // confirm the submission and unset all POST variables
                $this->setMessage('Thank you for the confirmation!');
                $unsets = array('email', 'name', 'confirm', 'start_stamp');
                foreach ($unsets as $unset) {
                    unset($_POST[$unset]);
                }
            }
        }

        // mark the start of this script
        $start_stamp = (isset($_POST['start_stamp'])) ? $_POST['start_stamp'] : time();

        return $app['twig']->render('ConfirmationLog/Template/default/droplet/confirmation.twig', array(
            'TEMPLATE_URL' => WB_URL.'/ConfirmationLog/Template/default/droplet',
            'locale' => $app['translator']->getLocale(),
            'message' => $this->getMessage(),
            'form' => array(
                'action' => $this->getPageURL(),
                'email' => array(
                    'active' => $parameter['email']['active'],
                    'name' => 'email',
                    'value' => (isset($_POST['email'])) ? $_POST['email'] : ''
                ),
                'name' => array(
                    'active' => $parameter['name']['active'],
                    'name' => 'name',
                    'value' => (isset($_POST['name'])) ? $_POST['name'] : ''
                ),
                'confirm' => array(
                    'active' => $parameter['confirm']['active'],
                    'name' => 'confirm',
                    'value' => 1
                ),
                'css' => array(
                    'active' => $parameter['css']['active']
                ),
                'start_stamp' => $start_stamp
            )
        ));
    }
}
