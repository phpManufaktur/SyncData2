<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Control;


use phpManufaktur\SyncData\Data\SynchronizeClient as SyncClient;
use phpManufaktur\SyncData\Control\Zip\unZip;
use phpManufaktur\SyncData\Data\General;

class SynchronizeClient
{

    protected $app = null;
    protected static $archive_id = null;

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
     * Process the tables for the synchronization
     *
     * @throws \Exception
     */
    protected function processTables()
    {
        if (!file_exists(TEMP_PATH.'/sync/synchronize/tables.json')) {
            throw new \Exception("tables.json does not exists!");
        }
        if (false === ($tables = json_decode(@file_get_contents(TEMP_PATH.'/sync/synchronize/tables.json'), true))) {
            throw new \Exception("Can't decode the tables.json file!");
        }
        $General = new General($this->app);
        foreach ($tables as $table) {
            switch ($table['action']) {
                case 'INSERT':
                    $exists = $General->getRowContent(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    if (isset($exists[$table['index_field']])) {
                        $checksum = $General->getRowContentChecksum(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                        if ($checksum !== $table['checksum']) {
                            $this->app['monolog']->addError(sprintf("Table %s with %s => %s already exists, but the checksum differ! Please check the table!",
                                $table['table_name'], $table['index_field'], $table['index_id']),
                                array('method' => __METHOD__, 'line' => __LINE__));
                        }
                        else {
                            $this->app['monolog']->addInfo(sprintf("Table %s with %s => %s already exists, skipped!",
                                $table['table_name'], $table['index_field'], $table['index_id']),
                                array('method' => __METHOD__, 'line' => __LINE__));
                        }
                        continue;
                    }
                    if (false === ($data = json_decode($this->app['utils']->unsanitizeText($table['content']), true))) {
                        throw new \Exception("Problem decoding json content!");
                    }
                    $General->insert(CMS_TABLE_PREFIX.$table['table_name'], $data);
                    $checksum = $General->getRowContentChecksum(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    if ($checksum !== $table['checksum']) {
                        $this->app['monolog']->addError(sprintf("Table %s INSERT %s => %s successfull, but the checksum differ! Please check the table!",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    else {
                        $this->app['monolog']->addError(sprintf("Table %s INSERT %s => %s successfull",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    break;
                case 'UPDATE':
                    $exists = $General->getRowContent(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    if (!isset($exists[$table['index_field']])) {
                        $this->app['monolog']->addError(sprintf("Table %s with %s => %s does not exists for UPDATE! Please check the table!",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                        continue;
                    }
                    $checksum = $General->getRowContentChecksum(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    if ($checksum === $table['checksum']) {
                        $this->app['monolog']->addInfo(sprintf("Table %s with %s => %s skipped UPDATE, the actual content has the same checksum!",
                                $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                        continue;
                    }
                    if (false === ($data = json_decode($this->app['utils']->unsanitizeText($table['content']), true))) {
                        throw new \Exception("Problem decoding json content!");
                    }
                    $General->update(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']), $data);
                    $checksum = $General->getRowContentChecksum(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    if ($checksum !== $table['checksum']) {
                        $this->app['monolog']->addError(sprintf("Table %s UPDATE %s => %s successfull, but the checksum differ! Please check the table!",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    else {
                        $this->app['monolog']->addError(sprintf("Table %s UPDATE %s => %s successfull",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    break;
                case 'DELETE':
                    $General->delete(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $table['index_id']));
                    $this->app['monolog']->addInfo(sprintf("Table %s DELETE %s => %s executed.",
                            $table['table_name'], $table['index_field'], $table['index_id']),
                        array('method' => __METHOD__, 'line' => __LINE__));
                    break;
            }
        }

    }

    /**
     * @todo missing some validations and checks!
     * @throws \Exception
     */
    protected function processFiles()
    {
        if (!file_exists(TEMP_PATH.'/sync/synchronize/files.json')) {
            throw new \Exception("files.json does not exists!");
        }
        if (false === ($files = json_decode(@file_get_contents(TEMP_PATH.'/sync/synchronize/files.json'), true))) {
            throw new \Exception("Can't decode the files.json file!");
        }
        foreach ($files as $file) {
            if ($file['action'] == 'DELETE') {
                if (file_exists(CMS_PATH.$file['relative_path'])) {
                    @unlink(CMS_PATH.$file['relative_path']);
                    $this->app['monolog']->addInfo("Deleted file ".$file['relative_path'],
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
            }
            else {
                // CHANGED or NEW
                if (file_exists(TEMP_PATH.'/sync/synchronize/CMS'.$file['relative_path'])) {
                    if (!@copy(TEMP_PATH.'/sync/synchronize/CMS'.$file['relative_path'],
                        CMS_PATH.$file['relative_path'])) {
                        $this->app['monolog']->addError("Can't copy file to ".$file['relative_path'],
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    else {
                        if ($file['action'] == 'NEW') {
                            $this->app['monolog']->addInfo("Inserted NEW file ".$file['relative_path'],
                                array('method' => __METHOD__, 'line' => __LINE__));
                        }
                        else {
                            $this->app['monolog']->addInfo("CHANGED file ".$file['relative_path'],
                                array('method' => __METHOD__, 'line' => __LINE__));
                        }
                    }
                }
                else {
                    $this->app['monolog']->addError("MISSING file ".$file['relative_path']." in the SYNC archive!",
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
            }
        }
    }

    /**
     * Main routine to exec the synchronization
     *
     * @throws \Exception
     * @return string
     */
    public function exec()
    {
        try {
            // start SYNC
            $this->app['monolog']->addInfo('Start SYNC', array('method' => __METHOD__, 'line' => __LINE__));

            $SyncClient = new SyncClient($this->app);
            $archive_id = $SyncClient->selectLastArchiveID();
            self::$archive_id = $archive_id+1;

            $zip_path = sprintf('%s/inbox/syncdata_synchronize_%05d.zip', SYNCDATA_PATH, self::$archive_id);
            $md5_path = sprintf('%s/inbox/syncdata_synchronize_%05d.md5', SYNCDATA_PATH, self::$archive_id);
            $md5_archive_path = sprintf('%s/data/synchronize/syncdata_synchronize_%05d.md5', SYNCDATA_PATH, self::$archive_id);
            $zip_archive_path = sprintf('%s/data/synchronize/syncdata_synchronize_%05d.zip', SYNCDATA_PATH, self::$archive_id);
            if (file_exists($zip_path) && file_exists($md5_path)) {
                // ok - expected archive is there, proceed
                if (false === ($md5_origin = file_get_contents($md5_path))) {
                    $result = "Can't read the MD5 checksum file for the SYNC!";
                    $this->app['monolog']->addError($result, array('method' => __METHOD__, 'line' => __LINE__));
                    return $result;
                }
                if (md5_file($zip_path) !== $md5_origin) {
                    $result = "The checksum of the SYNC archive is not equal to the MD5 checksum file value!";
                    $this->app['monolog']->addError($result, array('method' => __METHOD__, 'line' => __LINE__));
                    return $result;
                }
                // check the TEMP directory
                if (file_exists(TEMP_PATH.'/sync') && !$this->app['utils']->rrmdir(TEMP_PATH.'/sync')) {
                    throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/sync'));
                }
                if (!file_exists(TEMP_PATH.'/sync') && (false === @mkdir(TEMP_PATH.'/sync', 0755, true))) {
                    throw new \Exception("Can't create the directory ".TEMP_PATH."/sync");
                }
                // unzip the archive
                $this->app['monolog']->addInfo("Start unzipping $zip_path", array('method' => __METHOD__, 'line' => __LINE__));
                $unZip = new unZip($this->app);
                $unZip->setUnZipPath(TEMP_PATH.'/sync');
                $unZip->extract($zip_path);
                $this->app['monolog']->addInfo("Unzipped $zip_path", array('method' => __METHOD__, 'line' => __LINE__));

                // process the tables
                $this->processTables();

                // process the files
                $this->processFiles();

                // ok - nearly all done
                $data = array(
                    'archive_id' => self::$archive_id,
                    'action' => 'SYNC'
                );
                $SyncClient->insert($data);

                // move the files from the /inbox to /data/synchronize
                if (!file_exists(SYNCDATA_PATH.'/data/synchronize/.htaccess') || !file_exists(SYNCDATA_PATH.'/data/synchronize/.htpasswd')) {
                    $this->app['utils']->createDirectoryProtection(SYNCDATA_PATH.'/data/synchronize');
                }
                if (!@rename($md5_path, $md5_archive_path)) {
                    $this->app['monolog']->addError("Can't save the MD5 checksum file in /data/synchronize!",
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
                if (!@rename($zip_path, $zip_archive_path)) {
                    $this->app['monolog']->addError("Can't save the synchronize archive in /data/synchronize!",
                        array('method' => __METHOD__, 'line' => __LINE__));
                }

                // delete the temp directories
                $directories = array('/backup', '/restore', '/sync', '/unzip');
                foreach ($directories as $directory) {
                    if (file_exists(TEMP_PATH.$directory) && (true !== $this->app['utils']->rrmdir(TEMP_PATH.$directory))) {
                        throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.directory));
                    }
                }
                $this->app['monolog']->addInfo("SYNC finished!", array('method' => __METHOD__, 'line' => __LINE__));
            }
            else {
                $result = sprintf('Missing archive file %s and checksum file %s in the inbox.', basename($zip_path), basename($md5_path));
                $this->app['monolog']->addInfo($result, array('method' => __METHOD__, 'line' => __LINE__));
                return $result;
            }

            return 'SYNC finished';
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }
}