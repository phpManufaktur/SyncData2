<?php

/**
 * kitFramework::Basic
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Setup;

/**
 * Class to access the CMS addons
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 *
 */
class Addons
{

    protected $app = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Check if the addon with the given directory name exists in the table
     *
     * @param string $directory
     * @throws \Exception
     * @return boolean
     */
    public function existsDirectory($directory)
    {
        try {
            $SQL = "SELECT `directory` FROM `" . CMS_TABLE_PREFIX . "addons` WHERE `directory`='$directory'";
            $result = $this->app['db']->fetchColumn($SQL);
            return ($result == $directory);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Insert a new record into the Addons table
     *
     * @param array $data
     * @param reference integer $addon_id
     * @throws \Exception
     */
    public function insert($data, &$addon_id=null)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value) {
                $insert[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->unsanitizeText($value) : $value;
            }
            $this->app['db']->insert(CMS_TABLE_PREFIX.'addons', $insert);
            $addon_id = $this->app['db']->lastInsertId();
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Update the addon record with the given directory name and the data array
     *
     * @param string $directory
     * @param array $data
     * @throws \Exception
     */
    public function update($directory, $data)
    {
        try {
            $update = array();
            foreach ($data as $key => $value) {
                if ($key == 'directory') continue;
                $update[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? $this->app['utils']->sanitizeText($value) : $value;
            }
            if (!empty($update)) {
                $this->app['db']->update(CMS_TABLE_PREFIX.'addons', $update, array('directory' => $directory));
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Delete the record with the given directory name
     *
     * @param string $directory
     * @throws \Exception
     */
    public function delete($directory)
    {
        try {
            $this->app['db']->delete(CMS_TABLE_PREFIX.'addons', array('directory' => $directory));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Select a addon by the given installation directory
     *
     * @param string $directory
     * @throws \Exception
     * @return Ambigous <boolean, array> false or record
     */
    public function select($directory)
    {
        try {
            $SQL = "SELECT * FROM `".CMS_TABLE_PREFIX."addons` WHERE `directory`='$directory'";
            $result = $this->app['db']->fetchAssoc($SQL);
            return (isset($result['addon_id'])) ? $result : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

}
