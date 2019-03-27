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
 * The Configuration Loader
 * -----------------------------------------------------------------------
 *
 * The Configuration loader are responsible to read and return the
 * configurations in a form of array.
 *
 * Usage:
 * 1. Get the entire configuration from a file:
 * 	  $config = Config::get('filename');
 *
 * 2. Get specific configuration from a file:
 *    $config = Config::get('filename', 'configuration_key');
 *
 */
class Config {

	/**
	 * The array of configuration from config/env.php
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $env = null;

	/**
	 * The array of configuration from files located on config directory
	 * @var array
	 * @access protected
	 * @static
	 */
	protected static $hive = null;

	/**
	 * Link a variable or an object to the container
	 *
	 * @param string	$file 	the configuration file name (without .php)
	 * @param string	$key	the array key
	 *
	 * @return array	$hive	the array of configurations
	 *
	 * @static
	 * @access public
	 * @since Method available since 0.1.1
	 */
	public static function get($file, $key = null){
		if(isset(self::$hive[$file]) === false){
			self::$hive[$file] = include_once(BR_PATH.'Config/'.$file.'.phtml');
		}

		if($key === null){
			return self::$hive[$file];
		}else{
			return self::$hive[$file][$key];
		}
	}

	/**
	 * Reads the configuration file (config/env.php) and include each of the
	 * variables (retrieved in a form of associative array) to the Environment
	 * Variable. Also store the configurations into static variable $env
	 *
	 * @static
	 * @access public
	 * @since Method available since Release 0.1.1
	 */
	public static function setEnv(){
		if(self::$env === null){
			self::$env = require_once(BR_PATH.'Config/env.php');
		}

		foreach(self::$env as $v => $a){
			putenv($v.'='.$a);
		}
	}
}