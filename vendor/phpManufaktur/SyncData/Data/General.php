<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Data;

use phpManufaktur\SyncData\Control\Application;

/**
 * General class for queries and functions to access the different
 * tables of the CMS
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class General {

    protected $app = null;

    /**
     * Constructor
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Return the tables of the configured database
     *
     * @throws \Exception
     * @return array
     */
    public function getTables($strip_table_prefix=false)
    {
        try {
            $result = $this->app['db']->fetchAll("SHOW TABLES");
            $tables = array();
            if (is_array($result)) {
                foreach ($result as $item) {
                    foreach ($item as $show => $table) {
                        if ($strip_table_prefix && (strpos($table, CMS_TABLE_PREFIX) === 0)) {
                            $table = substr($table, strlen(CMS_TABLE_PREFIX));
                        }
                        $tables[] = $table;
                    }
                }
            }
            return $tables;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Return all rows of the given table
     *
     * @param string $table
     * @throws \Exception
     * @return unknown
     */
    public function getTableContent($table)
    {
        try {
            $result = $this->app['db']->fetchAll("SELECT * FROM `$table`");
            return $result;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Read a row from the given table
     *
     * @param string $table
     * @param array $fields associative array with fields and data for the WHERE clause
     * @throws \Doctrine\DBAL\DBALException
     * @return multitype:Ambigous <mixed, unknown> |boolean
     */
    public function getRowContent($table, $fields)
    {
        try {
            $query_string = '';
            foreach ($fields as $key => $value) {
                if (!empty($query_string))
                    $query .= ' AND ';
                $query_string .= $this->app['db']->quoteIdentifier($key)."='$value'";
            }
            $SQL = "SELECT * FROM `$table` WHERE $query_string";
            $result = $this->app['db']->fetchAssoc($SQL);
            if (is_array($result)) {
                $record = array();
                foreach ($result as $key => $value) {
                    $record[$key] = is_string($value) ? str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', $value) : $value;
                }
                return $record;
            }
            return false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Get the checksum for the given tables, looping through all rows
     *
     * @param string $table
     * @throws \Exception
     * @return Ambigous <boolean, string>
     */
    public function getTableContentChecksum($table)
    {
        try {
            $result = $this->app['db']->fetchAll("SELECT * FROM `$table`");
            $checksum = false;
            $content = '';
            if (is_array($result)) {
                foreach ($result as $row) {
                    $content .= md5(str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', implode(',', $row)));
                }
                $checksum = md5($content);
            }
            return $checksum;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Get the MD5 checksum for the given table row
     *
     * @param string $table
     * @param array $fields associative array with fields and data for the WHERE clause
     * @throws \Doctrine\DBAL\DBALException
     * @return string|boolean
     */
    public function getRowContentChecksum($table, $fields)
    {
        try {
            $query_string = '';
            foreach ($fields as $key => $value) {
                if (!empty($query_string))
                    $query .= ' AND ';
                $query_string .= $this->app['db']->quoteIdentifier($key)."='$value'";
            }
            $SQL = "SELECT * FROM `$table` WHERE $query_string";
            $result = $this->app['db']->fetchAssoc($SQL);
            if (is_array($result)) {
                return md5(str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', implode(',', $result)));
            }
            return false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Return the index fields for the given table
     *
     * @param string $table
     * @throws \Exception
     * @return array of the index fields
     */
    public function listTableIndexes($table)
    {
        try {
            $shemaManager = $this->app['db']->getSchemaManager();
            $result = $shemaManager->listTableIndexes($table);
            return $result;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Return the SQL code to create the given table
     *
     * @param string $table
     * @param boolean $replaceTablePrefix TRUE to replace the table prefix with a placeholder
     * @param boolean $useIfNotExists add IF NOT EXISTS if TRUE
     * @throws \Exception
     * @return Ambigous <boolean, mixed>
     */
    public function getCreateTableSQL($table, $replaceTablePrefix=true, $useIfNotExists=true)
    {
        try {
            $result = $this->app['db']->fetchAssoc("SHOW CREATE TABLE `$table`");
            $SQL = false;
            if (isset($result['Create Table']) && isset($result['Table'])) {
                // get the table name
                $table = $result['Table'];
                $no_prefix = $result['Table'];
                $not_exists = '';
                if ($replaceTablePrefix && (CMS_TABLE_PREFIX !== '')) {
                    $no_prefix = str_replace(CMS_TABLE_PREFIX, '{{ SyncData:TABLE_PREFIX }}', $table);
                }
                if ($useIfNotExists) {
                    $not_exists = ' IF NOT EXISTS';
                }
                $SQL = str_replace(sprintf("CREATE TABLE `%s`", $table), sprintf("CREATE TABLE%s `%s`", $not_exists, $no_prefix), $result['Create Table']);
            }
            return $SQL;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Check wether the given table exists
     *
     * @param string $table
     * @return boolean
     */
    public function tableExists($table)
    {
        try {
            $result = $this->app['db']->fetchAssoc("DESCRIBE `$table`");
            return true;
        } catch (\Doctrine\DBAL\DBALException $e) {
            // don't throw an exception, just log it!
            $this->app['monolog']->addInfo("The table $table does not exists!",
                array('method' => __METHOD__, 'line' => __LINE__));
            return false;
        }
    }

    /**
     * Drop (delete) the given table
     *
     * @param string $table
     * @throws \Exception
     */
    public function dropTable($table)
    {
        try {
            $this->app['db']->query("DROP TABLE IF EXISTS `$table`");
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Execute the given SQL code
     *
     * @param string $SQL
     * @throws \Exception
     */
    public function query($SQL)
    {
        try {
            $this->app['db']->query($SQL);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Insert an array of rows into the given table
     *
     * @param string $table
     * @param array $rows
     * @param boolean $replace_cms_url replace the CMS URL with a placeholder if TRUE
     * @throws \Exception
     */
    public function insertRows($table, $rows, $replace_cms_url=true)
    {
        try {
            foreach ($rows as $row) {
                $this->insert($table, $row, $replace_cms_url);
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Compatibility SQL Insert for CMS tables which does not handle UTF-8 in a proper way
     *
     * @param string $table including the CMS_TABLE_PREFIX
     * @param array $data
     * @param boolean $replace_cms_url
     * @throws \Exception
     * @throws Exception
     */
    protected function compatibilityInsert($table, $data, $replace_cms_url)
    {
        try {
            $SQL = "INSERT INTO `$table` SET ";
            $start = true;
            foreach ($data as $key => $value) {
                if (($table === CMS_TABLE_PREFIX.'pages') && (($key === 'menu_title') || ($key == 'page_title'))) {
                    $value = htmlspecialchars($value);
                }
                if ($replace_cms_url) {
                    $value = is_string($value) ? str_replace('{{ SyncData:CMS_URL }}', CMS_URL, $value) : $value;
                }
                if (!$start) {
                    $SQL .= ", ";
                }
                if ($start) {
                    $start = false;
                }
                $SQL .= "`$key`='$value'";

            }
            // create a separated MySQL connection
            if ((false === ($db_config = json_decode(@file_get_contents(SYNCDATA_PATH.'/config/doctrine.json'), true))) || !is_array($db_config)) {
                throw new \Exception("Can't read the Doctrine configuration file!");
            }
            // connect MySQL
            if (false === ($link = mysql_connect($db_config['DB_HOST'], $db_config['DB_USERNAME'], $db_config['DB_PASSWORD']))) {
                throw new \Exception("Error connectiong to the database: ".mysql_error());
            }
            // select database
            if (!mysql_select_db($db_config['DB_NAME'], $link)) {
                throw new \Exception("Error selecting the database: ".mysql_error());
            }
            // insert the row "as it is"
            if (!mysql_query($SQL, $link)) {
                throw new \Exception("Error compatibility INSERT: ".mysql_error());
            }
            // close the connection
            mysql_close($link);
            $this->app['monolog']->addDebug("Compatibility INSERT for $table");
        }
        catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Standard SQL insert routine
     *
     * @param string $table including the CMS_TABLE_PREFIX
     * @param array $data
     * @param boolean $replace_cms_url
     * @throws \Doctrine\DBAL\DBALException
     */
    public function insert($table, $data, $replace_cms_url=true)
    {
        try {
            $table_name = substr($table, strlen(CMS_TABLE_PREFIX));

            if (isset($this->app['config']['restore']['tables']['utf-8']['compatibility']['table'][$table_name]) &&
                ($this->app['config']['restore']['tables']['utf-8']['compatibility'][$table_name]['enabled'])) {
                // force the compatibility mode for this table
                return $this->compatibilityInsert($table, $data, $replace_cms_url);
            }
            if ($replace_cms_url) {
                // replace the shematic URL
                $content = array();
                foreach ($data as $key => $value) {
                    $value = is_string($value) ? str_replace('{{ SyncData:CMS_URL }}', CMS_URL, $value) : $value;
                    $content[$key] = $value;
                }
                $data = $content;
            }

            if (isset($this->app['config']['restore']['tables']['utf-8']['force']['table'][$table_name])) {
                $content = array();
                foreach ($data as $key => $value) {
                    if ($this->app['config']['restore']['tables']['utf-8']['force']['table'][$table_name]['enabled'] &&
                        (in_array($key, $this->app['config']['restore']['tables']['utf-8']['force']['table'][$table_name]['field']))) {
                        // force utf-8 encoding
                        $content[$key] = utf8_encode($value);
                        $this->app['monolog']->addDebug("Forced UTF-8 for $table => $key");
                    }
                    else {
                        $content[$key] = $value;
                    }
                }
                $data = $content;
            }

            $this->app['db']->insert($table, $data);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Select the index field content for the given table
     *
     * @param string $table
     * @param string $index_field
     * @throws \Doctrine\DBAL\DBALException
     * @return Ambigous <boolean, unknown>
     */
    public function selectRowsIndexField($table, $index_field)
    {
        try {
            $SQL = "SELECT `$index_field` FROM `$table`";
            $result = $this->app['db']->fetchAll($SQL);
            return (is_array($result)) ? $result : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Compatibility SQL update which submit the bare data without any checks
     *
     * @param string $table including the CMS_TABLE_PREFIX
     * @param array $fields with key and value for the WHERE clause
     * @param array $data to update
     * @param boolean $replace_cms_url
     * @throws \Exception
     * @throws Exception
     */
    protected function compatibilityUpdate($table, $fields, $data, $replace_cms_url=true)
    {
        try {
            $SQL = "UPDATE `$table` SET ";
            $start = true;
            foreach ($data as $key => $value) {
                if (($table === CMS_TABLE_PREFIX.'pages') && (($key === 'menu_title') || ($key == 'page_title'))) {
                    $value = htmlspecialchars($value);
                }
                if ($replace_cms_url) {
                    $value = is_string($value) ? str_replace('{{ SyncData:CMS_URL }}', CMS_URL, $value) : $value;
                }
                if (!$start) {
                    $SQL .= ", ";
                }
                if ($start) {
                    $start = false;
                }
                $SQL .= "`$key`='$value'";
            }
            $SQL .= " WHERE ";
            $start = true;
            foreach ($fields as $key => $value) {
                if (!$start) {
                    $SQL .= " AND ";
                }
                else {
                    $start = false;
                }
                $SQL .= "`$key`='$value'";
            }
            // create a separated MySQL connection
            if ((false === ($db_config = json_decode(@file_get_contents(SYNCDATA_PATH.'/config/doctrine.json'), true))) || !is_array($db_config)) {
                throw new \Exception("Can't read the Doctrine configuration file!");
            }
            // connect MySQL
            if (false === ($link = mysql_connect($db_config['DB_HOST'], $db_config['DB_USERNAME'], $db_config['DB_PASSWORD']))) {
                throw new \Exception("Error connecting to the database: ".mysql_error());
            }
            // select database
            if (!mysql_select_db($db_config['DB_NAME'], $link)) {
                throw new \Exception("Error selecting the database: ".mysql_error());
            }
            // insert the row "as it is"
            if (!mysql_query($SQL, $link)) {
                throw new \Exception("Error compatibility UPDATE: ".mysql_error());
            }
            // close the connection
            mysql_close($link);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Update the specified table with the specified data
     *
     * @param string $table including the CMS_TABLE_PREFIX
     * @param array $fields with key and value for the WHERE clause
     * @param array $data to update
     * @param boolean $replace_cms_url
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update($table, $fields, $data, $replace_cms_url=true)
    {
        try {
            if ($table === CMS_TABLE_PREFIX.'pages') {
                return $this->compatibilityUpdate($table, $fields, $data);
            }
            $update = array();
            foreach ($data as $key => $value)
                if ($replace_cms_url) {
                    $update[$this->app['db']->quoteIdentifier($key)] = is_string($value) ? str_replace('{{ SyncData:CMS_URL }}', CMS_URL, $value) : $value;
                }
                else {
                    $update[$this->app['db']->quoteIdentifier($key)] = $value;
                }
            $this->app['db']->update($table, $update, $fields);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }

    /**
     * Standard SQL to delete rows from a table
     *
     * @param string $table including the CMS_TABLE_PREFIX
     * @param array $fields associative fields and values for the WHERE clause
     * @throws \Doctrine\DBAL\DBALException
     */
    public function delete($table, $fields)
    {
        try {
            $this->app['db']->delete($table, $fields);
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw $e;
        }
    }
}
