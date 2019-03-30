<?php
/*
        This code is under MIT License
        
        +--------------------------------+
        |   DO NOT MODIFY THIS HEADERS   |
        +--------------------------------+-----------------+
        |   Created by BiuStudio                           |
        |   Email: support@biuhub.net                      |
        |   Link: https://www.biurad.tk                    |
        |   Source: https://github.com/biustudios/biurad   |
        |   Real Name: Divine Niiquaye - Ghana             |
        |   Copyright Copyright (c) 2018-2019 BiuStudio    |
        |   License: https://biurad.tk/LICENSE.md          |
        +--------------------------------------------------+
        
        +--------------------------------------------------------------------------------+
        |   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                       |
        +--------------------------------------------------------------------------------+
        
        +----------------+
        |   Tested on    |
        +----------------+-----+
        |  APACHE => 2.0.55    |
        |     PHP => 5.4       |
        +----------------------+
        
        +---------------------+
        |  How to report bug  |
        +---------------------+-----------------------------------------------------------------+
        |   You can e-mail me using the email addres written above. That email is also my msn   |
        |   contact, so you can use it for contact me on MSN.                                   |
        +---------------------------------------------------------------------------------------+
        
        +-----------+
        |  Notes    |
        +-----------+------------------------------------------------------------------------------------------------+
        |   - BiuRad's simple-as-possible architecture was inspired by several conference talks, slides              |
        |     and articles about php frameworks that - surprisingly and intentionally -                              |
        |     go back to the basics of programming, using procedural programming, static classes,                    |
        |     extremely simple constructs, not-totally-DRY code etc. while keeping the code extremely readable.      |
        |   - Features of Biuraad Php Framework
        |     +--> Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |     +--> Built with the official PHP password hashing functions, fitting the most modern password          |
                   hashing/salting web standards.                                                                    |
        |     +--> Uses [Post-Redirect-Get pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get)                 |
        |     <--+ Uses URL rewriting ("beautiful URLs").                                                            |
        |   - Masses of comments                                                                                     |                                                                              |
        |     +--> Uses Libraries including Composer to load external dependencies.                                  |
        |     <--+ Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |   - Fits PSR-0/1/2/4 coding guideline.                                                                     |
        +------------------------------------------------------------------------------------------------------------+
        
        +------------------+
        |  Special Thanks  |
        +------------------+-----------------------------------------------------------------------------------------+
        |  I always thank the HTML FORUM COMMUNITY (http://www.html.it) for the advice about the regular expressions |
        |  A special thanks at github.com(http://www.github.com), because they provide me the list of php libraries, |
        |  snippets, and any more...                                                                                 |
        |  I thanks Php.net and Sololearn.com for its guildline in PHP Programming                                   |
        |  Finally, i thank Wikipedia for the countries's icons 20px                                                 |
        +------------------------------------------------------------------------------------------------------------+
*/
namespace Radion;
use Phpfastcache\CacheManager as CacheManager;

/**
 *  The Cache manager various providers
 * -----------------------------------------------------------------------
 *
 * The Cache Facade configures the Cache Manager and provides access to the
 * Cache Manager instance
 *
 */
class Cache extends CacheConfig
{
    /**
     * The configurations.
     *
     * @var array
     * @static
     *
     */
    private static $config = null;

    /**
     * Load configuration file.
     *
     */
    public static function init()
    {
        if (self::$config === null) {
            self::$config = Config::get('cache');
        }
    }

    /**
     * @return null|\phpFastCache\Core\DriverAbstract
     *
     */
    public static function getInstance()
    {
        if (self::$config['enabled'] === true) {
            CacheConfig::setSettings(self::$config['settings']);

            return CacheConfig::createInstance();
        } else {
            return;
        }
    }

    /**
     * @return null|\phpFastCache\Core\DriverAbstract
     *
     */
    public static function initialize()
    {
        self::loadConfig();

        return self::getInstance();
    }

    function OpenCacheDir()
    {
        $cache = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        $htmlcontent = "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Biurad Slim Framework</title></head><body></body></html>";
        if (!file_exists($cache)) {
            $oldmask = umask(0);
            @mkdir($cache, 0777, true);
            @umask($oldmask);
        }
        if (!file_exists($cache.'/.htaccess')) {
            $f = @fopen($cache."/.htaccess", "a+");
            @fwrite($f, "deny from all");
            @fclose($f);
        }
        if (!file_exists($cache.'/index.html')) {
            $f = @fopen($cache."/index.html", "a+");
            @fwrite($f, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Biurad Slim Framework</title></head><body></body></html>");
            @fclose($f);
        }
        if (!file_exists($cache.'/framework/index.html')) {
            $f = @fopen($cache."/framework/index.html", "a+");
            @fwrite($f, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Biurad Slim Framework</title></head><body></body></html>");
            @fclose($f);
        }
    }
    static function read($fileName)
    {
        $cache = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        $fileName = $cache.'/' . $fileName;
        if (file_exists($fileName)) {
            $handle   = fopen($fileName, 'rb');
            $variable = fread($handle, filesize($fileName));
            fclose($handle);
            return unserialize($variable);
        } else {
            return null;
        }
    }
    static function write($fileName, $variable)
    {
        $cache = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        $fileName = $cache.'/' . $fileName;
        $handle   = fopen($fileName, 'a');
        fwrite($handle, serialize($variable));
        fclose($handle);
    }
    static function delete($fileName)
    {
        $cache = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        $fileName = $cache.'/' . $fileName;
        @unlink($fileName);
    }

    static function cachebase() {
        $cache = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        return $cache;
    }

    function ClearCache() {
        $path = BR_PATH.'Resources/' . Config::get('theme','storage_path');
        if ($handle = opendir($path)) {
            while (false !== ($file = readdir($handle))) {
                if (strripos($file, ['temp','rade','logs','rb']) !== false) {
                    @unlink($path . '/' . $file);
                }
            }
        }
    }
}

class CacheConfig
{
    private static $settings;

    /**
     * @param $settings
     *
     */
    public static function setSettings($settings)
    {
        self::$settings = $settings;
    }

    /**
     * @return \phpFastCache\Core\DriverAbstract
     *
     */
    public static function createInstance()
    {
        CacheManager::setup(self::$settings);

        return CacheManager::getInstance();
    }
}