<?php
/*
This code is under MIT License

+--------------------------------+
|   DO NOT MODIFY THIS HEADERS   |
+--------------------------------+-----------------+
|   Created by BiuStudio                           |
|   Email: support@biuhub.net                      |
|   Link: https://www.biurad.ml                    |
|   Source: https://github.com/biustudios/         |
|   Real Name: Divine Niiquaye - Ghana             |
|   Copyright Copyright (c) 2018-2019 BiuStudio    |
|   License: https://biurad.ml/LICENSE.md          |
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

/**
 * The Configuration Loader
 * -----------------------------------------------------------------------
 *
 * The Configuration loader are responsible to read and return the
 * configurations in a form of array.
 *
 * Usage:
 * 1. Get the entire configuration from a file:
 *      $config = Config::get('filename');
 *
 * 2. Get specific configuration from a file:
 *    $config = Config::get('filename', 'configuration_key');
 *
 */
class ConfigManager
{
    /**
     * Contain all the config
     * -
     * Contenido de variables de configuración.
     *
     * @var array
     */
    protected static $vars = [];

    /**
     * The array of configuration from config/env.php.
     *
     * @var array
     * @static
     *
     */
    protected static $env = null;

    /**
     * The array of configuration from files located on config directory.
     *
     * @var array
     * @static
     *
     */
    protected static $hive = null;

    /**
     * Link a variable or an object to the container.
     *
     * @param string $file the configuration file name (without .php)
     * @param string $key  the array key
     *
     * @return array $hive    the array of configurations
     *
     * @static
     *
     */
    public static function _get($file, $key = null)
    {
        if (isset(self::$hive[$file]) === false) {
            self::$hive[$file] = include_once BR_PATH . 'Config/' . $file . '.phtml ';
        }

        if ($key === null) {
            return self::$hive[$file];
        } else {
            return self::$hive[$file][$key];
        }
    }

    /**
     * Get config vars
     * -
     * Obtain configuración.
     *
     * @param string $var config.app.title
     *
     * @return mixed
     */
    public static function get($var)
    {
        $namespaces = explode('.', $var);
        if (!isset(self::$vars[$namespaces[0]])) {
            self::load($namespaces[0]);
        }
        switch (count($namespaces)) {
            case 3:
                return isset(self::$vars[$namespaces[0]][$namespaces[1]][$namespaces[2]]) ?
                self::$vars[$namespaces[0]][$namespaces[1]][$namespaces[2]] : null;
            case 2:
                return isset(self::$vars[$namespaces[0]][$namespaces[1]]) ?
                self::$vars[$namespaces[0]][$namespaces[1]] : null;
            case 1:
                return isset(self::$vars[$namespaces[0]]) ? self::$vars[$namespaces[0]] : null;

            default:
                ExceptionManager('Maximum of 3 arrays in Config::get(config.app.title):' . $var);
        }
    }

    /**
     * Get all configs
     * -
     * Obtain all configurations
     *
     * @return array
     */
    public static function getAll()
    {
        return self::$vars;
    }

    /**
     * Set variable in config
     * -
     * Assign a chain of files.key.key
     *
     * @param string $var   variable of configurations
     * @param mixed  $value
     */
    public static function set($var, $value)
    {
        $namespaces = explode('.', $var);
        switch (count($namespaces)) {
            case 3:
                self::$vars[$namespaces[0]][$namespaces[1]][$namespaces[2]] = $value;
                break;
            case 2:
                self::$vars[$namespaces[0]][$namespaces[1]] = $value;
                break;
            case 1:
                self::$vars[$namespaces[0]] = $value;
                break;
            default:
                new ExceptionManager('Maximum of 3 arrays in Config::get(config.app.title):' . $var);
        }
    }

    /**
     * Read config file
     *
     * @param string $file  .php o .ini
     * @param bool   $force .php o .ini
     *
     * @return array
     */
    public static function &read($file, $force = false)
    {
        if (isset(self::$vars[$file]) && !$force) {
            return self::$vars[$file];
        }
        self::load($file);

        return self::$vars[$file];
    }

    /**
     * Load config file
     *
     *
     * @param string $file
     */
    private static function load($file)
    {
        if (file_exists(BR_PATH . 'Config/' . $file . '.phtml')) {
            self::$vars[$file] = require BR_PATH . 'Config/' . $file . '.phtml';

            return;
        }
        //  .ini
        self::$vars[$file] = parse_ini_file(BR_PATH . 'Config/' . $file . '.ini', true);
    }

    /**
     * Reads the configuration file (config/env.php) and include each of the
     * variables (retrieved in a form of associative array) to the Environment
     * Variable. Also store the configurations into static variable $env.
     *
     * @static
     *
     */
    public static function setEnv()
    {
        if (self::$env === null) {
            self::$env = require_once BR_PATH . 'Config/env.php';
        }

        foreach (self::$env as $v => $a) {
            putenv($v . '=' . $a);
        }
    }

}
