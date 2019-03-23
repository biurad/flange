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

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 *  The Debugger for Biurad Slim framework
 * -----------------------------------------------------------------------
 *
 * Provides the developer with useful messages in case of an exception or
 * errors happen.
 *
 */
class ExceptionManager extends \Exception
{
    private static $profiles = [];
    private static $time_start = 0;
    private static $profilerStartTime = 0;

    /**
     * Registering the debugger to log exceptions locally or transfer them to
     * external services.
     *
     * Depends on the settings in config/env.php:
     *
     * + 0: Shows "Something went wrong" message ambiguously (handled locally)
     *
     * + 1:	Shows simple error message, file and the line occured (handled
     *			locally)
     *
     * + 2: Shows advanced debugging with code snippet, stack frames, and
     *			envionment details, handled by Flip\Whoops
     *
     * @static
     *
     */
    public static function start()
    {
        if (getenv('DEBUG') === '0' || getenv('DEBUG') === '1') {
            register_shutdown_function('Radion\ExceptionManager::error_handler');
        } elseif (getenv('DEBUG') === '2') {
            Whoops::register();
        } elseif (getenv('DEBUG') === '-1') {
            // do nothing
        }
    }

    /**
     * Sets the header of the HTTP request and then display the
     * HTTP error codes.
     *
     * @param string $code      The HTTP error code
     * @param bool   $terminate Terminate the entire script execution
     *
     * @static
     *
     */
    public static function report($code, $terminate = false)
    {
        switch ($code) {
            case '404':
                self::set_header('404', 'Internal Server Error');
                self::display('simple', '404 Not Found');
                break;
            case '500':
                self::set_header('500', 'Internal Server Error');
                self::display('simple', 'Something went wrong');
                break;
            default:
                self::set_header('500', 'Internal Server Error');
                self::display('simple');
                break;
        }

        if ($terminate) {
            die();
        }
    }

    /**
     * Sets the header of the HTTP request.
     *
     * @static
     *
     */
    public static function set_header($code, $error)
    {
        header($_SERVER['SERVER_PROTOCOL'] . '' . $code . '' . $error);
    }

    /**
     * The error handler which is called by register_shutdown_function()
     * in event of exceptions, syntax errors, warning and notices.
     *
     * @static
     *
     * @see Debugger::start(), Debugger::display()
     */
    public static function error_handler()
    {
        $error = error_get_last();
        $message = $error['message'];
        if ($error) {
            if (getenv('DEBUG') == 0) {
                self::display('simple', 'Something went wrong');
            } else {
                self::display('full', $error);
            }
        }
    }

    /**
     * Display error messages.
     *
     * @param string $name    error page name
     * @param string $message error messages
     * @param string $description error details
     *
     * @static
     *
     */
    public static function display($name, $message = '', $decription = '')
    {
        self::set_header('500', 'Internal Server Error');
        include '../tests/templates/views/' . $name . '.php';
    }

    /**
     * Calculate a precise time difference.
     *
     * @param string $start result of microtime()
     * @param string $end   result of microtime(); if NULL/FALSE/0/'' then it's now
     *
     * @return flat difference in seconds, calculated with minimum precision loss
     *
     * @static
     *
     */
    private static function microtime_diff($start)
    {
        $duration = microtime(true) - $start;
        $hours = (int)($duration / 60 / 60);
        $minutes = (int)($duration / 60) - $hours * 60;
        $seconds = $duration - $hours * 60 * 60 - $minutes * 60;

        return number_format((float)$seconds, 5, '.', '');
    }

    /**
     * Display execution time (start time - finish time) in human readable form
     * (milliseconds).
     *
     *
     * @static
     *
     * @see Debugger::microtime_diff()
     */
    public static function exec_time()
    {
        echo '<span class="ss_exec_time" style="display: table; margin: 0 auto;">Request takes ' . (self::microtime_diff(SS_START) * 1000) . ' milliseconds</span>';
    }

    /**
     */
    public static function startProfiling()
    {
        if (self::$profilerStartTime == 0) {
            self::$profilerStartTime = microtime(true);
        }

        self::$time_start = microtime(true);
    }

    /**
     * @param string $point_name
     * @param string $point_type
     *
     * @return array
     *
     */
    public static function addProfilingData($point_name = '', $point_type = 'others')
    {
        $profileData =
            [
                'name' => $point_name,
                'time' => (self::microtime_diff(self::$time_start) * 1000),
                'unit' => 'ms',
                'type' => $point_type,
            ];

        array_push(self::$profiles, $profileData);

        self::$time_start = microtime(true);

        return $profileData;
    }

    /**
     * @return array
     *
     */
    public static function endProfiling()
    {
        $timeIncludingAutoloader = self::microtime_diff(BR_START) * 1000;
        $timeProfiled = self::microtime_diff(self::$profilerStartTime) * 1000;
        $timeMinusAutoloader = $timeIncludingAutoloader - $timeProfiled;

        $profileData =
            [
                'name' => 'Starting Autoloader',
                'time' => ($timeMinusAutoloader),
                'unit' => 'ms',
                'type' => 'system',
            ];

        array_unshift(self::$profiles, $profileData);
        self::$time_start = 0;
        self::$profilerStartTime = 0;

        return
            [
                'Total Time' => ($timeIncludingAutoloader),
                'unit'       => 'ms',
                'profiles'   => self::$profiles,
            ];
    }
}


/**
 * Class Whoops.
 *
 */
class Whoops
{
    /**
     * @return Run
     *
     */
    public static function register()
    {
        $whoops = new \Whoops\Run();
        $whoops->pushHandler(new PrettyPageHandler());

        return $whoops->register();
    }
}