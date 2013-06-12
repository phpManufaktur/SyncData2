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
                    $this->app['monolog']->addInfo(sprintf("Table %s has changed. Register the new checksum and do nothing more because this table has no index and must be updated complete.", $table['table_name']));
                }
                else {
                    // now we check, what has changed
                    if (false === ($backupStatus = $BackupTables->selectTableByBackupID(self::$backup_id, $table['table_name']))) {
                        $this->app['monolog']->addInfo(sprintf("Found no infornations for table %s", $table));
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
                                'content' => ''
                            );
                            // add entry to the synchronize table
                            $sync_id = -1;
                            $SynchronizeTables->insert($data, $sync_id);
                            $this->app['monolog']->addInfo(sprintf("Add DELETE %s for index field %s => %s with the ID %d",
                                $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id));

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
                                'content' => json_encode($record)
                            );
                            // add entry to the synchronize table
                            $sync_id = -1;
                            $SynchronizeTables->insert($data, $sync_id);
                            $this->app['monolog']->addInfo(sprintf("Add UPDATE %s for index field %s => %s with the ID %d",
                                $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id));
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
                    if (md5($calculate_checksum) !== $table_checksum) {
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
                                    'content' => json_encode($record)
                                );
                                // add entry to the synchronize table
                                $sync_id = -1;
                                $SynchronizeTables->insert($data, $sync_id);
                                $this->app['monolog']->addInfo(sprintf("Add INSERT %s for index field %s => %s with the ID %d",
                                    $table['table_name'], $backupRow['index_field'], $backupRow['index_id'], $sync_id));

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
                                $this->app['monolog']->addInfo(sprintf("Added field %s of table %s as index field to the backup tables", $table['index_field'], $table['table_name']));

                                // add the new checksum to the calculated checksum!
                                $calculate_checksum .= $new_checksum;
                            }
                        }
                    }
                    if (md5($calculate_checksum) === $table_checksum) {
                        // all is fine - update the backup master
                        $data = array(
                            'last_checksum' => $table_checksum
                        );
                        $BackupMaster->update($table['id'], $data);
                        $this->app['monolog']->addInfo("Updated the checksum for the backup master table {$table['table_name']}");
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

    public function exec()
    {
        $BackupMaster = new BackupMaster($this->app);
        // first we need the last backup ID
        if (false === (self::$backup_id = $BackupMaster->selectLastBackupID())) {
            $result = "Got no backup ID for processing a check for changed tables and files. Please create a backup first!";
            $this->app['monolog']->addInfo($result);
            return $result;
        }
        if (false === ($tables = $BackupMaster->selectTablesByBackupID(self::$backup_id))) {
            $result = "Found no tables for the backup ID ".self::$backup_id;
            $this->app['monolog']->addInfo($result);
            return $result;
        }
        foreach ($tables as $table) {
            if (!in_array($table['table_name'], $this->app['config']['backup']['tables']['ignore'])) {
                $this->app['monolog']->addInfo("Check table ".$table['table_name']." for changes");
                $this->checkTable($table);
            }
            else {
                // ignore this table
                $this->app['monolog']->addInfo("Ignored table {$table['table_name']}");
            }
        }

        //print_r($tables);
        return 'ok';
    }

}