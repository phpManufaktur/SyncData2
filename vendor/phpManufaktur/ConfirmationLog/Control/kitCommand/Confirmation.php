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
                        'message' => $this->getMessage(),
                        'form' => $form->createView()
                    ));
            }

            // save the data


            print_r($confirmation);

            $cms = $this->getCMSinfoArray();

            print_r($cms);

            return 'ok';
        }
        else {
            // invalid form submission
            $this->setMessage('The form is not valid, please check your input and try again!');

            return $this->app['twig']->render($this->app['utils']->getTemplateFile(
                '@phpManufaktur/ConfirmationLog/Template', 'command/confirmation.twig', $this->getPreferredTemplateStyle()),
                array(
                    'basic' => $this->getBasicSettings(),
                    'message' => $this->getMessage(),
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

        $fields = $this->getFormFields();
        $form = $fields->getForm();

        return $this->app['twig']->render($this->app['utils']->getTemplateFile(
            '@phpManufaktur/ConfirmationLog/Template', 'command/confirmation.twig', $this->getPreferredTemplateStyle()),
            array(
                'basic' => $this->getBasicSettings(),
                'message' => $this->getMessage(),
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
