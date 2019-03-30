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

use Rlis\RadeMinify\RadeMinify;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;


/**
 *  The Debugger
 * -----------------------------------------------------------------------
 *
 * Provides the developer with useful messages in case of an exception or
 * errors happen.
 *
 */
class Debugger extends \Exception
{
    private static $profiles = [];
    private static $time_start = 0;
    private static $profilerStartTime = 0;

    /** @var array */
    private $_vars = array();
    /** @var string */
    private static $_startTime;
	/** @var string */
    private static $_endTime;
	/** @var string */
    private static $_startMemoryUsage;
	/** @var string */
    private static $_endMemoryUsage;
	/** @var array */
	private static $_arrGeneral;
	/** @var array */
    private static $_arrParams;
	/** @var array */
    private static $_arrConsole;
	/** @var array */
    private static $_arrWarnings;    
	/** @var array */
    private static $_arrErrors;
	/** @var array */
	private static $_arrQueries;
	/** @var array */
	private static $_arrData;
	/** @var float */
	private static $_sqlTotalTime = 0;

    /**
     * Registering the debugger to log exceptions locally or transfer them to
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
    public function start()
    {
        if (getenv('DEBUG') === '0' || getenv('DEBUG') === '1') {register_shutdown_function('Radion\Debugger::error_handler');
        } else if (getenv('DEBUG') === '2') {$whoops = new Run;
            $whoops->pushHandler(new PrettyPageHandler);

            if (\Whoops\Util\Misc::isAjaxRequest()) {
                $jsonHandler = new JsonResponseHandler();
                $jsonHandler->setJsonApi(true);
                $whoops->pushHandler($jsonHandler);
            }

            $whoops->register();
        } else if (getenv('DEBUG') === '-1') {}session_name('RADIONSESSID');
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
            Debugger::display('wrong', 'Maintainance Mode', "Sorry but the application is being maintained, we'll back shortly");
            echo "<script>document.title = 'Maintainance Mode';</script>";
            exit(1);
        } else if (getenv('ENVIRONMENT') == 'production') {
            ini_set('display_errors', 0);
            if (version_compare(PHP_VERSION, '5.6', '>=')) {
                error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
            } else {
                error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_USER_NOTICE);
            }
        } else if (getenv('ENVIRONMENT') == 'debug') {
            ini_set('display_errors', 0);
            error_reporting(-1);
            self::$_endTime = (self::microtime_diff(BR_START) * 1000);
            self::$_endMemoryUsage = memory_get_usage();
            $htmlCompression = (Config::get('theme','compression') === true) ? true : false;

            $nl = "\n";

            // Retrieve stored error messages and show them, then remove
            if ($debugError = \Session::get('debug-errors')) {
                self::addMessage('errors', 'debug-errors', $debugError);
                \Session::remove('debug-errors');
            }
            if ($debugWarning = \Session::get('debug-warnings')) {
                self::addMessage('warnings', 'debug-warnings', $debugWarning);
                \Session::remove('debug-warnings');
            }

            $totalParams = (self::$_arrParams);
            $totalConsole = (self::$_arrConsole);
            $totalWarnings = (self::$_arrWarnings);
            $totalErrors = (self::$_arrErrors);
            $totalQueries = (self::$_arrQueries);

            // Debug bar status
            $debugBarState = isset($_COOKIE['__debugstate']) ? $_COOKIE['debugBarState'] : 'radion';
            $onDblClick = 'appTabsMinimize()';

            $panelAlign = 'left';
            $panelTextAlign = 'left';
            $output = $nl . '<style type="text/css">
			#debug-panel {opacity:0.9;position:fixed;bottom:0;left:0;z-index:2000;width:100%;max-height:90%;font:12px tahoma, verdana, sans-serif;color:#000;}
			#debug-panel fieldset {padding:0px 10px;background-color:#fff;border:1px solid #ccc;width:98%;margin:0px auto 0px auto;text-align:' . $panelTextAlign . ';}
			#debug-panel fieldset legend {float:' . $panelAlign . ';background-color:#f9f9f9;padding:5px 5px 4px 5px;border:1px solid #ccc;border-left:1px solid #ddd;border-bottom:1px solid #f4f4f4;margin:-15px 0 0 10px;font:12px tahoma, verdana, sans-serif;width:auto;}
			#debug-panel fieldset legend ul {color:#999;font-weight:normal;margin:0px;padding:0px;}
			#debug-panel fieldset legend ul li{float:left;width:auto;list-style-type:none;}
			#debug-panel fieldset legend ul li.title{min-width:50px;width:auto;padding:0 2px;}
			#debug-panel fieldset legend ul li.narrow{width:auto;padding:0 2px;}
			#debug-panel fieldset legend ul li.item{width:auto;padding:0 12px;border-right:1px solid #999;}
			#debug-panel fieldset legend ul li.item:last-child{padding:0 0 0 12px;border-right:0px;}
			#debug-panel a {text-decoration:none;text-transform:none;color:#bbb;font-weight:normal;}
			#debug-panel a.debugArrow {color:#222;}
			#debug-panel a.black {color:#222;}
            #debug-panel pre {border:0px;}
			#debug-panel strong {font-weight:bold;}
			#debug-panel .tab-orange { color:#d15600 !important; }
			#debug-panel .tab-red { color:#cc0000 !important; }
			@media (max-width: 680px) {
				#debug-panel fieldset legend ul li.item a {display:block;visibility:hidden;}
				#debug-panel fieldset legend ul li.item a:first-letter {visibility:visible !important;}
				#debug-panel fieldset legend ul li.item {width:30px; height:15px; margin-bottom:3px;)
			}
		</style>
		<script type="text/javascript">
			var arrDebugTabs = ["General","Params","Console","Warnings","Errors","Queries"];
			var debugTabsHeight = "200px";
			var cssText = keyTab = "";
			function appSetCookie(state, tab){ document.cookie = "debugBarState="+state+"; path=/"; if(tab !== null) document.cookie = "debugBarTab="+tab+"; path=/"; }
			function appGetCookie(name){ if(document.cookie.length > 0){ start_c = document.cookie.indexOf(name + "="); if(start_c != -1){ start_c += (name.length + 1); end_c = document.cookie.indexOf(";", start_c); if(end_c == -1) end_c = document.cookie.length; return unescape(document.cookie.substring(start_c,end_c)); }} return ""; }
			function appTabsMiddle(){ appExpandTabs("middle", appGetCookie("debugBarTab")); }
			function appTabsMaximize(){ appExpandTabs("max", appGetCookie("debugBarTab")); }
			function appTabsMinimize(){ appExpandTabs("min", "General"); }
			function appExpandTabs(act, key){
				if(act == "max"){ debugTabsHeight = "500px"; }
				else if(act == "middle"){ debugTabsHeight = "200px"; }
				else if(act == "min"){ debugTabsHeight = "0px";	}
				else if(act == "auto"){
					if(debugTabsHeight == "0px"){ debugTabsHeight = "200px"; act = "middle"; }
					else if(debugTabsHeight == "200px"){ act = "middle"; }
					else if(debugTabsHeight == "500px"){ act = "max"; }
				}
				keyTab = (key == null) ? "General" : key;
				document.getElementById("debugArrowExpand").style.display = ((act == "max") ? "none" : (act == "middle") ? "none" : "");
				document.getElementById("debugArrowCollapse").style.display = ((act == "max") ? "" : (act == "middle") ? "" : "none");
				document.getElementById("debugArrowMaximize").style.display = ((act == "max") ? "none" : (act == "middle") ? "" : "");
				document.getElementById("debugArrowMinimize").style.display = ((act == "max") ? "" : (act == "middle") ? "none" : "none");
				for(var i = 0; i < arrDebugTabs.length; i++){
					if(act == "min" || arrDebugTabs[i] != keyTab){
						document.getElementById("content"+arrDebugTabs[i]).style.display = "none";
						document.getElementById("tab"+arrDebugTabs[i]).style.cssText = "color:#bbb;";
					}
				}
				if(act != "min"){
					document.getElementById("content"+keyTab).style.display = "";
					document.getElementById("content"+keyTab).style.cssText = "width:100%;height:"+debugTabsHeight+";overflow-y:auto;";
					if(document.getElementById("tab"+keyTab).className == "tab-orange"){
						cssText = "color:#b13600 !important;";
					}else if(document.getElementById("tab"+keyTab).className == "tab-red"){
						cssText = "color:#aa0000 !important;";
					}else{
						cssText = "color:#222;";
					}
					document.getElementById("tab"+keyTab).style.cssText = cssText;
				}
				document.getElementById("debug-panel").style.opacity = (act == "min") ? "0.9" : "1";
				appSetCookie(act, key);
			}
		</script>

		<div id="debug-panel">
		<fieldset>
		<legend id="debug-panel-legend">
			<ul>
				<li class="title"><b style="color:#222">' . 'Debug' . '</b>:&nbsp;</li>
				<li class="narrow"><a id="debugArrowExpand" class="debugArrow" style="display:;" href="javascript:void(0)" title="Expand" onclick="javascript:appTabsMiddle()">&#9650;</a></li>
				<li class="narrow"><a id="debugArrowCollapse" class="debugArrow" style="display:none;" href="javascript:void(0)" title="Collapse" onclick="javascript:appTabsMinimize()">&#9660;</a></li>
				<li class="narrow"><a id="debugArrowMaximize" class="debugArrow" style="display:;" href="javascript:void(0)" title="Maximize" onclick="javascript:appTabsMaximize()">&#9744;</a></li>
				<li class="narrow"><a id="debugArrowMinimize" class="debugArrow" style="display:none;" href="javascript:void(0)" title="Minimize" onclick="javascript:appTabsMiddle()">&#9635;</a></li>
				<li class="item"><a id="tabGeneral" href="javascript:void(\'General\')" onclick="javascript:appExpandTabs(\'auto\', \'General\')" ondblclick="javascript:' . $onDblClick . '">' . 'General' . '</a></li>
				<li class="item"><a id="tabParams" href="javascript:void(\'Params\')" onclick="javascript:appExpandTabs(\'auto\', \'Params\')" ondblclick="javascript:' . $onDblClick . '">' . 'Params' . ' ' . $totalParams . '</a></li>
				<li class="item"><a id="tabConsole" href="javascript:void(\'Console\')" onclick="javascript:appExpandTabs(\'auto\', \'Console\')" ondblclick="javascript:' . $onDblClick . '">' . 'Console' . ' ' . $totalConsole . '</a></li>
				<li class="item"><a id="tabWarnings" href="javascript:void(\'Warnings\')" ' . ($totalWarnings ? 'class="tab-orange"' : '') . ' onclick="javascript:appExpandTabs(\'auto\', \'Warnings\')" ondblclick="javascript:' . $onDblClick . '">' . 'Warnings' . ' ' . $totalWarnings . '</a></li>
				<li class="item"><a id="tabErrors" href="javascript:void(\'Errors\')" ' . ($totalErrors ? 'class="tab-red"' : '') . ' onclick="javascript:appExpandTabs(\'auto\', \'Errors\')" ondblclick="javascript:' . $onDblClick . '">' . 'Errors' . ' ' . $totalErrors . '</a></li>
				<li class="item"><a id="tabQueries" href="javascript:void(\'Queries\')" onclick="javascript:appExpandTabs(\'auto\', \'Queries\')" ondblclick="javascript:' . $onDblClick . '">' . 'SQL Queries' . ' ' . $totalQueries . '</a></li>
			</ul>
		</legend>

		<div id="contentGeneral" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';

            $output .= 'Script name' . ': ' . Config::get('info','SOFTWARE') . '<br>';
            $output .= 'Script by' . ': ' . '[ <a href="https://www.biustudios.ml" class="black">' . 'BiuStudios' . '</a> ]' . '<br>';
            $output .= 'Framework version' . ': ' . Config::get('info','VERSION') . '<br>';
            $output .= 'PHP version' . ': ' . phpversion() . '<br>';
            $output .= 'Framework Environment' . ': ' . getenv('ENVIRONMENT') . '<br>';

            $totalRunningTime = round((float) self::$_endTime - (float) self::$_startTime, 5);
            $totalRunningTimeSql = round($totalRunningTime - (float) self::$_sqlTotalTime, 5);
            $totalRunningTimeScript = round($totalRunningTime - $totalRunningTimeSql, 5);
            $totalMemoryUsage = \Convert::fileSize((float) self::$_endMemoryUsage - (float) self::$_startMemoryUsage);
            $htmlCompressionRate = !empty(self::$_arrData['html-compression-rate']) ? self::$_arrData['html-compression-rate'] : 'Unknown';

            $output .= 'Total running time' . ': ' . $totalRunningTime . ' ' . 'miliseconds' . '.<br>';
            $output .= 'Script running time' . ': ' . $totalRunningTimeSql . ' ' . 'miliseconds' . '.<br>';
            $output .= 'SQL running time' . ': ' . $totalRunningTimeScript . ' ' . 'miliseconds' . '.<br>';
            $output .= 'Total memory usage' . ': ' . $totalMemoryUsage . '<br><br>';


            $output .= 'GZip ' . 'Output compression' . ': ' . (Config::get('theme','compression') ? 'enabled' : 'no') . '<br>';
            $output .= 'HTML ' . 'Output compression' . ': ' . (Config::get('theme','compression') ? 'enabled' . ' (' . 'compression rate' . ': ' . $htmlCompressionRate . ')' : 'no') . '<br>';

            $output .= 'Action' . ': [ <a href="web?cache=clear" class="black">' . 'Clear Caches Only' . '</a> ] <br>';


            $output .= '</div>

		<div id="contentParams" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';

            $output .= '<strong>APPLICATION</strong>:';
            $output .= '<br><br>';

            $files = array();
            $data = get_included_files();

            foreach ($data as $file_path) {
                // Include only BR_PATH.'Application'
                if (strpos($file_path, BR_PATH.'Application')) {
                    $file = str_replace(BR_PATH.'Application', '', $file_path);
                    $files[$file] = is_array($file_path) ? $file_path : strip_tags($file_path);
                }
            }
            $files = print_r($files, true);
            $output .= $htmlCompression ? nl2br($files) : $files;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>$_GET</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrGet = array();
            if (isset($_GET)) {
                foreach ($_GET as $key => $val) {
                    $arrGet[$key] = is_array($val) ? $val : strip_tags($val);
                }
            }
            $arrGet = print_r($arrGet, true);
            $output .= $htmlCompression ? nl2br($arrGet) : $arrGet;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>$_POST</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrPost = array();
            if (isset($_POST)) {
                foreach ($_POST as $key => $val) {
                    $arrPost[$key] = is_array($val) ? $val : strip_tags($val);
                }
            }
            $arrPost = print_r($arrPost, true);
            $output .= $htmlCompression ? nl2br($arrPost) : $arrPost;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>$_FILES</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrFiles = array();
            if (isset($_FILES)) {
                foreach ($_FILES as $key => $val) {
                    $arrFiles[$key] = is_array($val) ? $val : strip_tags($val);
                }
            }
            $arrFiles = print_r($arrFiles, true);
            $output .= $htmlCompression ? nl2br($arrFiles) : $arrFiles;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>$_COOKIE</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrCookie = array();
            if (isset($_COOKIE)) {
                foreach ($_COOKIE as $key => $val) {
                    $arrCookie[$key] = is_array($val) ? $val : strip_tags($val);
                }
            }
            $arrCookie = print_r($arrCookie, true);
            $output .= $htmlCompression ? nl2br($arrCookie) : $arrCookie;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>$_SESSION</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrSession = array();
            if (isset($_SESSION)) {
                foreach ($_SESSION as $key => $val) {
                    $arrSession[$key] = is_array($val) ? $val : strip_tags($val);
                }
            }
            $arrSession = print_r($arrSession, true);
            $output .= $htmlCompression ? nl2br($arrSession) : $arrSession;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '<strong>CONSTANTS</strong>:';
            $output .= '<pre style="white-space:pre-wrap;">';
            $arrConstants = @get_defined_constants(true);
            $arrUserConstants = isset($arrConstants['user']) ? print_r($arrConstants['user'], true) : array();
            $output .= $htmlCompression ? nl2br($arrUserConstants) : $arrUserConstants;
            $output .= '</pre>';
            $output .= '<br>';

            $output .= '</div>

		<div id="contentConsole" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';
            $output .= '<pre>';
            $arrConsole = print_r(self::$_arrConsole, true);
            $output .= $htmlCompression ? nl2br($arrConsole) : $arrConsole;
            $output .= '</pre>';
            $output .= '</div>

		<div id="contentWarnings" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';
            $output .= '<pre>';
            /**foreach (self::$_arrWarnings as $warnKey => $warnVal) {
                $output .= ($warnKey) . '<br>';
                if (is_array($warnVal)) {
                    foreach ($warnVal as $warnValVal) {
                        $output .= '- ' . $warnValVal . '<br>';
                    }
                } else {
                    $output .= '- ' . $warnVal[0] . '<br>';
                }
            }*/
            $output .= '</pre>';
            $output .= '<br>';
            $output .= '</div>

		<div id="contentErrors" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';
            /**foreach (self::$_arrErrors as $msg) {
                $output .= '<pre style="white-space:normal;word-wrap:break-word;">';
                $msg = print_r($msg, true);
                $output .= $htmlCompression ? nl2br($msg) : $msg;
                $output .= '</pre>';
                $output .= '<br>';
            }*/
            $output .= '</div>

		<div id="contentQueries" style="display:none;padding:10px;width:100%;height:200px;overflow-y:auto;">';
            $output .= 'SQL running time' . ': ' . $totalRunningTimeScript . ' sec.<br><br>';
            /**foreach (self::$_arrQueries as $msgKey => $msgVal) {
                $output .= $msgKey . '<br>';
                $output .= $msgVal[0] . '<br><br>';
            }*/
            $output .= '</div>

		</fieldset>
		</div>';

            if ($debugBarState == 'max') {
                $output .= '<script type="text/javascript">appTabsMaximize();</script>';
            } elseif ($debugBarState == 'middle') {
                $output .= '<script type="text/javascript">appTabsMiddle();</script>';
            } else {
                $output .= '<script type="text/javascript">appTabsMinimize();</script>';
            }

            // Compresss output
            if (Config::get('theme','compression') === true) {
                $output = RadeMinify::html($output);
                echo ($output);
            } else if (Config::get('theme', 'compression') == false) {
                echo ($output);
            } else {
                header('HTTP/1.1 503 Service Unavailable.', true, 503);
                Debugger::display('wrong', 'Compression Notice', 'The application compression was not set correctly');
                exit(1);
            }

        } else {
            header('HTTP/1.1 503 Service Unavailable.', true, 503);
            Debugger::display('wrong', 'Environment not defined', 'The application environment is not set correctly');
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
     * @see Debugger::set_header(), Debugger::display()
     * @access public
     * @since Method available since Release 0.1.0
     */
    public static function report($code, $terminate = false)
    {
        switch ($code) {
            case '404':
                self::set_header('404', 'Internal Server Error');
                self::display('simple', '404 Not Found', 'The requested URL was not found on this server.');
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
     * @see Debugger::start(), Debugger::display()
     * @access public
     * @since Method available since Release 0.1.0
     */
    public static function error_handler()
    {
        $error = error_get_last();
        $message = $error['message'];
        if ($error) {
            if (getenv('DEBUG') == 0 || getenv('ENVIRONMENT') == 'production') {
                self::display('wrong', 'Something went wrong');
            } else {
                header('HTTP/1.1 503 Service Unavailable.', true, 503);
                self::display('full', $error);
                exit(1);
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
    public static function display($name, $message = '', $description = '')
    {
        self::set_header('500', 'Internal Server Error');
        include BR_PATH . 'Resources/' . Config::get('theme', 'storage_path') . '/errors/html/' . $name . '.php';
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
     * @see Debugger::exec_time()
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
     * Add message to the stack
     * @param float $time
     * @return void
     */
    public static function addSqlTime($time = 0)
    {		
		self::$_sqlTotalTime += (float)$time;
	}

    /**
     * Add message to the stack
     * @param string $type
     * @param string $key
     * @param string $val
     * @param string $storeType
     */
    public static function addMessage($type = 'params', $key = '', $val = '', $storeType = '')
    {
        
		// Store message in session
        if($storeType == 'session'){
            Session::set('debug-'.$type, $val);
			return false;
        }
		
        if($type == 'general') self::$_arrGeneral[$key][] = Filter::sanitize('string', $val);
		elseif($type == 'params') self::$_arrParams[$key] = Filter::sanitize('string', $val);
        elseif($type == 'errors') self::$_arrErrors[$key][] = Filter::sanitize('string', $val);
		elseif($type == 'warnings') self::$_arrWarnings[$key][] = Filter::sanitize('string', $val);
		elseif($type == 'queries') self::$_arrQueries[$key][] = Filter::encode($val);
		else if($type == 'data') self::$_arrData[$key] = $val;
		elseif($type == 'console'){
			if(is_array($val)){
				$value = $val;
			}elseif(is_object($val)){
				$value = array('class'=>get_class($val), 'properties'=>get_object_vars($val), 'methods'=>get_class_methods($val));				
			}else{
				$value = Filter::sanitize('string', $val);	
			}
			
			$key = Filter::sanitize('string', $key);
			if($key != ''){
				self::$_arrConsole[$key] = $value;
			}else{
				self::$_arrConsole[] = $value;
			}
		}
    }

    /**
	 *	Returns all defined variables for current view
	 *	@return array
	 */
    public function getAllVars()
    {
        return $this->_vars;
	}

    /**
     * Get message from the stack
     * @param string $type
     * @param string $key
     * @return string 
     */
    public static function __getMessage($type = 'params', $key = '')
    {
		$output = '';
		
        if($type == 'errors') $output = isset(self::$_arrErrors[$key]) ? self::$_arrErrors[$key] : '';

		return $output;
    }

    /**
     * Display execution time (start time - finish time) in human readable form
     * (milliseconds).
     *
     *
     * @static
     * @see Debugger::microtime_diff()
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
     * Get formatted microtime
     * @return float
     */
    private static function _getFormattedMicrotime()
    {    
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }
}
