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

    protected static $detected_CURL = null;
    protected static $required_CURL = null;

    protected static $detected_ZIPArchive = null;
    protected static $required_ZIPArchive = null;

    protected static $cms_config_path = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        self::$cms_config_path = realpath(dirname(__FILE__).'/../../../../../config.php');
    }

    /**
     * Set the required PHP version
     *
     * @param string $php_version i.e. '5.3.2'
     */
    public function setRequriredPHPVersion($php_version)
    {
        self::$required_PHP_VERSION = $php_version;
    }

    /**
     * Set the required MySQL version
     *
     * @param string $mysql_version i.e. '5.0.0'
     */
    public function setRequiredMySQLVersion($mysql_version)
    {
        self::$required_MYSQL_VERSION = $mysql_version;
    }

    /**
     * Determine wether cURL is needed or not
     *
     * @param boolean $required
     */
    public function setRequiredCURL($required)
    {
        self::$required_CURL = (bool) $required;
    }

    /**
     * Determine wether the ZIPArchive is needed or not
     *
     * @param boolean $required
     */
    public function setRequriredZIPArchive($required)
    {
        self::$required_ZIPArchive = (bool) $required;
    }

    /**
     * Return the operating system: WINDOWS or LINUX
     *
     * @return string
     */
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

    /**
     * Return an array with information about MySQL
     *
     * @return multitype:mixed NULL
     */
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

    /**
     * Return an array with information about PHP
     *
     * @return multitype:number NULL
     */
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
        self::$detected_CURL = function_exists('curl_init');
        if (is_null(self::$required_CURL)) {
            self::$required_CURL = self::$detected_CURL;
        }
        return array(
            'installed' => (int) self::$detected_CURL,
            'required' => (int) self::$required_CURL,
            'checked' => (self::$required_CURL && !self::$detected_CURL) ? 0 : 1
            );
    }

    protected function isZIParchiveInstalled()
    {
        self::$detected_ZIPArchive = class_exists('ZipArchive');
        if (is_null(self::$required_ZIPArchive)) {
            self::$required_ZIPArchive = self::$detected_ZIPArchive;
        }
        return array(
            'installed' => (int) self::$detected_ZIPArchive,
            'required' => (int) self::$required_ZIPArchive,
            'checked' => (self::$required_ZIPArchive && !self::$detected_ZIPArchive) ? 0 : 1
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