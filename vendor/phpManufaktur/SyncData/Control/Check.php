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

use phpManufaktur\SyncData\Control\Application;
use phpManufaktur\SyncData\Data\BackupMaster;
use phpManufaktur\SyncData\Data\General;
use phpManufaktur\SyncData\Data\BackupTables;
use phpManufaktur\SyncData\Data\SynchronizeTables;
use phpManufaktur\SyncData\Data\SynchronizeMaster;
use phpManufaktur\SyncData\Data\BackupFiles;
use phpManufaktur\SyncData\Data\SynchronizeFiles;

/**
 * Check for changes in the actual installation - compare with the last
 * Backup archive and track all changes
 *
 * @author ralf.hertsch@phpmanufaktur.de
 *
 */
class Check
{

    protected $app = null;
    protected static $backup_id = null;

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
     * @return the $backup_id
     */
    public static function getBackupID ()
    {
        return Check::$backup_id;
    }

      /**
     * @param Ambigous <\phpManufaktur\SyncData\Data\Ambigous, boolean, unknown> $backup_id
     */
    public static function setBackupID ($backup_id)
    {
        Check::$backup_id = $backup_id;
    }

      /**
     * Check the given table for different checksums
     *
     * @param string $table without CMS_TABLE_PREFIX
     * @throws Exception
     */
    protected function checkTable($table)
    {
        try {
            $General = new General($this->app);
            $BackupTables = new BackupTables($this->app);
            $BackupMaster = new BackupMaster($this->app);
            $SynchronizeTables = new SynchronizeTables($this->app);

            // get the actual checksum of the table
            $table_checksum = $General->getTableContentChecksum(CMS_TABLE_PREFIX.$table['table_name']);
            if ($table_checksum !== $table['last_checksum']) {
                // the checksum of the table has changed
                if ($table['index_field'] === BackupMaster::NO_INDEX_FIELD) {
                    // this table has no index fields an can only updated complete
                    $data = array(
                        'last_checksum' => $table_checksum
                    );
                    $BackupMaster->update($table['id'], $data);
                    $this->app['monolog']->addInfo(sprintf("Table %s has changed. Register the new checksum and do nothing more because this table has no index and must be updated complete.", $table['table_name']),
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
                else {
                    // now we check, what has changed
                    if (false === ($backupStatus = $BackupTables->selectTableByBackupID(self::$backup_id, $table['table_name']))) {
                        $this->app['monolog']->addInfo(sprintf("Found no infornations for table %s", $table),
                            array('method' => __METHOD__, 'line' => __LINE__));
                        return false;
                    }
                    $calculate_checksum = '';
                    $backup_index_ids = array();
                    foreach ($backupStatus as $backupRow) {
                        // first we loop through the existing backup table
                        $backup_index_ids[] = $backupRow['index_id'];
                        if (false === ($new_checksum = $General->getRowContentChecksum(CMS_TABLE_PREFIX.$table['table_name'], array($backupRow['index_field'] => $backupRow['index_id'])))) {
                            // no checksum - we assume that the row no longer exists!
                            $data = array(
                                'backup_id' => self::$backup_id,
                                'index_field' => $backupRow['index_field'],
                                'index_id' => $backupRow['index_id'],
                                'checksum' => '',
                                'table_name' => $table['table_name'],
                                'action' => 'DELETE',
                                'content' => '',
                                'sync_status' => 'CHECKED'
                            );
                            // add entry to the synchronize table
                            $sync_id = -1;
                            $SynchronizeTables->insert($data, $sync_id);
                            $this->app['monolog']->addInfo(sprintf("Add DELETE %s for index field %s => %s with the ID %d",
                                $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id),
                                array('method' => __METHOD__, 'line' => __LINE__));

                        }
                        elseif ($new_checksum !== $backupRow['last_checksum']) {
                            $calculate_checksum .= $new_checksum;
                            if (false === ($record = $General->getRowContent(CMS_TABLE_PREFIX.$table['table_name'], array($backupRow['index_field'] => $backupRow['index_id'])))) {
                                throw new \Exception(sprintf("Can't read the row content for table %s by select %s and %s", $table['table_name'], $backupRow['index_field'], $backupRow['index_id']));
                            }
                            $data = array(
                                'backup_id' => self::$backup_id,
                                'index_field' => $backupRow['index_field'],
                                'index_id' => $backupRow['index_id'],
                                'checksum' => $new_checksum,
                                'table_name' => $table['table_name'],
                                'action' => 'UPDATE',
                                'content' => json_encode($record),
                                'sync_status' => 'CHECKED'
                            );
                            // add entry to the synchronize table
                            $sync_id = -1;
                            $SynchronizeTables->insert($data, $sync_id);
                            $this->app['monolog']->addInfo(sprintf("Add UPDATE %s for index field %s => %s with the ID %d",
                                $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id),
                                array('method' => __METHOD__, 'line' => __LINE__));
                            // update the backup tables
                            $data = array(
                                'last_checksum' => $new_checksum
                            );
                            $BackupTables->update($backupRow['id'], $data);

                        }
                        else {
                            $calculate_checksum .= $new_checksum;
                        }
                    }
                    if (md5($calculate_checksum) != $table_checksum) {
                        // we have to check for inserted records!
                        if (false === ($rows = $General->selectRowsIndexField(CMS_TABLE_PREFIX.$table['table_name'], $table['index_field']))) {
                            throw new \Exception("Got no data from table {$table['table_name']}");
                        }
                        foreach ($rows as $row) {
                            if (!in_array($row[$table['index_field']], $backup_index_ids)) {
                                // add a new row to the synchronize table
                                if (false === ($record = $General->getRowContent(CMS_TABLE_PREFIX.$table['table_name'], array($table['index_field'] => $row[$table['index_field']])))) {
                                    throw new \Exception(sprintf("Can't read the row content for table %s by select %s and %s", $table['table_name'], $table['index_field'], $row[$table['index_field']]));
                                }
                                $new_checksum = md5(str_ireplace(CMS_URL, '{{ SyncData:CMS_URL }}', implode(',', $record)));
                                $data = array(
                                    'backup_id' => self::$backup_id,
                                    'index_field' => $table['index_field'],
                                    'index_id' => $row[$table['index_field']],
                                    'checksum' => $new_checksum,
                                    'table_name' => $table['table_name'],
                                    'action' => 'INSERT',
                                    'content' => json_encode($record),
                                    'sync_status' => 'CHECKED'
                                );
                                // add entry to the synchronize table
                                $sync_id = -1;
                                $SynchronizeTables->insert($data, $sync_id);
                                $this->app['monolog']->addInfo(sprintf("Add INSERT %s for index field %s => %s with the ID %d",
                                    $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id),
                                    array('method' => __METHOD__, 'line' => __LINE__));

                                // update the backup tables ???
                                $data = array(
                                    'backup_id' => self::$backup_id,
                                    'table_name' => $table['table_name'],
                                    'index_field' => $table['index_field'],
                                    'index_id' => $row[$table['index_field']],
                                    'origin_checksum' => $new_checksum,
                                    'last_checksum' => $new_checksum,
                                    'action' => 'SYNCHRONIZE'
                                );
                                $BackupTables->insert($data);
                                $this->app['monolog']->addInfo(sprintf("Added field %s of table %s as index field to the backup tables", $table['index_field'], $table['table_name']),
                                    array('method' => __METHOD__, 'line' => __LINE__));

                                // add the new checksum to the calculated checksum!
                                $calculate_checksum .= $new_checksum;
                            }
                        }
                    }
                    if (md5($calculate_checksum) == $table_checksum) {
                        // all is fine - update the backup master
                        $data = array(
                            'last_checksum' => $table_checksum
                        );
                        $BackupMaster->update($table['id'], $data);
                        $this->app['monolog']->addInfo("Updated the checksum for the backup master table {$table['table_name']}",
                        array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    else {
                        // big problem - the checksum should be equal!
                        throw new \Exception("Problem: all steps done, but the calculated checksum differs from the real checksum for the table {$table['table_name']}!");
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Try to get the primary index field of the given table
     *
     * @param string $table
     * @return Ambigous <string, boolean> return the name of the field or false
     */
    protected function getPrimaryIndexField($table)
    {
        $General = new General($this->app);
        $indexes = $General->listTableIndexes($table);
        $indexField = false;
        foreach ($indexes as $index) {
            if ($index->isPrimary()) {
                $columns = $index->getColumns();
                $indexField = $columns[0];
            }
        }
        return $indexField;
    }

    /**
     * Loop through the backup archive and check the registered files for changes,
     * removing, check for new files and add them
     *
     * @throws \Exception
     * @return boolean
     */
    protected function checkFiles()
    {
        try {
            $BackupFiles = new BackupFiles($this->app);
            $SynchronizeFiles = new SynchronizeFiles($this->app);
            if (false === ($files = $BackupFiles->selectFilesByBackupID(self::$backup_id))) {
                $this->app['monolog']->addInfo("Found no files to process!",
                    array('method' => __METHOD__, 'line' => __LINE__));
                return false;
            }
            $backup_files = array();
            foreach ($files as $file) {
                if (file_exists(CMS_PATH.$file['relative_path'])) {
                    // the file still exists
                    $new_checksum = md5_file(CMS_PATH.$file['relative_path']);
                    if ($new_checksum !== $file['file_checksum_last']) {
                        // the file has changed
                        $data = array(
                            'file_checksum_last' => $new_checksum
                        );
                        $BackupFiles->update($file['id'], $data);

                        $data = array(
                            'backup_id' => self::$backup_id,
                            'relative_path' => $file['relative_path'],
                            'file_name' => $file['file_name'],
                            'file_checksum' => $new_checksum,
                            'file_date' => date('Y-m-d H:i:s', filemtime(CMS_PATH.$file['relative_path'])),
                            'file_size' => filesize(CMS_PATH.$file['relative_path']),
                            'action' => 'CHANGED',
                            'sync_status' => 'CHECKED'
                        );
                        $SynchronizeFiles->insert($data);
                        $this->app['monolog']->addInfo("The file {$file['relative_path']} has changed.",
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                    $backup_files[] = CMS_PATH.$file['relative_path'];
                }
                elseif (!$SynchronizeFiles->isFileMarkedAsDeleted($file['relative_path'], self::$backup_id)) {
                    $data = array(
                        'backup_id' => self::$backup_id,
                        'relative_path' => $file['relative_path'],
                        'file_name' => $file['file_name'],
                        'file_checksum' => '',
                        'file_date' => $file['file_date'],
                        'file_size' => $file['file_size'],
                        'action' => 'DELETE',
                        'sync_status' => 'CHECKED'
                    );
                    $SynchronizeFiles->insert($data);
                    $this->app['monolog']->addInfo("The file {$file['relative_path']} does no longer exists.",
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
            }
            // now we check for new files
            $stack = array();
            $stack[] = CMS_PATH;
            while ($stack) {
                $thisdir = array_pop($stack);
                if (false !== ($dircont = scandir($thisdir))) {
                    $i=0;
                    while (isset($dircont[$i])) {
                        if ($dircont[$i] !== '.' && $dircont[$i] !== '..') {
                            $current_file = "{$thisdir}/{$dircont[$i]}";
                            if (is_file($current_file) && !in_array($current_file, $backup_files) &&
                                !in_array(basename($current_file), $this->app['config']['backup']['files']['ignore'])) {
                                // this is a new file
                                $checksum = md5_file($current_file);
                                $file_date = date('Y-m-d H:i:s', filemtime($current_file));
                                $file_size = filesize($current_file);
                                $data = array(
                                    'backup_id' => self::$backup_id,
                                    'date' => date('Y-m-d H:i:s'),
                                    'relative_path' => substr($current_file, strlen(CMS_PATH)),
                                    'file_name' => basename($current_file),
                                    'file_checksum_origin' => $checksum,
                                    'file_checksum_last' => $checksum,
                                    'file_date' => $file_date,
                                    'file_size' => $file_size,
                                    'action' => 'SYNCHRONIZE'
                                );
                                $BackupFiles->insert($data);
                                $data = array(
                                    'backup_id' => self::$backup_id,
                                    'relative_path' => substr($current_file, strlen(CMS_PATH)),
                                    'file_name' => basename($current_file),
                                    'file_checksum' => $checksum,
                                    'file_date' => $file_date,
                                    'file_size' => $file_size,
                                    'action' => 'NEW',
                                    'sync_status' => 'CHECKED'
                                );
                                $SynchronizeFiles->insert($data);
                                $this->app['monolog']->addInfo("Added the new file $current_file to the Synchronize files",
                                    array('method' => __METHOD__, 'line' => __LINE__));
                            }
                            elseif (is_dir($current_file) && !in_array(substr($current_file, strlen(CMS_PATH)+1),
                                    $this->app['config']['backup']['directories']['ignore']['directory']) &&
                                    !in_array(substr($current_file, strrpos($current_file, DIRECTORY_SEPARATOR)+1),
                                    $this->app['config']['backup']['directories']['ignore']['subdirectory'])) {
                                // directory is not ignored so dive in
                                $stack[] = $current_file;
                            }
                        }
                        $i++;
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Action handler for the class Check
     *
     * @throws \Exception
     * @return string
     */
    public function exec()
    {
        try {
            // first check the tables
            $BackupMaster = new BackupMaster($this->app);
            $General = new General($this->app);
            $SynchronizeMaster = new SynchronizeMaster($this->app);
            // first we need the last backup ID
            if (false === (self::$backup_id = $BackupMaster->selectLastBackupID())) {
                $result = "Got no backup ID for processing a check for changed tables and files. Please create a backup first!";
                $this->app['monolog']->addInfo($result, array('method' => __METHOD__, 'line' => __LINE__));
                return $result;
            }
            if (false === ($tables = $BackupMaster->selectTablesByBackupID(self::$backup_id))) {
                $result = "Found no tables for the backup ID ".self::$backup_id;
                $this->app['monolog']->addInfo($result, array('method' => __METHOD__, 'line' => __LINE__));
                return $result;
            }
            $backup_tables = array();
            foreach ($tables as $table) {
                // loop through the backup tables
                if (!in_array($table['table_name'], $this->app['config']['backup']['tables']['ignore'])) {
                    $this->app['monolog']->addInfo("Check table ".$table['table_name']." for changes",
                        array('method' => __METHOD__, 'line' => __LINE__));
                    if ($General->tableExists(CMS_TABLE_PREFIX.$table['table_name'])) {
                        $this->checkTable($table);
                        $backup_tables[] = $table['table_name'];
                    }
                    elseif (!$SynchronizeMaster->isTableMarkedAsDeleted($table['table_name'], self::$backup_id)) {
                        // the table does no longer exists and is not marked for delete!
                        $data = array(
                            'backup_id' => self::$backup_id,
                            'checksum' => '',
                            'table_name' => $table['table_name'],
                            'action' => 'DELETE',
                            'sql_create_table' => '',
                            'sync_status' => 'CHECKED'
                        );
                        $SynchronizeMaster->insert($data);
                        $this->app['monolog']->addInfo("Add table {$table['table_name']} to synchronize master",
                            array('method' => __METHOD__, 'line' => __LINE__));
                    }
                }
                else {
                    // ignore this table
                    $this->app['monolog']->addInfo("Ignored table {$table['table_name']}",
                        array('method' => __METHOD__, 'line' => __LINE__));
                }
            }

            // check for new tables - strip table prefix!
            $tables = $General->getTables(true);
            foreach ($tables as $table) {
                if (!in_array($table, $this->app['config']['backup']['tables']['ignore']) &&
                    !in_array($table, $backup_tables)) {
                    // new table detected!
                    $SQL = $General->getCreateTableSQL(CMS_TABLE_PREFIX.$table);
                    $indexField = (false === $idx = $this->getPrimaryIndexField(CMS_TABLE_PREFIX.$table)) ? 'NO_INDEX_FIELD' : $idx;
                    $checksum = $General->getTableContentChecksum(CMS_TABLE_PREFIX.$table);
                    // add the table to the backup master (with actual date!)
                    $data = array(
                        'backup_id' => self::$backup_id,
                        'date' => date('Y-m-d H:i:s'),
                        'sql_create_table' => $SQL,
                        'index_field' => $indexField,
                        'origin_checksum' => $checksum,
                        'last_checksum' => $checksum,
                        'table_name' => $table
                    );
                    $BackupMaster->insert($data);
                    $this->app['monolog']->addInfo("Add table $table to backup master", array('method' => __METHOD__, 'line' => __LINE__));
                    // add the table to synchronize master
                    $data = array(
                        'backup_id' => self::$backup_id,
                        'checksum' => $checksum,
                        'table_name' => $table,
                        'action' => 'CREATE',
                        'sql_create_table' => $SQL,
                        'sync_status' => 'CHECKED'
                    );
                    $SynchronizeMaster->insert($data);
                    $this->app['monolog']->addInfo('Add table $table to synchronize master', array('method' => __METHOD__, 'line' => __LINE__));
                }
            }

            // now we check the files
            $this->checkFiles();

            return 'ok';
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

}
