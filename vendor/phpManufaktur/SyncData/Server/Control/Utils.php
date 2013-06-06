<?php

/**
 * SyncDataServer
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\SyncData\Server\Control;

class Utils
{
    protected $app = null;
    protected static $count_files = 0;
    protected static $count_directories = 0;
    protected static $count_tables = 0;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Reset or set the counter for processed files
     *
     * @param number $count
     */
    public static function setCountFiles($count=0)
    {
        self::$count_files = $count;
    }

    /**
     * Get the counted files
     *
     * @return number
     */
    public static function getCountFiles()
    {
        return self::$count_files;
    }

    /**
     * Increase the counted files by one
     */
    public static function increaseCountFiles()
    {
        self::$count_files++;
    }

    /**
     * Reset or set the counter for directories
     *
     * @param number $count
     */
    public static function setCountDirectories($count=0)
    {
        self::$count_directories = $count;
    }

    /**
     * Get the counted directories
     *
     * @return number
     */
    public static function getCountDirectories()
    {
        return self::$count_directories;
    }

    /**
     * Increase the counted directories by one
     */
    public static function increaseCountDirectories() {
        self::$count_directories++;
    }

    /**
     * Reset or set the counter for tables
     *
     * @param number $count
     */
    public static function setCountTables($count=0)
    {
        self::$count_tables = $count;
    }

    /**
     * Get the counted tables
     *
     * @return number
     */
    public static function getCountTables()
    {
        return self::$count_tables;
    }

    /**
     * Increase the counted tables by one
     */
    public static function increaseCountTables() {
        self::$count_tables++;
    }

    /**
     * Generates a strong password of N length containing at least one lower case letter,
     * one uppercase letter, one digit, and one special character. The remaining characters
     * in the password are chosen at random from those four sets.
     *
     * The available characters in each set are user friendly - there are no ambiguous
     * characters such as i, l, 1, o, 0, etc. This, coupled with the $add_dashes option,
     * makes it much easier for users to manually type or speak their passwords.
     *
     * Note: the $add_dashes option will increase the length of the password by
     * floor(sqrt(N)) characters.
     *
     * @link https://gist.github.com/tylerhall/521810
     *
     * @param number $length
     * @param string $add_dashes
     * @param string $available_sets
     * @return string
     */
    public static function generatePassword($length=9, $add_dashes=false, $available_sets='luds')
    {
        $sets = array();
        if (strpos($available_sets, 'l') !== false)
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        if (strpos($available_sets, 'u') !== false)
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        if (strpos($available_sets, 'd') !== false)
            $sets[] = '23456789';
        if (strpos($available_sets, 's') !== false)
            $sets[] = '!@#$%&*?';

        $all = '';
        $password = '';

        foreach($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if (!$add_dashes) {
            return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';
        while (strlen($password) > $dash_len) {
            $dash_str .= substr($password, 0, $dash_len) . '-';
    		  $password = substr($password, $dash_len);
    	   }
    	   $dash_str .= $password;
    	   return $dash_str;
    }

    /**
     * Remove a directory recursivly
     *
     * @link http://www.php.net/manual/de/function.rmdir.php#110489
     * @param string $dir
     * @return boolean
     */
    public function rrmdir($dir) {
        try {
            $files = array();
            if (false === ($scan_dir = @scandir($dir))) {
                throw new \Exception(sprintf("Can't scan the directory %s", $dir));
            }
            $files = array_diff($scan_dir, array('.','..'));
        } catch (\Exception $e) {
            $this->app['monolog']->addInfo($e->getMessage(), error_get_last());
        }
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rrmdir("$dir/$file") : @unlink("$dir/$file");
        }
        return @rmdir($dir);
    }

    /**
     * Sanitize a path, revert backslashes, resolves '..' etc.
     *
     * @link https://github.com/webbird/LEPTON_2_BlackCat/blob/master/upload/framework/CAT/Helper/Directory.php
     * @param string $path
     * @return string
     */
    public static function sanitizePath($path)
    {
        // remove / at end of string; this will make sanitizePath fail otherwise!
        $path = preg_replace( '~/{1,}$~', '', $path );
        // make all slashes forward
        $path = str_replace( '\\', '/', $path );
        // bla/./bloo ==> bla/bloo
        $path = preg_replace('~/\./~', '/', $path);
        // loop through all the parts, popping whenever there's a .., pushing otherwise.
        $parts = array();
        foreach (explode('/', preg_replace('~/+~', '/', $path)) as $part) {
            if ($part === ".." || $part == '') {
                array_pop($parts);
            }
            elseif ($part != "") {
                $parts[] = $part;
            }
        }
        $new_path = implode("/", $parts);
        // windows
        if (!preg_match('/^[a-z]\:/i', $new_path)) {
            $new_path = '/' . $new_path;
        }
        return $new_path;
    }

    /**
     * Copy files recursive from source to destination
     *
     * @param string $source_directory
     * @param string $destination_directory
     * @param array $ignore_directories must contains full path
     * @param array $ignore_subdirectories directory name only
     * @param array $ignore_files filename only
     * @throws \Exception
     * @return boolean
     */
    public function copyRecursive($source_directory, $destination_directory, $ignore_directories=array(),
        $ignore_subdirectories=array(), $ignore_files=array())
    {
        if (is_dir($source_directory))
            $directory_handle = dir($source_directory);
        else
            return false;
        if (!is_object($directory_handle)) return false;

        while (false !== ($file = $directory_handle->read())) {
            if (($file == '.') || ($file == '..')) continue;
            $source = self::sanitizePath($source_directory.'/'.$file);
            $target = self::sanitizePath($destination_directory.'/'.$file);
            if (is_dir($source)) {
                // check directories
                $skip = false;
                foreach ($ignore_directories as $directory) {
                    if ($source == $directory) {
                        $this->app['monolog']->addInfo(sprintf('Skipped directory %s', $source));
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;
                // check subdirectory
                if (in_array(substr(dirname($source), strrpos(dirname($source), DIRECTORY_SEPARATOR)+1), $ignore_subdirectories)) {
                    $this->app['monolog']->addInfo(sprintf('Skipped subdirectory %s', $source));
                    continue;
                }
                // create directory in the target
                if (true !== @mkdir($target, 0755, true )) {
                    throw new \Exception(sprintf("Can't create directory %s", $target), error_get_last());
                }
                self::increaseCountDirectories();
                // recursive call
                $this->copyRecursive($source, $target, $ignore_directories, $ignore_subdirectories, $ignore_files);
            }
            else {
                // check files
                if (in_array(basename($source), $ignore_files)) {
                    $this->app['monolog']->addInfo(sprintf('Skipped file %s', $source));
                    continue;
                }
                // check subdirectory
                if (in_array(substr(dirname($source), strrpos(dirname($source), DIRECTORY_SEPARATOR)+1), $ignore_subdirectories)) {
                    $this->app['monolog']->addInfo(sprintf('Skipped subdirectory %s', $source));
                    continue;
                }
                // copy file to the target
                if (true !== @copy($source, $target)) {
                    throw new \Exception(sprintf("Can't copy file %s", $source), error_get_last());
                }
                self::increaseCountFiles();
            }
        }
        $directory_handle->close();
        return true;
    }

    public function createDirectoryProtection($path)
    {
        // create protection for the desired directory
        $data = sprintf("# .htaccess generated by SyncData\nAuthUserFile %s/.htpasswd\n" .
            "AuthName \"SyncData protection\"\nAuthType Basic\n<Limit GET>\n" .
            "require valid-user\n</Limit>", $path);
        if (!file_put_contents(sprintf('%s/.htaccess', self::sanitizePath($path)), $data)) {
            throw new \Exception("Can't write .htaccess for config directory protection!");
        }
        $data = sprintf("# .htpasswd generated by SyncData\nsync_user:%s", crypt(self::generatePassword(16)));
        if (!file_put_contents(sprintf('%s/.htpasswd', self::sanitizePath($path)), $data)) {
            throw new \Exception("Can't write .htpasswd for config directory protection!");
        }
        $this->app['monolog']->addInfo("Created .htaccess protection for the directory $path");
    }
}