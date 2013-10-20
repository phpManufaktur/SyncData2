<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\kitCommand;

use Silex\Application;
use phpManufaktur\Basic\Control\kitCommand\Basic;
use phpManufaktur\Basic\Data\CMS\Page;
use phpManufaktur\ConfirmationLog\Data\Confirmation as ConfirmationData;
use phpManufaktur\ConfirmationLog\Data\Config;

class Confirmation extends Basic
{
    protected static $useEMail = null;
    protected static $useName = null;
    protected static $useConfirm = null;

    /**
     * (non-PHPdoc)
     * @see \phpManufaktur\Basic\Control\kitCommand\Basic::initParameters()
     */
    protected function initParameters(Application $app, $parameter_id=-1)
    {
        parent::initParameters($app, $parameter_id);

        // get the parameters
        $params = $this->getCommandParameters();

        // set the defaults by parameters
        self::$useEMail = (isset($params['email']) && ((strtolower($params['email']) == 'false') || ($params['email'] == '0'))) ? false : true;
        self::$useName = (isset($params['name']) && ((strtolower($params['name']) == 'false') || ($params['name'] == '0'))) ? false : true;
        self::$useConfirm = (isset($params['confirm']) && ((strtolower($params['confirm']) == 'false') || ($params['confirm'] == '0'))) ? false : true;
    }

    /**
     * Get the form fields
     *
     * @param array $confirmation
     * @return FormFactory
     */
    protected function getFormFields($confirmation=array())
    {
        $form = $this->app['form.factory']->createBuilder('form')
            ->add('start_stamp', 'hidden', array(
                'data' => isset($confirmation['start_stamp']) ? $confirmation['start_stamp'] : time()
            ));
        if (self::$useEMail) {
            $form->add('email', 'email', array(
                'data' => isset($confirmation['email']) ? $confirmation['email'] : '',
                'label' => 'Your email'
            ));
        }
        if (self::$useName) {
            $form->add('name', 'text', array(
                'data' => isset($confirmation['name']) ? $confirmation['name'] : '',
                'label' => 'Your name'
            ));
        }
        if (self::$useConfirm) {
            $form->add('confirm', 'choice', array(
                'choices' => array('yes' => 'I have read the full text above'),
                'expanded' => true,
                'multiple' => true,
                'required' => true,
                'label' => '&nbsp;', // suppress label
                'data' => isset($confirmation['confirm']) ? array('yes') : null
            ));
        }
        return $form;
    }

    /**
     * Controller to check the confirmation. Insert a new record on success.
     *
     * @param Application $app
     * @return string result message
     */
    public function controllerCheckConfirmation(Application $app)
    {
        $this->initParameters($app);

        $fields = $this->getFormFields();
        $form = $fields->getForm();

        $form->bind($this->app['request']);

        if ($form->isValid()) {
            $confirmation = $form->getData();

            // check data
            $checked = true;
            if (self::$useConfirm && (!isset($confirmation['confirm']))) {
                $this->setMessage('The confirmation box must be checked!');
                $checked = false;
            }
            if (self::$useEMail && (!isset($confirmation['email']) || !filter_var($confirmation['email'], FILTER_VALIDATE_EMAIL))) {
                $this->setMessage('The email address %email% is not valid!', array('%email%' => $confirmation['email']));
                $checked = false;
            }
            if (self::$useName && (!isset($confirmation['name']) || (strlen($confirmation['name']) < 3))) {
                $this->setMessage('The name must contain at minimum 3 characters.');
                $checked = false;
            }

            if (!$checked) {
                // show the dialog again and prompt a message
                return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                    '@phpManufaktur/ConfirmationLog/Template', 'command/confirmation.twig', $this->getPreferredTemplateStyle()),
                    array(
                        'basic' => $this->getBasicSettings(),
                        'form' => $form->createView()
                    ));
            }

            // save the data
            $special = $this->getCMSinfoArray();

            $page_id = $this->getCMSpageID();

            $topic_id = !is_null($special['special']['topic_id']) ? $special['special']['topic_id'] : null;
            $post_id = !is_null($special['special']['post_id']) ? $special['special']['post_id'] : null;

            $Page = new Page($app);
            $page_title = $Page->getTitle($page_id, array('topic_id' => $topic_id, 'post_id' => $post_id));

            // check for a INSTALLATION_NAME
            if (false === ($installation_name = $app['utils']->parseFileForConstants(CMS_PATH.'/config.php', 'INSTALLATION_NAME'))) {
                $installation_name = '';
            }

            $data = array(
                'page_id' => $page_id,
                'page_type' => (!is_null($topic_id) || !is_null($post_id)) ? (!is_null($topic_id)) ? 'TOPICS' : 'NEWS' : 'PAGE',
                'second_id' => (!is_null($topic_id) || !is_null($post_id)) ? (!is_null($topic_id)) ? $topic_id : $post_id : 0,
                'installation_name' => $installation_name,
                'user_name' => $this->getCMSuserName(),
                'user_email' => $this->getCMSuserEMail(),
                'page_title' => $page_title,
                'page_url' => $this->getCMSpageURL(),
                'typed_name' => isset($confirmation['name']) ? $confirmation['name'] : '',
                'typed_email' => isset($confirmation['email']) ? strtolower($confirmation['email']) : '',
                'confirmed_at' => date('Y-m-d H:i:s'),
                'time_on_page' => time() - $confirmation['start_stamp'],
                'status' => ($installation_name == 'SERVER') ? 'SUBMITTED' : 'PENDING'
            );

            $ConfirmationData = new ConfirmationData($app);
            $ConfirmationData->insert($data);

            $this->setMessage('Thank you for the confirmation!');

            return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/ConfirmationLog/Template', 'command/received.twig', $this->getPreferredTemplateStyle()),
                array(
                    'basic' => $this->getBasicSettings(),
                    'form' => $form->createView()
                ));
        }
        else {
            // invalid form submission
            $this->setMessage('The form is not valid, please check your input and try again!');

            return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/ConfirmationLog/Template', 'command/confirmation.twig', $this->getPreferredTemplateStyle()),
                array(
                    'basic' => $this->getBasicSettings(),
                    'form' => $form->createView()
                ));
        }
    }

    /**
     * Create the Confirmation dialog
     *
     * @param Application $app
     * @return rendered dialog
     */
    public function controllerDialog(Application $app)
    {
        // initialize the Basic class
        $this->initParameters($app);

        $Cfg = new Config($app);
        $config = $Cfg->getConfiguration();
        $only_once = (isset($config['confirmation']['only_once'])) ? $config['confirmation']['only_once'] : true;
        $identifier = (isset($config['confirmation']['identifier'])) ? $config['confirmation']['identifier'] : 'USERNAME';

        $username = $this->getCMSuserName();
        $useremail = $this->getCMSuserEMail();
        if ($only_once && !empty($username) && !empty($useremail)) {

            $special = $this->getCMSinfoArray();
            $page_id = $this->getCMSpageID();

            $topic_id = !is_null($special['special']['topic_id']) ? $special['special']['topic_id'] : null;
            $post_id = !is_null($special['special']['post_id']) ? $special['special']['post_id'] : null;

            $second_id = (!is_null($topic_id) || !is_null($post_id)) ? (!is_null($topic_id)) ? $topic_id : $post_id : 0;
            $page_type = (!is_null($topic_id) || !is_null($post_id)) ? (!is_null($topic_id)) ? 'TOPICS' : 'NEWS' : 'PAGE';

            $ConfirmationData = new ConfirmationData($app);
            $identifier_name = ($identifier == 'USERNAME') ? $this->getCMSuserName() : $this->getCMSuserEMail();
            $confirmation = array();

            if ($ConfirmationData->hasAlreadyConfirmed($identifier, $identifier_name, $page_type, $page_id, $second_id, $confirmation)) {
                // the user has already confirmed this article
                return $app['twig']->render($this->app['utils']->getTemplateFile(
                    '@phpManufaktur/ConfirmationLog/Template',
                    'command/ok.confirmation.twig',
                    $this->getPreferredTemplateStyle()),
                    array(
                        'basic' => $this->getBasicSettings(),
                        'confirmation' => $confirmation
                ));
            }
        }

        $fields = $this->getFormFields();
        $form = $fields->getForm();

        return $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/ConfirmationLog/Template', 'command/confirmation.twig', $this->getPreferredTemplateStyle()),
            array(
                'basic' => $this->getBasicSettings(),
                'form' => $form->createView()
            ));
    }

    /**
     * Create the iFrame for the dialog and execute the route
     * /confirmationlog/dialog
     *
     * @param Application $app
     */
    public function controllerCreateIFrame(Application $app)
    {
        $this->initParameters($app);
        return $this->createIFrame('/confirmationlog/dialog');
    }
}
