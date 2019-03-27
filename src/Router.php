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

use Radion\Config;
use Radion\Debugger;
use Radion\Viewer;

/**
 * Class Router.
 *
 */
class Router
{
    private static $mimeTypes;
    private static $config = null;
    private static $halts = false;
    private static $routes = [];
    private static $methods = [];
    private static $callbacks = [];
    private static $patterns = [
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*',
    ];

    private static $isGroup = false;
    private static $groupHalt = false;
    private static $groupController;
    private static $groupMethodName;

    private static $currentURI;
    private static $currentRoute;

    private static $errorCallback;

    /**
     * Defines a route w/ callback and method.
     *
     * @param $method
     * @param $params
     *
     */
    public static function __callstatic($method, $params)
    {
        if ($method == 'group') {
            // Seperate controller name and the method
            $segments = explode('@', $params[0]);

            // Instanitate controller
            self::$groupController = new $segments[0]();
            self::$groupMethodName = $segments[1];
            self::$isGroup = true;

            // Access route groups
            call_user_func($params[1]);

            self::runDispatcher();

            self::$groupController = null;
            self::$groupMethodName = null;
            self::$isGroup = false;
        } else {
            // remove leading slash which may cause problems in Linux servers
            $params[0] = trim($params[0], '/');
            $uri = dirname($_SERVER['SCRIPT_NAME']).'/'.$params[0];
            $callback = $params[1];
            array_push(self::$routes, $uri);
            array_push(self::$methods, strtoupper($method));
            array_push(self::$callbacks, $callback);
            self::runDispatcher();
        }
    }

    /**
     * Load the configuration file.
     *
     */
    public static function start()
    {
        if (self::$config === null) {
            self::$config = Config::get('routes');
            self::$mimeTypes = Config::get('mimetypes');
        }

        foreach (self::$config['routes'] as $route) {
            include self::$config['path'].$route.'.php';
        }
    }

    /**
     * @param bool $flag
     *
     */
    public static function haltOnMatch($flag = true)
    {
        self::$halts = $flag;
    }

    /**
     * Run the dispatcher for the last time to collect all non-grouped
     * routes. Will throw a 404 error if any route is not found.
     *
     */
    public static function dispatch()
    {
        self::runDispatcher();
        if (!self::$halts && !self::$groupHalt) {
            Debugger::report(404);
        }
    }

    /**
     * @param $url
     * @param bool $permanent
     *
     */
    public static function redirect($url, $permanent = false)
    {
        if (headers_sent() === false) {
            header('Location: '.$url, true, ($permanent === true) ? 301 : 302);
        }
        exit();
    }

    /**
     * @param $file_name
     *
     * @return mixed|string
     *
     */
    public static function getMimeType($file_name)
    {
        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        if (isset(static::$mimeTypes['.'.$ext])) {
            return static::$mimeTypes['.'.$ext];
        } else {
            return 'text/plain';
        }
    }

    /**
     * @return mixed|string
     *
     */
    public static function getCurrentURI()
    {
        return self::$currentURI;
    }

    /**
     * @param $file_name
     *
     * @return mixed|string
     *
     */
    public static function getCurrentRoute()
    {
        $a = self::findOverlap(BR_PATH, self::$currentURI)[0];

        return str_replace($a, '', self::$currentURI);
    }

    /**
     * @param $str1
     * @param $str2
     *
     * @return array|bool
     *

     */
    public static function findOverlap($str1, $str2)
    {
        $return = [];
        $sl1 = strlen($str1);
        $sl2 = strlen($str2);
        $max = $sl1 > $sl2 ? $sl2 : $sl1;
        $i = 1;
        while ($i <= $max) {
            $s1 = substr($str1, -$i);
            $s2 = substr($str2, 0, $i);
            if ($s1 == $s2) {
                $return[] = $s1;
            }
            $i++;
        }
        if (!empty($return)) {
            return $return;
        }

        return false;
    }

    /**
     * Runs the callback for the given request.
     *

     */
    private static function runDispatcher()
    {
        if (self::$groupHalt || self::$halts) {
            return;
        }
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);
        $found_route = false;

        self::$routes = str_replace('//', '/', self::$routes);
        // Check if route is defined without regex

        if (in_array($uri, self::$routes)) {
            $route_pos = array_keys(self::$routes, $uri);
            foreach ($route_pos as $route) {
                // Using an ANY option to match both GET and POST requests
                if (self::$methods[$route] === $method || self::$methods[$route] === 'ANY') {
                    //dd(preg_match('#^' . $route . '$#', $uri, $matched));

                    // If route is not an object
                    if (!is_object(self::$callbacks[$route])) {
                        self::haltOnMatch();
                        $found_route = true;
                        self::$currentURI = $uri;

                        if (self::$isGroup) {
                            self::$groupHalt = true;
                            self::$groupController->{self::$groupMethodName}();
                        }

                        // Grab all parts based on a / separator
                        $parts = explode('/', self::$callbacks[$route]);

                        // Collect the last index of the array
                        $last = end($parts);

                        // Grab the controller name and method call
                        $segments = explode('@', $last);

                        if (count($segments) >= 2) {
                            // Instanitate controller
                            $controller = new $segments[0]();

                            // Call method
                            $controller->{$segments[1]}();
                        } else {
                            Viewer::file(self::$callbacks[$route]);
                        }

                        if (self::$halts) {
                            return true;
                        }
                    } else {
                        self::haltOnMatch();
                        $found_route = true;
                        self::$currentURI = $uri;

                        // Call closure
                        if (self::$isGroup) {
                            self::$groupHalt = true;
                            self::$groupController->{self::$groupMethodName}();
                        }

                        if (is_object(self::$callbacks[$route])) {
                            call_user_func(self::$callbacks[$route]);
                        } else {
                            Viewer::file(self::$callbacks[$route]);
                        }

                        if (self::$halts) {
                            return true;
                        }
                    }
                }
            }
        } else {
            // Check if defined with regex
            $pos = 0;
            foreach (self::$routes as $route) {
                if (strpos($route, ':') !== false) {
                    $route = str_replace($searches, $replaces, $route);
                }

                if (preg_match('#^'.$route.'$#', $uri, $matched)) {
                    if (self::$methods[$pos] === $method || self::$methods[$pos] === 'ANY') {
                        self::haltOnMatch();
                        $found_route = true;
                        self::$currentURI = $uri;

                        if (self::$isGroup) {
                            self::$groupHalt = true;
                            self::$groupController->{self::$groupMethodName}();
                        }

                        // Remove $matched[0] as [1] is the first parameter.
                        array_shift($matched);
                        if (!is_object(self::$callbacks[$pos])) {
                            // Grab all parts based on a / separator
                            $parts = explode('/', self::$callbacks[$pos]);
                            // Collect the last index of the array
                            $last = end($parts);
                            // Grab the controller name and method call
                            $segments = explode('@', $last);
                            // Instanitate controller
                            $controller = new $segments[0]();

                            // Fix multi parameters
                            if (!method_exists($controller, $segments[1])) {
                                //"controller and action not found"
                                Debugger::report(500);
                            } else {
                                call_user_func_array([$controller, $segments[1]], $matched);
                            }
                            if (self::$halts) {
                                return;
                            }
                        } else {
                            self::haltOnMatch();
                            $found_route = true;
                            self::$currentURI = $uri;

                            if (self::$isGroup) {
                                self::$groupHalt = true;
                                self::$groupController->{self::$groupMethodName}();
                            }

                            call_user_func_array(self::$callbacks[$pos], $matched);
                            if (self::$halts) {
                                return;
                            }
                        }
                    } else {
                        // continue searching
                    }
                }
                $pos++;
            }
        }

        // Tell if there is no found grouped routes
        return false;
    }
}
