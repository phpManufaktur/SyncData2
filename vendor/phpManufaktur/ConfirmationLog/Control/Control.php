<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control;

class Control
{
    protected $app = null;
    protected static $message = '';

    protected function initialize($app)
    {
        $this->app = $app;
    }

    /**
     * @return the $message
     */
    public function getMessage()
    {
        return self::$message;
    }

    /**
     * Set a message. Messages are chained and will be translated with the given
     * parameters. If $log_message = true, the message will also logged to the
     * kitFramework logfile.
     *
     * @param string $message
     * @param array $params
     * @param boolean $log_message
     */
    public function setMessage($message, $params=array(), $log_message=false)
    {
        self::$message .= sprintf('<div class="message item">%s</div>', $this->app['translator']->trans($message, $params));
        if ($log_message) {
            // log this message
            $this->app['monolog']->addInfo(strip_tags($this->app['translator']->trans($message, $params, 'messages', 'en')));
        }
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
     * Clear the existing message(s)
     */
    public function clearMessage()
    {
        self::$message = '';
    }

}
