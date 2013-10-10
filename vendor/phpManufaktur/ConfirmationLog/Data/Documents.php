<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data;

class Documents
{
    protected $app = null;
    protected static $table_name = null;

    /**
     * Constructor - this class can be called as Silex\Application or as
     * SyncData\Application, therefore we dont specify the $app variable!
     *
     * @param Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        self::$table_name = CMS_TABLE_PREFIX.'kit2_confirmation_documents';
    }

    /**
     * Create the table
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function createTable ()
    {
        $table = self::$table_name;
        $SQL = <<<EOD
    CREATE TABLE IF NOT EXISTS `$table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `page_id` INT(11) NOT NULL DEFAULT '-1',
        `page_type` ENUM('PAGE','TOPICS','NEWS','OTHER') NOT NULL DEFAULT 'PAGE',
        `second_id` INT(11) NOT NULL DEFAULT '0',
        `page_title` VARCHAR(255) NOT NULL DEFAULT '',
        `modified_when` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        `timestamp` TIMESTAMP,
        PRIMARY KEY (`id`)
    )
    COMMENT='Articles which contain a Droplet or a kitCommand for confirmation'
    ENGINE=InnoDB
    AUTO_INCREMENT=1
    DEFAULT CHARSET=utf8
    COLLATE='utf8_general_ci'
EOD;
        try {
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Created table '".self::$table_name."'",
                array('method' => __METHOD__, 'line' => __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Delete table - switching check for foreign keys off before executing
     *
     * @throws \Exception
     */
    public function dropTable()
    {
        try {
            $table = self::$table_name;
            $SQL = <<<EOD
    SET foreign_key_checks = 0;
    DROP TABLE IF EXISTS `$table`;
    SET foreign_key_checks = 1;
EOD;
            $this->app['db']->query($SQL);
            $this->app['monolog']->addInfo("Drop table '".self::$table_name."'", array(__METHOD__, __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Check if the given $table exists
     *
     * @param string $table
     * @throws \Exception
     * @return boolean
     */
    protected function tableExists($table)
    {
        try {
            $query = $this->app['db']->query("SHOW TABLES LIKE '$table'");
            return (false !== ($row = $query->fetch())) ? true : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Check wether a document is already registered.
     *
     * @param integer $page_type
     * @param integer $page_id
     * @param integer $second_id
     * @param integer reference $document_id
     * @param string reference $document_last_modified
     * @throws \Exception
     * @return boolean
     */
    public function existsDocument($page_type, $page_id, $second_id, &$document_id=-1, &$document_last_modified='0000-00-00 00:00:00')
    {
        try {
            $SQL = "SELECT `id`, `modified_when` FROM `".self::$table_name."` WHERE `page_type`='$page_type' AND `page_id`='$page_id' AND `second_id`='$second_id'";
            $result = $this->app['db']->fetchAssoc($SQL);
            if (isset($result['id'])) {
                $document_id = $result['id'];
                $document_last_modified = $result['modified_when'];
                return true;
            }
            else {
                return false;
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Insert a new document
     *
     * @param array $data
     * @param integer reference $document_id
     * @throws \Exception
     */
    public function insert($data, &$document_id=-1)
    {
        try {
            $insert = array();
            foreach ($data as $key => $value) {
                if (($key == 'id') || ($key == 'timestamp')) continue;
                $insert[$key] = (is_string($value)) ? $this->app['utils']->sanitizeText($value) : $value;
            }
            $this->app['db']->insert(self::$table_name, $insert);
            $document_id = $this->app['db']->lastInsertId();
            return $document_id;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Update a record
     *
     * @param integer $id
     * @param array $data
     * @throws \Exception
     */
    public function update($id, $data)
    {
        try {
            $update = array();
            foreach ($data as $key => $value) {
                if (($key == 'id') || ($key == 'timestamp')) continue;
                $update[$key] = (is_string($value)) ? $this->app['utils']->sanitizeText($value) : $value;
            }
            $this->app['db']->update(self::$table_name, $update, array('id' => $id));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Parse Pages, News and Topics for Confirmation Droplets / kitCommands and
     * add all hits to the table kit2_confirmation_article.
     * Also update the modified_when field of existing and changed articles.
     *
     * @throws \Exception
     */
    public function parseForNeededConfirmations()
    {
        try {
            $this->app['monolog']->addDebug('Start parsing pages, news and topics for confirmation Droplets or kitCommands',
                array(__METHOD__, __LINE__));
            $wysiwyg = CMS_TABLE_PREFIX.'mod_wysiwyg';
            $pages = CMS_TABLE_PREFIX."pages";

            // check the PAGES
            $SQL = "SELECT $pages.`page_id`, $pages.`modified_when`, $pages.`page_title` FROM $pages, $wysiwyg WHERE $pages.`page_id`=$wysiwyg.`page_id` AND (".
                "(`content` LIKE '%[[syncdata_confirmation]]%') OR (`content` LIKE '%[[syncdata_confirmation?%]]%') OR ".
                "(`content` LIKE '%[[confirmation_log]]%') OR (`content` LIKE '%[[confirmation_log?%]]%') OR ".
                "(`content` LIKE '%~~ confirmation ~~%') OR (`content` LIKE '%~~ confirmation %~~%'))";

            $results = $this->app['db']->fetchAll($SQL);

            foreach ($results as $result) {
                $document_id = -1;
                $document_last_modified = '0000-00-00 00:00:00';
                if (!$this->existsDocument('PAGE', $result['page_id'], 0, $document_id, $document_last_modified)) {
                    // insert a new record
                    $data = array(
                        'page_id' => $result['page_id'],
                        'page_type' => 'PAGE',
                        'second_id' => 0,
                        'page_title' => $this->app['utils']->unsanitizeText($result['page_title']),
                        'modified_when' => date('Y-m-d H:i:s', $result['modified_when'])
                    );
                    $this->insert($data, $document_id);
                    $this->app['monolog']->addDebug("Add the page ID {$result['page_id']} to ".self::$table_name,
                        array(__METHOD__, __LINE__));
                }
                elseif ($document_last_modified != date('Y-m-d H:i:s', $result['modified_when'])) {
                    // update an existing record
                    $data = array(
                        'page_title' => $this->app['utils']->unsanitizeText($result['page_title']),
                        'modified_when' => date('Y-m-d H:i:s', $result['modified_when'])
                    );
                    $this->update($document_id, $data);
                    $this->app['monolog']->addDebug("Updated the page ID {$result['page_id']} at ".self::$table_name,
                        array(__METHOD__, __LINE__));
                }
            }

            // check the NEWS
            if ($this->tableExists(CMS_TABLE_PREFIX.'mod_news_posts')) {
                $SQL = "SELECT `page_id`, `post_id`, `posted_when`, `title` FROM `".CMS_TABLE_PREFIX."mod_news_posts` WHERE (".
                    "(`content_long` LIKE '%[[syncdata_confirmation]]%') OR (`content_long` LIKE '%[[syncdata_confirmation?%]]%') OR ".
                    "(`content_long` LIKE '%[[confirmation_log]]%') OR (`content_long` LIKE '%[[confirmation_log?%]]%') OR ".
                    "(`content_long` LIKE '%~~ confirmation ~~%') OR (`content_long` LIKE '%~~ confirmation %~~%'))";

                $results = $this->app['db']->fetchAll($SQL);

                foreach ($results as $result) {
                    $document_id = -1;
                    $document_last_modified = '0000-00-00 00:00:00';
                    if (!$this->existsDocument('NEWS', $result['page_id'], $result['post_id'], $document_id, $document_last_modified)) {
                        // insert a new record
                        $data = array(
                            'page_id' => $result['page_id'],
                            'page_type' => 'NEWS',
                            'second_id' => $result['post_id'],
                            'page_title' => $this->app['utils']->unsanitizeText($result['title']),
                            'modified_when' => date('Y-m-d H:i:s', $result['posted_when'])
                        );
                        $this->insert($data, $document_id);
                        $this->app['monolog']->addDebug("Add the NEWS ID {$result['post_id']} to ".self::$table_name,
                            array(__METHOD__, __LINE__));
                    }
                    elseif ($document_last_modified != date('Y-m-d H:i:s', $result['posted_when'])) {
                        // update an existing record
                        $data = array(
                            'page_title' => $this->app['utils']->unsanitizeText($result['title']),
                            'modified_when' => date('Y-m-d H:i:s', $result['posted_when'])
                        );
                        $this->update($document_id, $data);
                        $this->app['monolog']->addDebug("Updated the NEWS ID {$result['post_id']} at ".self::$table_name,
                            array(__METHOD__, __LINE__));
                    }
                }
            }

            // check the TOPICS
            if ($this->tableExists(CMS_TABLE_PREFIX.'mod_topics')) {
                $SQL = "SELECT `page_id`, `topic_id`, `posted_modified`, `title` FROM `".CMS_TABLE_PREFIX."mod_topics` WHERE (".
                    "(`content_long` LIKE '%[[syncdata_confirmation]]%') OR (`content_long` LIKE '%[[syncdata_confirmation?%]]%') OR ".
                    "(`content_long` LIKE '%[[confirmation_log]]%') OR (`content_long` LIKE '%[[confirmation_log?%]]%') OR ".
                    "(`content_long` LIKE '%~~ confirmation ~~%') OR (`content_long` LIKE '%~~ confirmation %~~%'))";

                $results = $this->app['db']->fetchAll($SQL);

                foreach ($results as $result) {
                    $document_id = -1;
                    $document_last_modified = '0000-00-00 00:00:00';
                    if (!$this->existsDocument('TOPICS', $result['page_id'], $result['topic_id'], $document_id, $document_last_modified)) {
                        // insert a new record
                        $data = array(
                            'page_id' => $result['page_id'],
                            'page_type' => 'TOPICS',
                            'second_id' => $result['topic_id'],
                            'page_title' => $this->app['utils']->unsanitizeText($result['title']),
                            'modified_when' => date('Y-m-d H:i:s', $result['posted_modified'])
                        );
                        $this->insert($data, $document_id);
                        $this->app['monolog']->addDebug("Add the TOPICS ID {$result['topic_id']} to ".self::$table_name,
                            array(__METHOD__, __LINE__));
                    }
                    elseif ($document_last_modified != date('Y-m-d H:i:s', $result['posted_modified'])) {
                        // update an existing record
                        $data = array(
                            'page_title' => $this->app['utils']->unsanitizeText($result['title']),
                            'modified_when' => date('Y-m-d H:i:s', $result['posted_modified'])
                        );
                        $this->update($document_id, $data);
                        $this->app['monolog']->addDebug("Updated the TOPICS ID {$result['topic_id']} at ".self::$table_name,
                            array(__METHOD__, __LINE__));
                    }
                }
            }
            $this->app['monolog']->addDebug('Finished parsing pages, news and topics for confirmation Droplets or kitCommands',
                array(__METHOD__, __LINE__));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Check if pages, news or topics are more actual than the documents table
     *
     * @throws \Exception
     * @return boolean
     */
    public function checkIfDocumentsNeedUpdate()
    {
        try {
            $SQL = "SELECT MAX(`modified_when`) FROM `".self::$table_name."`";
            $date = $this->app['db']->fetchColumn($SQL);
            if (is_null($date)) {
                // table is probably empty ...
                $this->app['monolog']->addDebug("$SQL return NULL, nothing to do!", array(__METHOD__, __LINE__));
                return true;
            }
            $modified_when = strtotime($date);

            // check the pages
            $SQL = "SELECT MAX(`modified_when`) FROM `".CMS_TABLE_PREFIX."pages`";
            if ((null != ($date = $this->app['db']->fetchColumn($SQL))) && ($date > $modified_when)) {
                $this->app['monolog']->addDebug("One or more pages are modified, ".self::$table_name." need a update.",
                    array(__METHOD__, __LINE__));
                return true;
            }

            if ($this->tableExists(CMS_TABLE_PREFIX.'mod_news_post')) {
                $SQL = "SELECT MAX(`posted_when`) FROM `".CMS_TABLE_PREFIX."mod_news_post`";
                if ((null != ($date = $this->app['db']->fetchColumn($SQL))) && ($date > $modified_when)) {
                    $this->app['monolog']->addDebug("One or more NEWS articles are modified, ".self::$table_name." need a update.",
                        array(__METHOD__, __LINE__));
                    return true;
                }
            }

            if ($this->tableExists(CMS_TABLE_PREFIX.'mod_topics')) {
                $SQL = "SELECT MAX(`posted_modified`) FROM `".CMS_TABLE_PREFIX."mod_topics`";
                if ((null != ($date = $this->app['db']->fetchColumn($SQL))) && ($date > $modified_when)) {
                    $this->app['monolog']->addDebug("One or more TOPICS arcticles are modified, ".self::$table_name." need a update.",
                        array(__METHOD__, __LINE__));
                    return true;
                }
            }
            $this->app['monolog']->addDebug("No update needed for ".self::$table_name, array(__METHOD__, __LINE__));
            return false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Get all titles from the table in descending order of the modification date
     *
     * @throws \Exception
     * @return Ambigous <boolean, multitype:NULL >
     */
    public function getAllTitles()
    {
        try {
            $SQL = "SELECT `page_title` FROM `".self::$table_name."` ORDER BY `modified_when` DESC";
            $results = $this->app['db']->fetchAll($SQL);
            $titles = array();
            foreach ($results as $result) {
                $titles[] = $this->app['utils']->unsanitizeText($result['page_title']);
            }
            return (!empty($titles)) ? $titles : false;
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }
}
