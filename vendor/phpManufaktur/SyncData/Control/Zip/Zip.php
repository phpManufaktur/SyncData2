<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Control\Zip;

use phpManufaktur\SyncData\Control\Application;

class Zip {

    protected $app = null;

    public function __construct(Application $app)
    {
        $this->app = $app;
        if (!class_exists('\\ZipArchive')) {
            throw new \Exception('Missing class ZipArchive!');
        }
    }

    /**
     * Add files and sub-directories in a folder to zip file.
     *
     * @param string $folder
     * @param ZipArchive $zipFile
     * @param integer $exclusiveLength Number of text to be exclusived from the file path.
     */
    private function folderToZip($folder, &$zipFile, $exclusiveLength)
    {
        $handle = opendir($folder);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = "$folder/$f";
                // Remove prefix from file path before add to zip.
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    if (!$zipFile->addFile($filePath, $localPath)) {
                        $this->app['monolog']->addError("Can't add $filePath to the ZIP");
                    }
                    else {
                        $this->app['utils']->increaseCountFiles();
                    }
                }
                elseif (is_dir($filePath)) {
                    // Add sub-directory.
                    if (!$zipFile->addEmptyDir($localPath)) {
                        throw new \Exception("Can't add the empty directory $localPath to the ZIP");
                    }
                    $this->app['utils']->increaseCountDirectories();
                    $this->folderToZip($filePath, $zipFile, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }

    /**
     * Zip a folder (include itself).
     * Usage: Zip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
     *
     * @param string $sourcePath Path of directory to be zip.
     * @param string $outZipPath Path of output zip file.
     */
    public function zipDir($sourcePath, $outZipPath)
    {
        $this->app['monolog']->addInfo("Create the $outZipPath ZIP archive from $sourcePath");
        $pathInfo = pathInfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];

        $this->app['utils']->setCountFiles();
        $this->app['utils']->setCountDirectories();
        $z = new \ZipArchive();
        $z->open($outZipPath, \ZIPARCHIVE::CREATE);
        $z->addEmptyDir($dirName);
        $this->app['monolog']->addInfo('Start adding files to the ZIP archive');
        $this->folderToZip($sourcePath, $z, strlen("$parentPath/"));
        $z->close();
        $this->app['monolog']->addInfo(sprintf('Added %d files in %d directories to the ZIP',
            $this->app['utils']->getCountFiles(), $this->app['utils']->getCountDirectories()));
        $this->app['monolog']->addInfo("Closed ZIP the archive $outZipPath");
    }

}