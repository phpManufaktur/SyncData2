<?php

/**
 * SyncData
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/kitBase
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Control\Zip;

class unZipException extends \Exception
{

    /**
     * Standard exception handling for class UnZip
     *
     * @param string $error
     * @return \phpManufaktur\Appetizer\UnZip\UnZipException
     */
    public static function error ($error)
    {
        return new self($error);
    } // error()

    /**
     * Exception handling for open error of ZipArchive
     *
     * @param integer $error_code
     * @param string $zip_file
     * @return \phpManufaktur\Appetizer\UnZip\UnZipException
     */
    public static function errorZipArchiveOpen ($error_code, $zip_file)
    {
        switch ($error_code) :
            case \ZIPARCHIVE::ER_EXISTS:
                $error = sprintf("File '%s' already exists.", $zip_file);
                break;
            case \ZIPARCHIVE::ER_INCONS:
                $error = sprintf("Zip archive '%s' is inconsistent.", $zip_file);
                break;
            case \ZIPARCHIVE::ER_INVAL:
                $error = sprintf("Invalid argument (%s)", $zip_file);
                break;
            case \ZIPARCHIVE::ER_MEMORY:
                $error = sprintf("Malloc failure (%s)", $zip_file);
                break;
            case \ZIPARCHIVE::ER_NOENT:
                $error = sprintf("No such zip file: '%s'", $zip_file);
                break;
            case \ZIPARCHIVE::ER_NOZIP:
                $error = sprintf("'%s' is not a zip archive.", $zip_file);
                break;
            case \ZIPARCHIVE::ER_OPEN:
                $error = sprintf("Can't open zip file: %s", $zip_file);
                break;
            case \ZIPARCHIVE::ER_READ:
                $error = sprintf("Zip read error (%s)", $zip_file);
                break;
            case \ZIPARCHIVE::ER_SEEK:
                $error = sprintf("Zip seek error (%s)", $zip_file);
                break;
            default:
                $error = sprintf("'%s' is not a valid zip archive, got error code: %s", $zip_file, $error_code);
                break;
        endswitch;

        $error_str = sprintf('[ %d ] %s', $error_code, $error);
        return new self($error_str);
    } // errorZipArchiveOpen()

} // class unZipException

