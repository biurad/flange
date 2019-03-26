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

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Handler\JsonResponseHandler;
use Radion\CommonManager as Common; 

/**
 *  The ExceptionManager
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
     * Registering the ExceptionManager to log exceptions locally or transfer them to
     * external services.
     *
     * Depends on the settings in config/env.php:
     *
     * + 0: Shows "Something went wrong" message ambiguously (handled locally)
     *
     * + 1:    Shows simple error message, file and the line occured (handled
     *            locally)
     *
     * + 2: Shows advanced debugging with code snippet, stack frames, and
     *            envionment details, handled by Flip\Whoops
     *
     * @static
     * @access public
     * @since Method available since Release 0.1.0
     */
    public static function start()
    {
        if (getenv('DEBUG') === '0' || getenv('DEBUG') === '1') {
            register_shutdown_function('Radion\ExceptionManager::error_handler');
        } else if (getenv('DEBUG') === '2') {
            $whoops = new \Whoops\Run;
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);

            if (\Whoops\Util\Misc::isAjaxRequest()) {
                $jsonHandler = new JsonResponseHandler();
                $jsonHandler->setJsonApi(true);
                $whoops->pushHandler($jsonHandler);
            }

            $whoops->register();
        } else if (getenv('DEBUG') === '3') {
			set_error_handler('Radion\CommonManager::_error_handler');
			set_exception_handler('Radion\CommonManager::_exception_handler');
			register_shutdown_function('Radion\CommonManager::_shutdown_handler');
		}
		session_name('RADIONSESSID');
        session_start();

        if (getenv('ENVIRONMENT') == 'development') {
            ini_set('display_errors', 0);
            error_reporting(-1);
        } else if (getenv('ENVIRONMENT') == 'testing') {
            error_reporting(-1);
            ini_set('display_errors', 1);
        } else if (getenv('ENVIRONMENT') == 'maintainance') {
            error_reporting(0);
            ini_set('display_errors', 0);
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            ExceptionManager::display('wrong', 'Maintainance Mode', "Sorry but the application is being maintained, we'll back shortly");
            echo "<script>document.title = 'Maintainance Mode';</script>";
            exit(1);
        } else if (getenv('ENVIRONMENT') == 'production') {
            ini_set('display_errors', 0);
            if (version_compare(PHP_VERSION, '5.6', '>=')) {
                error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
            } else {
                error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
            }
        } else {
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            ExceptionManager::display('wrong', 'Environment not defined', 'The application environment is not set correctly');
            exit(1); // EXIT_ERROR
        }
    }

/**
 * Sets the header of the HTTP request and then display the
 * HTTP error codes.
 *
 * @param string    $code                The HTTP error code
 * @param bool        $terminate    Terminate the entire script execution
 *
 * @static
 * @see ExceptionManager::set_header(), ExceptionManager::display()
 * @access public
 * @since Method available since Release 0.1.0
 */
    public static function report($code, $terminate = false)
    {
        switch ($code) {
            case '404':
                self::set_header('404', 'Internal Server Error');
				self::show_404();
                break;
            case '500':
                self::set_header('500', 'Internal Server Error');
                self::display('simple', 'Something went wrong','', 500);
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
 * Sets the header of the HTTP request
 *
 * @static
 * @access public
 * @since Method available since Release 0.1.0
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
 * @see ExceptionManager::start(), ExceptionManager::display()
 * @access public
 * @since Method available since Release 0.1.0
 */
    public static function error_handler()
    {
        $error = error_get_last();
        $message = $error['message'];
        if ($error) {
            if (getenv('DEBUG') == 0) {
                self::display('wrong', 'Something went wrong');
            } else {
                self::display('full', $error);
            }
        }
    }

/**
 * Display error messages
 *
 * @param string $name        error page name
 * @param string @message    error messages
 *
 * @static
 * @access public
 * @since Method available since Release 0.1.0
 */
    public static function display($page = '', $heading = '', $message = '', $code = 500, $log_error = TRUE)
    {
		if (!$page || !$heading || !$message || !$code) {
			$page = 'wrong';
			$heading = 'An Error Was Encountered';
			$message = 'An error was made in your requested.';
			$code= 500; 
		}

		// By default we log this, but allow a dev to skip it
		if ($log_error)
		{
			Common::log_message('error', $heading.': '.$page);
		}

		echo self::show_error($heading, $message, $page, $code);
		exit(4); // EXIT_UNKNOWN_FILE
    }

/**
 * Calculate a precise time difference.
 *
 * @param string $start result of microtime()
 * @param string $end     result of microtime(); if NULL/FALSE/0/'' then it's now
 *
 * @return flat difference in seconds, calculated with minimum precision loss
 *
 * @static
 * @see ExceptionManager::exec_time()
 * @access public
 * @since Method available since Release 0.1.0
 */
    private static function microtime_diff($start)
    {
        $duration = microtime(true) - $start;
        $hours = (int) ($duration / 60 / 60);
        $minutes = (int) ($duration / 60) - $hours * 60;
        $seconds = $duration - $hours * 60 * 60 - $minutes * 60;
        return number_format((float) $seconds, 5, '.', '');
    }

/**
 * Display execution time (start time - finish time) in human readable form
 * (milliseconds).
 *
 *
 * @static
 * @see ExceptionManager::microtime_diff()
 * @access public
 * @since Method available since Release 0.1.0
 */
    public static function exec_time()
    {
        echo ('<span class="ss_exec_time" style="display: table;margin: 0 auto;font-size: 20px;color: #333;">Request takes ' . (self::microtime_diff(BR_START) * 1000) . ' milliseconds</span>');
    }

    public static function startProfiling()
    {
        if (self::$profilerStartTime == 0) {
            self::$profilerStartTime = microtime(true);
        }

        self::$time_start = microtime(true);
    }

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
            'unit' => 'ms',
            'profiles' => self::$profiles,
        ];
    }

    /**
	 * Nesting level of the output buffering mechanism
	 *
	 * @var	int
	 */
	public $ob_level;

	/**
	 * List of available error levels
	 *
	 * @var	array
	 */
	public $levels = array(
		E_ERROR			=>	'Error',
		E_WARNING		=>	'Warning',
		E_PARSE			=>	'Parsing Error',
		E_NOTICE		=>	'Notice',
		E_CORE_ERROR		=>	'Core Error',
		E_CORE_WARNING		=>	'Core Warning',
		E_COMPILE_ERROR		=>	'Compile Error',
		E_COMPILE_WARNING	=>	'Compile Warning',
		E_USER_ERROR		=>	'User Error',
		E_USER_WARNING		=>	'User Warning',
		E_USER_NOTICE		=>	'User Notice',
		E_STRICT		=>	'Runtime Notice'
	);

	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		$this->ob_level = ob_get_level();
		// Note: Do not log messages from this constructor.
	}

	// --------------------------------------------------------------------

	/**
	 * Exception Logger
	 *
	 * Logs PHP generated error messages
	 *
	 * @param	int	$severity	Log level
	 * @param	string	$message	Error message
	 * @param	string	$filepath	File path
	 * @param	int	$line		Line number
	 * @return	void
	 */
	public static function log_exception($severity, $message, $filepath, $line)
	{
		$severity = isset(self::$levels[$severity]) ? self::$levels[$severity] : $severity;
		Common::log_message('error', 'Severity: '.$severity.' --> '.$message.' '.$filepath.' '.$line);
	}

	// --------------------------------------------------------------------

	/**
	 * 404 Error Handler
	 *
	 * @uses	Debbuger::show_error()
	 *
	 * @param	string	$page		Page URI
	 * @param 	bool	$log_error	Whether to log the error
	 * @return	void
	 */
	public static function show_404($page = '', $log_error = TRUE)
	{
		if (Common::is_cli())
		{
			$heading = 'Not Found';
			$message = 'The controller/method pair you requested was not found.';
		}
		else
		{
			$heading = '404 Page Not Found';
			$message = 'The requested URL was not found on this server.';
		}

		// By default we log this, but allow a dev to skip it
		if ($log_error)
		{
			log_message('error', $heading.': '.$page);
		}

		echo self::show_error($heading, $message, 'error_404', 404);
		exit(4); // EXIT_UNKNOWN_FILE
	}

	// --------------------------------------------------------------------

	/**
	 * General Error Page
	 *
	 * Takes an error message as input (either as a string or an array)
	 * and displays it using the specified template.
	 *
	 * @param	string		$heading	Page heading
	 * @param	string|string[]	$message	Error message
	 * @param	string		$template	Template name
	 * @param 	int		$status_code	(default: 500)
	 *
	 * @return	string	Error page output
	 */
	public function show_error($heading, $message, $template = 'error_general', $status_code = 500)
	{
		$templates_path = ConfigManager::get(url.error_views_path);
		if (empty($templates_path))
		{
			$templates_path = BR_PATH.'Resources'.DIRECTORY_SEPARATOR.Config::get(theme.storage_path).DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR;
		}

		if (is_cli())
		{
			$message = "\t".(is_array($message) ? implode("\n\t", $message) : $message);
			$template = 'cli'.DIRECTORY_SEPARATOR.$template;
		}
		else
		{
			set_status_header($status_code);
			$message = '<p>'.(is_array($message) ? implode('</p><p>', $message) : $message).'</p>';
			$template = 'html'.DIRECTORY_SEPARATOR.$template;
		}

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}
		ob_start();
		include($templates_path.$template.'.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}

	// --------------------------------------------------------------------

	public function show_exception($exception)
	{
		$templates_path = ConfigManager::get(url.error_views_path);
		if (empty($templates_path))
		{
			$templates_path = BR_PATH.'Resources'.DIRECTORY_SEPARATOR.Config::get(theme.storage_path).DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR;
		}

		$message = $exception->getMessage();
		if (empty($message))
		{
			$message = '(null)';
		}

		if (is_cli())
		{
			$templates_path .= 'cli'.DIRECTORY_SEPARATOR;
		}
		else
		{
			$templates_path .= 'html'.DIRECTORY_SEPARATOR;
		}

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}

		ob_start();
		include($templates_path.'error_exception.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		echo $buffer;
	}

	// --------------------------------------------------------------------

	/**
	 * Native PHP error handler
	 *
	 * @param	int	$severity	Error level
	 * @param	string	$message	Error message
	 * @param	string	$filepath	File path
	 * @param	int	$line		Line number
	 * @return	void
	 */
	public function show_php_error($severity, $message, $filepath, $line)
	{
		$templates_path = ConfigManager::get(url.error_views_path);
		if (empty($templates_path))
		{
			$templates_path = BR_PATH.'Resources'.DIRECTORY_SEPARATOR.Config::get(theme.storage_path).DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR;
		}

		$severity = isset($this->levels[$severity]) ? $this->levels[$severity] : $severity;

		// For safety reasons we don't show the full file path in non-CLI requests
		if ( ! is_cli())
		{
			$filepath = str_replace('\\', '/', $filepath);
			if (FALSE !== strpos($filepath, '/'))
			{
				$x = explode('/', $filepath);
				$filepath = $x[count($x)-2].'/'.end($x);
			}

			$template = 'html'.DIRECTORY_SEPARATOR.'error_php';
		}
		else
		{
			$template = 'cli'.DIRECTORY_SEPARATOR.'error_php';
		}

		if (ob_get_level() > $this->ob_level + 1)
		{
			ob_end_flush();
		}
		ob_start();
		include($templates_path.$template.'.php');
		$buffer = ob_get_contents();
		ob_end_clean();
		echo $buffer;
	}
}
