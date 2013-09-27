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

use phpManufaktur\ConfirmationLog\Data\Confirmation;

class Setup
{
    protected $app = null;
    protected static $droplet = null;

    /**
     * Install a droplet to the CMS
     *
     * @throws \Exception
     * @return boolean
     */
    protected function installDroplet()
    {
        try {
            $SQL = "SELECT `id` FROM `".CMS_TABLE_PREFIX."mod_droplets` WHERE `name`='".self::$droplet['name']."'";
            $id = $this->app['db']->fetchColumn($SQL);
            if ($id > 0) {
                // the droplet is already installed
                $this->app['monolog']->addInfo('The Droplet `'.self::$droplet['name']."` is already installed.");
                return true;
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

    public function exec($app)
    {
        $this->app = $app;

        $Confirmation = new Confirmation($app);
        $Confirmation->createTable();

        if (defined('SYNCDATA_PATH')) {
            // this is a SyncData installation and we need droplets
            self::$droplet = array(
                'name' => 'syncdata_confirmation',
                'path' => MANUFAKTUR_PATH.'/ConfirmationLog/Data/Setup/Droplet/syncdata_confirmation.php',
                'description' => 'Get a confirmation from the user that he has read a page or article',
                'comments' => 'Please visit https://addons.phpmanufaktur.de/syncdata'
            );
            $this->installDroplet();
        }

        return 'Setup is complete';
    }
}
