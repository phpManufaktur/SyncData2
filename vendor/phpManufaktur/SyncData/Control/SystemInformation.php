<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

class SystemInformation
{
    protected static $detected_PHP_VERSION = null;
    protected static $required_PHP_VERSION = null;

    protected static $CMS_TYPE = null;
    protected static $CMS_VERSION = null;

    protected static $detected_MYSQL_VERSION = null;
    protected static $required_MYSQL_VERSION = null;

    protected static $cms_config_path = null;

    public function __construct()
    {
        self::$cms_config_path = realpath(dirname(__FILE__).'/../../../../../config.php');
    }

    public function setPromptResult($prompt_result)
    {
        self::$prompt_result = $prompt_result;
    }

    public function setMinimumPHPVersion($php_version)
    {
        self::$minimum_PHP_VERSION = $php_version;
    }

    protected function getOperatingSystem()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'WINDOWS' : 'LINUX';
    }

    /**
     * Try to get informations about the parent CMS
     *
     * @return Ambigous <string, multitype:NULL >
     */
    protected function getCMSinformation()
    {
        $result = '- no information available -';
        if (file_exists(self::$cms_config_path)) {
            include_once self::$cms_config_path;
            if (defined('DB_HOST')) {
                // establish MySQL connection
                if ((false !== ($db_handle = @mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD))) &&
                    @mysql_select_db(DB_NAME, $db_handle)) {
                    // select the CMS settings
                    $SQL = "SELECT * FROM `".TABLE_PREFIX."settings`";
                    if (false !== ($query = @mysql_query($SQL))) {
                        // reset CMS type and version
                        self::$CMS_TYPE = null;
                        self::$CMS_VERSION = null;
                        while (false !== ($row = mysql_fetch_assoc($query))) {
                            if ($row['name'] == 'wb_version') {
                                self::$CMS_TYPE = 'WebsiteBaker';
                                self::$CMS_VERSION = $row['value'];
                                break;
                            }
                            if ($row['name'] == 'lepton_version') {
                                self::$CMS_TYPE = 'LEPTON';
                                self::$CMS_VERSION = $row['value'];
                                break;
                            }
                        }
                        @mysql_free_result($query);
                        if (!is_null(self::$CMS_TYPE)) {
                            $result = array(
                                'Type' => self::$CMS_TYPE,
                                'Version' => self::$CMS_VERSION
                            );
                        }
                    }
                    // close the db handle
                    @mysql_close($db_handle);
                }
            }
        }
        return $result;
    }

    protected function getMySQLinformation()
    {
        self::$detected_MYSQL_VERSION = mysql_get_client_info();
        if (is_null(self::$required_MYSQL_VERSION)) {
            self::$required_MYSQL_VERSION = self::$detected_MYSQL_VERSION;
        }
        return array(
            'installed' => self::$detected_MYSQL_VERSION,
            'required' => self::$required_MYSQL_VERSION,
            'checked' => version_compare(self::$detected_MYSQL_VERSION, self::$required_MYSQL_VERSION, '>=')
            );
    }

    protected function getPHPinformation()
    {
        if (is_null(self::$required_PHP_VERSION)) {
            self::$required_PHP_VERSION = PHP_VERSION;
        }
        self::$detected_PHP_VERSION = PHP_VERSION;
        return array(
            'installed' => self::$detected_PHP_VERSION,
            'required' => self::$required_PHP_VERSION,
            'checked' => (int) version_compare(self::$detected_PHP_VERSION, self::$required_PHP_VERSION, '>=')
        );
    }

    protected function isCURLinstalled()
    {
        return array(
            'installed' => (int) function_exists('curl_init')
            );
    }

    protected function isZIParchiveInstalled()
    {
        return array(
            'installed' => (int) class_exists('ZipArchive')
        );
    }

    public function exec()
    {
        $result = array(
            'CMS' => $this->getCMSinformation(),
            'OPERATING_SYSTEM' => $this->getOperatingSystem(),
            'PHP_VERSION' => $this->getPHPinformation(),
            'MySQL' => $this->getMySQLinformation(),
            'cURL' => $this->isCURLinstalled(),
            'ZIPArchive' => $this->isZIParchiveInstalled(),
        );
        return $result;
    }

}