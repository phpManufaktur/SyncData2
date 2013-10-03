<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Data\Import;

use phpManufaktur\ConfirmationLog\Data\Confirmation;

class ImportOldLog
{
    protected $app = null;

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
     * Process the import, get all data from the previous confirmation table
     * mod_confirmation_log
     *
     * @throws \Exception
     */
    protected function import()
    {
        try {
            $ConfirmationData = new Confirmation($this->app);

            $skipped_records = 0;
            $added_records = 0;
            $critical_records = 0;

            $SQL = "SELECT * FROM `".CMS_TABLE_PREFIX."mod_confirmation_log`";
            $old_confirmations = $this->app['db']->fetchAll($SQL);
            foreach ($old_confirmations as $old) {
                $data = array(
                    'page_id' => $old['page_id'],
                    'page_type' => $old['page_type'],
                    'second_id' => $old['second_id'],
                    'installation_name' => $old['installation_name'],
                    'page_url' => $old['page_link'],
                    'page_title' => $old['page_title'],
                    'typed_name' => $old['typed_name'],
                    'typed_email' => $old['typed_email'],
                    'confirmed_at' => $old['confirmed_at'],
                    'time_on_page' => -1,
                    'received_at' => date('Y-m-d H:i:s'),
                    'transmitted_at' => $old['transmitted_at'],
                    'user_name' => $old['user_name'],
                    'user_email' => $old['user_email']
                );
                $confirmation_id = -1;
                $ConfirmationData->insert($data, $confirmation_id);
                $this->app['monolog']->addDebug(sprintf('[Import Confirmation] Imported record with old ID %d at new ID %d.',
                    $old['id'], $confirmation_id));

                $checksum = $ConfirmationData->getChecksum($confirmation_id);

                if (false !== ($exists_id = $ConfirmationData->existsChecksum($checksum, $confirmation_id))) {
                    // this checksum exists already!
                    $ConfirmationData->delete($confirmation_id);
                    $skipped_records++;
                    $this->app['monolog']->addInfo(sprintf('[Import Confirmation] Skipped record ID %d, the checksum %s already exists in target!',
                        $old['id'], $checksum));
                    continue;
                }
                $added_records++;
            }
            return $this->app['translator']->trans('Imported %added_records% records, skipped %skipped_records% records which already exists. Please check the logfile for further information.',
                    array('%added_records%' => $added_records, '%skipped_records%' => $skipped_records));
        } catch (\Doctrine\DBAL\DBALException $e) {
            throw new \Exception($e);
        }
    }

    /**
     * Execute the import process and return the result
     *
     * @param Application $app
     */
    public function exec($app)
    {
        $this->app = $app;

        if (!$this->tableExists(CMS_TABLE_PREFIX.'mod_confirmation_log')) {
            // table does not exists
            $app['monolog']->addInfo('The table `mod_confirmation_log` does not exists, import aborted.');
            return $app['translator']->trans('The table `mod_confirmation_log` does not exists, import aborted.');
        }

        return $this->import();
    }
}
