<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Setup;

class Droplet
{
    protected $app = null;
    protected static $droplet = null;

    /**
     * Constructor - don't use `Application` at this point!
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Set the Droplet information
     *
     * @param string $name of the droplet
     * @param string $path to the droplet code
     * @param string $description of the droplet
     * @param string $comments additional information
     */
    public function setDropletInfo($name, $path, $description, $comments)
    {
        self::$droplet = array(
            'name' => $name,
            'path' => $path,
            'description' => $description,
            'comments' => $comments
        );
    }

    /**
     * Return the Droplet information
     *
     * @return multitype:string
     */
    public function getDropletInfo()
    {
        return self::$droplet;
    }

    /**
     * Install a Droplet
     *
     * @throws \UnexpectedValueException
     * @throws \Exception
     * @return boolean
     */
    public function install()
    {
        try {
            if (is_null(self::$droplet) || !isset(self::$droplet['name']) || !isset(self::$droplet['path']) ||
                !isset(self::$droplet['description']) || !isset(self::$droplet['comments'])) {
                // info record must be set and valid
                throw new \UnexpectedValueException('The Droplet Info record must be set!');
            }

            $SQL = "SELECT `id` FROM `".CMS_TABLE_PREFIX."mod_droplets` WHERE `name`='".self::$droplet['name']."'";
            $id = $this->app['db']->fetchColumn($SQL);
            if ($id > 0) {
                // the droplet is already installed
                $this->app['monolog']->addInfo('The Droplet `'.self::$droplet['name']."` is already installed.");
                return false;
            }

            if (false === ($content = file_get_contents(self::$droplet['path']))) {
                throw new \Exception('File not found: '.self::$droplet['path']);
            }

            $content = str_replace(array('<?php','<?','?>'), '', $content);

            $data = array(
                'name' => self::$droplet['name'],
                'code' => $content,
                'description' => self::$droplet['description'],
                'modified_when' => time(),
                'modified_by' => 1,
                'active' => 1,
                'comments' => self::$droplet['comments']
            );

            $this->app['db']->insert(CMS_TABLE_PREFIX.'mod_droplets', $data);
            $this->app['monolog']->addInfo('The droplet `'.self::$droplet['name']."` has successfull installed.");
            return true;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Uninstall the Droplet
     *
     * @throws \UnexpectedValueException
     * @throws \Exception
     */
    public function uninstall()
    {
        try {
            if (is_null(self::$droplet) || !isset(self::$droplet['name']) || !isset(self::$droplet['path']) ||
            !isset(self::$droplet['description']) || !isset(self::$droplet['comments'])) {
                // info record must be set and valid
                throw new \UnexpectedValueException('The Droplet Info record must be set!');
            }

            $SQL = "SELECT `id` FROM `".CMS_TABLE_PREFIX."mod_droplets` WHERE `name`='".self::$droplet['name']."'";
            $id = $this->app['db']->fetchColumn($SQL);
            if ($id > 0) {
                // the droplet exists
                $this->app['db']->delete(CMS_TABLE_PREFIX."mod_droplets", array('id' => $id));
                $this->app['monolog']->addInfo('The Droplet `'.self::$droplet['name']."` has removed.");
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Update the Droplet (uninstall and install again)
     */
    public function update()
    {
        $this->uninstall();
        $this->install();
    }

    /**
     * Check if the old droplet [[confirmation_log]] exists and rewrite it with
     * the actual code (compatibility)
     *
     * @throws \UnexpectedValueException
     * @throws \Exception
     */
    public function checkOldConfirmationLogDroplet()
    {
        try {
            if (is_null(self::$droplet) || !isset(self::$droplet['name']) || !isset(self::$droplet['path']) ||
                !isset(self::$droplet['description']) || !isset(self::$droplet['comments'])) {
                // info record must be set and valid
                throw new \UnexpectedValueException('The Droplet Info record must be set!');
            }

            $SQL = "SELECT `id` FROM `".CMS_TABLE_PREFIX."mod_droplets` WHERE `name`='confirmation_log'";
            $id = $this->app['db']->fetchColumn($SQL);
            if ($id > 0) {
                // the droplet exists - remove the old version !!!
                $this->app['db']->delete(CMS_TABLE_PREFIX."mod_droplets", array('id' => $id));
                $this->app['monolog']->addInfo('The Droplet `confirmation_log` has removed.');
                // adapt the settings to old droplet name
                self::$droplet['name'] = 'confirmation_log';
                // install the old droplet with the actual code
                $this->install();
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}
