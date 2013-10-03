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

use phpManufaktur\ConfirmationLog\Data\Confirmation;
use phpManufaktur\SyncData\Control\Zip\Zip;
use phpManufaktur\SyncData\Control\Zip\unZip;

class Confirmations
{
    protected $app = null;
    protected $ConfirmationData = null;

    public function __construct($app)
    {
        $this->app = $app;
        $this->ConfirmationData = new Confirmation($app);
    }

    /**
     * Process all confirmations with status 'PENDING', create a archive and
     * checksum file, update with 'submitted_at' but dont change the status.
     * Place the files in the /archive and the /outbox folder.
     *
     * @throws \Exception
     */
    public function sendConfirmations()
    {
        $this->app['monolog']->addInfo('Start collecting and sending of confirmations');
        $pendings = $this->ConfirmationData->selectPendings();
        if (count($pendings) < 1) {
            // nothing to do
            $message = 'No pending confirmations available for sending.';
            $this->app['monolog']->addInfo($message);
            return $this->app['translator']->trans($message);
        }

        $submitted_at = date('Y-m-d H:i:s');

        $data = array(
            'date' => $submitted_at,
            'installation_name' => $this->app['config']['CMS']['INSTALLATION_NAME'],
            'client_id' => $this->app['config']['general']['client_id'],
            'confirmations' => $pendings
        );

        // delete an existing temp directory an all content
        if (file_exists(TEMP_PATH.'/confirmation') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/confirmation'))) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/confirmation'));
        }
        // create the temp directory
        if (!file_exists(TEMP_PATH.'/confirmation') && (false === @mkdir(TEMP_PATH.'/confirmation', 0755, true))) {
            throw new \Exception("Can't create the directory ".TEMP_PATH."/confirmation");
        }
        $this->app['monolog']->addInfo('Prepared temporary directory for the confirmations',
            array('method' => __METHOD__, 'line' => __LINE__));

        $json = $this->app['utils']->JSONFormat($data);

        if (!file_put_contents(TEMP_PATH.'/confirmation/confirmation.json', $json)) {
            throw new \Exception("Can't write the confirmation.json file!");
        }

        $timestamp = time();
        $confirmation_date = date('Ymd_Hi', $timestamp);
        $confirmation_zip = SYNCDATA_PATH."/data/confirmation/confirmation_$confirmation_date.zip";
        $confirmation_zip_out = SYNCDATA_PATH."/outbox/confirmation_$confirmation_date"."_".$this->app['config']['general']['client_id'].".zip";
        $confirmation_md5 = SYNCDATA_PATH."/data/confirmation/confirmation_$confirmation_date.md5";
        $confirmation_md5_out = SYNCDATA_PATH."/outbox/confirmation_$confirmation_date"."_".$this->app['config']['general']['client_id'].".md5";

        if (!file_exists(SYNCDATA_PATH.'/data/confirmation')) {
            if (!@mkdir(SYNCDATA_PATH.'/data/confirmation', 0755, true)) {
                throw new \Exception("Can't create the directory ".SYNCDATA_PATH.'/data/confirmation');
            }
        }
        if (!file_exists(SYNCDATA_PATH.'/data/confirmation/.htaccess') || !file_exists(SYNCDATA_PATH.'/data/confirmation/.htpasswd')) {
            $this->app['utils']->createDirectoryProtection(SYNCDATA_PATH.'/data/confirmation');
        }

        if (file_exists($confirmation_zip)) {
            @unlink($confirmation_zip);
        }

        $zip = new Zip($this->app);
        $zip->zipDir(TEMP_PATH.'/confirmation', $confirmation_zip);

        $md5 = md5_file($confirmation_zip);
        if (!file_put_contents($confirmation_md5, $md5)) {
            throw new \Exception("Can't write the MD5 checksum file for the confirmation!");
        }

        // delete an existing backup directory an all content
        if (file_exists(TEMP_PATH.'/confirmation') && (true !== $this->app['utils']->rrmdir(TEMP_PATH.'/confirmation'))) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/confirmation'));
        }

        // copy the files to the /outbox
        if (!@copy($confirmation_md5, $confirmation_md5_out)) {
            throw new \Exception("Can't copy the MD5 file to the /outbox!");
        }
        if (!@copy($confirmation_zip, $confirmation_zip_out)) {
            throw new \Exception("Can't copy the ZIP file to the /outbox!");
        }

        // loop through the confirmations and set 'transmitted_at'
        $update = array(
            'transmitted_at' => date('Y-m-d H:i:s', $timestamp),
            'status' => 'SUBMITTED'
        );
        foreach ($pendings as $pending) {
            $this->ConfirmationData->update($pending['id'], $update);
        }

        $this->app['monolog']->addInfo('Collection of confirmations finished.');
        return $this->app['translator']->trans('Created a confirmation archive for submission.');
    }

    public function getConfirmations()
    {
        // start restore
        $this->app['monolog']->addInfo('Start receiving confirmations', array(__METHOD__, __LINE__));

        // check the /inbox
        $files = array();
        $directory_handle = dir(SYNCDATA_PATH.'/inbox');
        while (false !== ($file = $directory_handle->read())) {
            // get all files into an array
            if (($file == '.') || ($file == '..')) continue;
            $path = $this->app['utils']->sanitizePath(SYNCDATA_PATH."/inbox/$file");
            if (is_dir($path)) {
                // RESTORE does not scan subdirectories!
                $this->app['monolog']->addInfo("Sipped subdirectory $path, RESTORE search only for files in the /inbox!",
                    array(__METHOD__, __LINE__));
                continue;
            }
            $files[] = $path;
        }
        // sort the array ascending
        sort($files);

        $confirmation_zip = null;
        $confirmation_md5 = null;

        foreach ($files as $file) {
            $fileinfo = pathinfo($file);
            if (strtolower($fileinfo['extension']) !== 'zip') continue;
            if (false !== ($pos = strpos($fileinfo['filename'], 'confirmation_')) && ($pos == 0)) {
                $confirmation_zip = $file;
                $confirmation_md5 = SYNCDATA_PATH.'/inbox/'.$fileinfo['filename'].'.md5';
                if (!file_exists($confirmation_md5)) {
                    $result = "Missing the MD5 checksum file for the confirmation archive!";
                    $this->app['monolog']->addError($result, array(__METHOD__, __LINE__));
                    return $this->app['translator']->trans($result);
                }
                break;
            }
        }

        if (is_null($confirmation_zip)) {
            // nothing to do ...
            return $this->app['translator']->trans('No confirmation archive to process!');
        }

        // get the origin checksum of the backup archive
        if (false === ($md5_origin = @file_get_contents($confirmation_md5))) {
            $result = "Can't read the MD5 checksum file for the confirmation archive!";
            $this->app['monolog']->addError($result, array(__METHOD__, __LINE__));
            return $result;
        }
        // compare the checksums
        $md5 = md5_file($confirmation_zip);
        if ($md5 !== $md5_origin) {
            $result = "The checksum of the confirmation archive is not equal to the MD5 checksum file value!";
            $this->app['monolog']->addError($result, array(__METHOD__, __LINE__));
            return $result;
        }
        $this->app['monolog']->addInfo("The MD5 checksum of the confirmation archive is valid ($md5).",
            array(__METHOD__, __LINE__));

        if (file_exists(TEMP_PATH.'/confirmation') && !$this->app['utils']->rrmdir(TEMP_PATH.'/confirmation')) {
            throw new \Exception(sprintf("Can't delete the directory %s", TEMP_PATH.'/confirmation'));
        }
        if (!file_exists(TEMP_PATH.'/confirmation') && (false === @mkdir(TEMP_PATH.'/confirmation', 0755, true))) {
            throw new \Exception("Can't create the directory ".TEMP_PATH."/confirmation");
        }

        $this->app['monolog']->addInfo("Start unzipping $confirmation_zip", array(__METHOD__, __LINE__));
        $unZip = new unZip($this->app);
        $unZip->setUnZipPath(TEMP_PATH.'/confirmation');
        $unZip->extract($confirmation_zip);
        $this->app['monolog']->addInfo("Unzipped $confirmation_zip", array(__METHOD__, __LINE__));

        // check if the confirmation.json exists
        if (!file_exists(TEMP_PATH.'/confirmation/confirmation/confirmation.json')) {
            throw new \Exception("Missing the confirmation.json file within the archive!");
        }

        $archive = $this->app['utils']->readJSON(TEMP_PATH.'/confirmation/confirmation/confirmation.json');

        if (!isset($archive['date']) || !isset($archive['installation_name']) ||
            !isset($archive['client_id']) || !isset($archive['confirmations'])) {
            throw new \Exception('Unexpected archive content!', array(__METHOD__, __LINE__));
        }

        $receipts = array();

        foreach ($archive['confirmations'] as $confirmation) {

            if (false !== ($exists_id = $this->ConfirmationData->existsChecksum($confirmation['checksum']))) {
                // this confirmation already exists!
                $receipts[] = array(
                    'id' => $confirmation['id'],
                    'received_at' => date('Y-m-d H:i:s'),
                    'client_id' => $archive['client_id']
                );
                $this->app['monolog']->addInfo('Skipped confirmation with checksum '.$confirmation['checksum'].' because it already exists!');
                continue;
            }
            $data = $confirmation;
            $data['status'] = 'SUBMITTED';
            $data['received_at'] = date('Y-m-d H:i:s');
            $data['transmitted_at'] = $archive['date'];

            $confirmation_id = -1;
            $this->ConfirmationData->insert($data, $confirmation_id);

            $receipts[] = array(
                'id' => $confirmation['id'],
                'received_at' => date('Y-m-d H:i:s'),
                'client_id' => $archive['client_id']
            );

            $this->app['monolog']->addInfo('Added confirmation with checksum '.$confirmation['checksum']);
        }

        // delete the files from the /inbox
        if (!@unlink($confirmation_zip)) {
            throw new \Exception("Can't delete the file $confirmation_zip");
        }
        if (!@unlink($confirmation_md5)) {
            throw new \Exception("Can't delete the file $confirmation_md5");
        }

        $this->app['monolog']->addInfo('Processed '.count($receipts).' confirmations.');
        return $this->app['translator']->trans('Processed %count% confirmations', array('%count%' => count($receipts)));
    }
}
