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
|   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                   |
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
 * Common Functions
 *
 * Loads the base classes and executes the request.
 *
 */

class CommonManager
{
    // ------------------------------------------------------------------------

    /**
     * Determines if the current version of PHP is equal to or greater than the supplied value
     *
     * @param	string
     * @return	bool	TRUE if the current version is $version or higher
     */
    public static function is_php($version)
    {
        static $_is_php;
        $version = (string) $version;

        if ( ! isset($_is_php[$version]))
        {
            $_is_php[$version] = version_compare(PHP_VERSION, $version, '>=');
        }

        return $_is_php[$version];
    }

    // ------------------------------------------------------------------------

    /**
     * Tests for file writability
     *
     * is_writable() returns TRUE on Windows servers when you really can't write to
     * the file, based on the read-only attribute. is_writable() is also unreliable
     * on Unix servers if safe_mode is on.
     *
     * @link	https://bugs.php.net/bug.php?id=54709
     * @param	string
     * @return	bool
     */
    public static function is_really_writable($file)
    {
        // If we're on a Unix server with safe_mode off we call is_writable
        if (DIRECTORY_SEPARATOR === '/' && (self::is_php('5.6') OR ! ini_get('safe_mode')))
        {
            return is_writable($file);
        }

        /** For Windows servers and safe_mode "on" installations we'll actually
        * write a file then read it. Bah...
        */
        if (is_dir($file))
        {
            $file = rtrim($file, '/').'/'.md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE)
            {
                return FALSE;
            }

            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        }
        elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
        {
            return FALSE;
        }

        fclose($fp);
        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Is HTTPS?
     *
     * Determines if the application is accessed via an encrypted
     * (HTTPS) connection.
     *
     * @return	bool
     */
    public static function is_https()
    {
        if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        {
            return TRUE;
        }
        elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        {
            return TRUE;
        }
        elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        {
            return TRUE;
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Is CLI?
     *
     * Test to see if a request was made from the command line.
     *
     * @return 	bool
     */
    public static function is_cli()
    {
        return (PHP_SAPI === 'cli' );
    }

    // ------------------------------------------------------------------------

    /**
     * Error Handler
     *
     * This function lets us invoke the exception class and
     * display errors using the standard error template located
     * in application/views/errors/error_general.php
     * This function will send the error page directly to the
     * browser and exit.
     *
     * @param	string
     * @param	int
     * @param	string
     * @return	void
     */
    public static function show_error($message, $status_code = 500, $heading = 'An Error Was Encountered')
    {
        $status_code = abs($status_code);
        if ($status_code < 100)
        {
            $exit_status = $status_code + 9; // 9 is EXIT__AUTO_MIN
            $status_code = 500;
        }
        else
        {
            $exit_status = 1; // EXIT_ERROR
        }

        $_error = new ExceptionManager;
        echo $_error->show_error($heading, $message, 'error_general', $status_code);
        exit($exit_status);
    }

    // ------------------------------------------------------------------------

    /**
     * 404 Page Handler
     *
     * This function is similar to the show_error() function above
     * However, instead of the standard error template it displays
     * 404 errors.
     *
     * @param	string
     * @param	bool
     * @return	void
     */
    public static function show_404($page = '', $log_error = TRUE)
    {
        $_error = new ExceptionManager;
        $_error->show_404($page, $log_error);
        exit(4); // EXIT_UNKNOWN_FILE
    }

    // ------------------------------------------------------------------------

    /**
     * Error Logging Interface
     *
     * We use this as a simple mechanism to access the logging
     * class and send messages to be logged.
     *
     * @param	string	the error level: 'error', 'debug' or 'info'
     * @param	string	the error message
     * @return	void
     */
    public static function log_message($level, $message)
    {
        static $_log;

        if ($_log === NULL)
        {
            // references cannot be directly assigned to static variables, so we use an array
            $_log[0] = new LoggerManager;
        }

        $_log[0]->write_log($level, $message);
    }

    // ------------------------------------------------------------------------

    /**
     * Set HTTP Status Header
     *
     * @param	int	the status code
     * @param	string
     * @return	void
     */
    public static function set_status_header($code = 200, $text = '')
    {
        if (self::is_cli())
        {
            return;
        }

        if (empty($code) OR ! is_numeric($code))
        {
            self::show_error('Status codes must be numeric', 500);
        }

        if (empty($text))
        {
            is_int($code) OR $code = (int) $code;
            $stati = array(
                100	=> 'Continue',
                101	=> 'Switching Protocols',

                200	=> 'OK',
                201	=> 'Created',
                202	=> 'Accepted',
                203	=> 'Non-Authoritative Information',
                204	=> 'No Content',
                205	=> 'Reset Content',
                206	=> 'Partial Content',

                300	=> 'Multiple Choices',
                301	=> 'Moved Permanently',
                302	=> 'Found',
                303	=> 'See Other',
                304	=> 'Not Modified',
                305	=> 'Use Proxy',
                307	=> 'Temporary Redirect',

                400	=> 'Bad Request',
                401	=> 'Unauthorized',
                402	=> 'Payment Required',
                403	=> 'Forbidden',
                404	=> 'Not Found',
                405	=> 'Method Not Allowed',
                406	=> 'Not Acceptable',
                407	=> 'Proxy Authentication Required',
                408	=> 'Request Timeout',
                409	=> 'Conflict',
                410	=> 'Gone',
                411	=> 'Length Required',
                412	=> 'Precondition Failed',
                413	=> 'Request Entity Too Large',
                414	=> 'Request-URI Too Long',
                415	=> 'Unsupported Media Type',
                416	=> 'Requested Range Not Satisfiable',
                417	=> 'Expectation Failed',
                422	=> 'Unprocessable Entity',
                426	=> 'Upgrade Required',
                428	=> 'Precondition Required',
                429	=> 'Too Many Requests',
                431	=> 'Request Header Fields Too Large',

                500	=> 'Internal Server Error',
                501	=> 'Not Implemented',
                502	=> 'Bad Gateway',
                503	=> 'Service Unavailable',
                504	=> 'Gateway Timeout',
                505	=> 'HTTP Version Not Supported',
                511	=> 'Network Authentication Required',
            );

            if (isset($stati[$code]))
            {
                $text = $stati[$code];
            }
            else
            {
                self::show_error('No status text available. Please check your status code number or supply your own message text.', 500);
            }
        }

        if (strpos(PHP_SAPI, 'cgi') === 0)
        {
            header('Status: '.$code.' '.$text, TRUE);
            return;
        }

        $server_protocol = (isset($_SERVER['SERVER_PROTOCOL']) && in_array($_SERVER['SERVER_PROTOCOL'], array('HTTP/1.0', 'HTTP/1.1', 'HTTP/2'), TRUE))
            ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        header($server_protocol.' '.$code.' '.$text, TRUE, $code);
    }

    // --------------------------------------------------------------------

    /**
     * Error Handler
     *
     * This is the custom error handler that is declared at the (relative)
     * top of CodeIgniter.php. The main reason we use this is to permit
     * PHP errors to be logged in our own log files since the user may
     * not have access to server logs. Since this function effectively
     * intercepts PHP errors, however, we also need to display errors
     * based on the current error_reporting level.
     * We do that with the use of a PHP error template.
     *
     * @param	int	$severity
     * @param	string	$message
     * @param	string	$filepath
     * @param	int	$line
     * @return	void
     */
    public static function _error_handler($severity, $message, $filepath, $line)
    {
        $is_error = (((E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR) & $severity) === $severity);

        // When an error occurred, set the status header to '500 Internal Server Error'
        // to indicate to the client something went wrong.
        // This can't be done within the $_error->show_php_error method because
        // it is only called when the display_errors flag is set (which isn't usually
        // the case in a production environment) or when errors are ignored because
        // they are above the error_reporting threshold.
        if ($is_error)
        {
            self::set_status_header(500);
        }

        // Should we ignore the error? We'll get the current error_reporting
        // level and add its bits with the severity bits to find out.
        if (($severity & error_reporting()) !== $severity)
        {
            return;
        }

        $_error = new ExceptionManager;
        $_error->log_exception($severity, $message, $filepath, $line);

        // Should we display the error?
        if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
        {
            $_error->show_php_error($severity, $message, $filepath, $line);
        }

        // If the error is fatal, the execution of the script should be stopped because
        // errors can't be recovered from. Halting the script conforms with PHP's
        // default error handling. See http://www.php.net/manual/en/errorfunc.constants.php
        if ($is_error)
        {
            exit(1); // EXIT_ERROR
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Exception Handler
     *
     * Sends uncaught exceptions to the logger and displays them
     * only if display_errors is On so that they don't show up in
     * production environments.
     *
     * @param	Exception	$exception
     * @return	void
     */
    static function _exception_handler($exception)
    {
        $_error = new ExceptionManager;
        $_error->log_exception('error', 'Exception: '.$exception->getMessage(), $exception->getFile(), $exception->getLine());

        self::is_cli() OR self::set_status_header(500);
        // Should we display the error?
        if (str_ireplace(array('off', 'none', 'no', 'false', 'null'), '', ini_get('display_errors')))
        {
            $_error->show_exception($exception);
        }

        exit(1); // EXIT_ERROR
    }

    // ------------------------------------------------------------------------

    /**
     * Shutdown Handler
     *
     * This is the shutdown handler that is declared at the top
     * of CodeIgniter.php. The main reason we use this is to simulate
     * a complete custom exception handler.
     *
     * E_STRICT is purposively neglected because such events may have
     * been caught. Duplication or none? None is preferred for now.
     *
     * @link	http://insomanic.me.uk/post/229851073/php-trick-catching-fatal-errors-e-error-with-a
     * @return	void
     */
    static function _shutdown_handler()
    {
        $last_error = error_get_last();
        if (isset($last_error) &&
            ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING)))
        {
            self::_error_handler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Stringify attributes for use in HTML tags.
     *
     * Helper function used to convert a string, array, or object
     * of attributes to a string.
     *
     * @param	mixed	string, array, object
     * @param	bool
     * @return	string
     */
    static function _stringify($attributes, $js = FALSE)
    {
        $atts = NULL;

        if (empty($attributes))
        {
            return $atts;
        }

        if (is_string($attributes))
        {
            return ' '.$attributes;
        }

        $attributes = (array) $attributes;

        foreach ($attributes as $key => $val)
        {
            $atts .= ($js) ? $key.'='.$val.',' : ' '.$key.'="'.$val.'"';
        }

        return rtrim($atts, ',');
    }

    // ------------------------------------------------------------------------
}