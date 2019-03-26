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

/**
 * The Singleton
 * -----------------------------------------------------------------------
 *
 * Simply extends this Singleton class if you wish to use the Singleton
 * pattern of programming in your project
 *
 */
class Singleton
{
    private static $instances = array();

    /**
     * Constructor method
     *
     * @access protected
     * @since Method available since Release 0.1.1
     */
    protected function __construct()
    {
        //
    }

    /**
     * Avoid cloning
     *
     * @access protected
     * @since Method available since Release 0.1.1
     */
    protected function __clone()
    {
        //
    }

    /**
     * Avoid unserialization
     *
     * @access public
     * @since Method available since Release 0.1.1
     */
    public function __wakeup()
    {
        throw new ExceptionManager("Cannot unserialize singleton");
    }

    /**
     * Class registry
     *
     * This function acts as a singleton. If the requested class does not
     * exist it is instantiated and set to a static variable. If it has
     * previously been instantiated the variable is returned.
     *
     * @param    string    the class name being requested
     * @param    string    the directory where the class should be found
     * @param    mixed    an optional argument to pass to the class constructor
     * @return    object
     */
    public function RegisterClass($class, $directory = '', $param = null)
    {
        static $_classes = array();

        // Does the class exist? If so, we're done...
        if (isset($_classes[$class])) {
            return $_classes[$class];
        }

        $name = false;

        // Look for the class first in the local libraries folder
        // then in the native system folder from vendor
        // Uses CodeIgniter approach
        foreach (array( BR_PATH.'Libraries/', BR_PATH.'Vendor/biurad/') as $path) {
            if (file_exists($path . $directory . '/' . $class . '.php')) {
                $name = $class;

                if (class_exists($name, false) === false) {
                    require_once $path . $directory . '/' . $class . '.php';
                }

                break;
            }
        }

        // Is the request a class extension? If so we load it too
        if (file_exists(BR_PATH.'Application/' . $directory . '/' .$class . '.php')) {
            $name = $class;

            if (class_exists($name, false) === false) {
                require_once BR_PATH.'Application/' . $directory . '/' . $name . '.php';
            }
        }

        // Did we find the class?
        if ($name === false) {
            // Note: We use exit() rather than show_error() in order to avoid a
            // self-referencing loop with the Exceptions class
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            ExceptionManager::display('wrong','Location not found','Unable to locate the specified class: ' . $class . '.php');
            exit(5); // EXIT_UNK_CLASS
        }

        // Keep track of what we just loaded
        is_loaded($class);

        $_classes[$class] = isset($param)
        ? new $name($param)
        : new $name();
        return $_classes[$class];
    }

    /**
	 * Keeps track of which libraries have been loaded. This function is
	 * called by the load_class() function above
	 *
	 * @param	string
	 * @return	array
	 */
    function is_loaded($class = '')
    {
        static $_is_loaded = array();

        if ($class !== '') {
                $_is_loaded[strtolower($class)] = $class;
            }

        return $_is_loaded;
	}

    /**
     * Get the instance of desired class
     *
     * @access public
     * @since Method available since Release 0.1.1
     */
    public static function getInstance()
    {
        $class = get_called_class(); // late-static-bound class name
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static;
        }
        return self::$instances[$class];
    }
}
