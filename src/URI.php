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

use Radion\CommonManager as Common;

class URIManager
{

    /**
	 * List of cached URI segments
	 *
	 * @var	array
	 */
    public $keyval = array();

    /**
	 * Current URI string
	 *
	 * @var	string
	 */
    public $uri_string = '';

    /**
	 * List of URI segments
	 *
	 * Starts at 1 instead of 0.
	 *
	 * @var	array
	 */
    public $segments = array();

    /**
	 * List of routed URI segments
	 *
	 * Starts at 1 instead of 0.
	 *
	 * @var	array
	 */
    public $rsegments = array();

    /**
	 * Permitted URI chars
	 *
	 * PCRE character group allowed in URI segments
	 *
	 * @var	string
	 */
    protected $_permitted_uri_chars;

    /**
	 * Class constructor
	 *
	 * @return	void
	 */
    public function __construct()
    {
        $this->config = new \Radion\ConfigManager;

        // If query strings are enabled, we don't need to parse any segments.
        // However, they don't make sense under CLI.
        if (Common::is_cli() or ConfigManager::_get('url','enable_query_strings') !== true) {
                $this->_permitted_uri_chars = ConfigManager::_get('url','permitted_uri_chars');

                // If it's a CLI request, ignore the configuration
                if (Common::is_cli()) {
                        $uri = $this->_parse_argv();
                    } else {
                        $protocol = ConfigManager::_get('url','uri_protocol');
                        empty($protocol) && $protocol = 'REQUEST_URI';

                        switch ($protocol) {
                            case 'AUTO': // For BC purposes only
                            case 'REQUEST_URI':
                                $uri = $this->_parse_request_uri();
                                break;
                            case 'QUERY_STRING':
                                $uri = $this->_parse_query_string();
                                break;
                            case 'PATH_INFO':
                            default:
                                $uri = isset($_SERVER[$protocol])
                                    ? $_SERVER[$protocol]
                                    : $this->_parse_request_uri();
                                break;
                        }
                    }

                $this->_set_uri_string($uri);
            }

        Common::log_message('info', 'URI Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
	 * Set URI String
	 *
	 * @param 	string	$str
	 * @return	void
	 */
    protected function _set_uri_string($str)
    {
        // Filter out control characters and trim slashes
        $this->uri_string = trim(remove_invisible_characters($str, false), '/');

        if ($this->uri_string !== '') {
                // Remove the URL suffix, if present

                $this->segments[0] = null;
                // Populate the segments array
                foreach (explode('/', trim($this->uri_string, '/')) as $val) {
                        $val = trim($val);
                        // Filter segments for security
                        $this->filter_uri($val);

                        if ($val !== '') {
                                $this->segments[] = $val;
                            }
                    }

                unset($this->segments[0]);
            }
    }

    // --------------------------------------------------------------------

    /**
	 * Parse REQUEST_URI
	 *
	 * Will parse REQUEST_URI and automatically detect the URI from it,
	 * while fixing the query string if necessary.
	 *
	 * @return	string
	 */
    protected function _parse_request_uri()
    {
        if (!isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
                return '';
            }

        // parse_url() returns false if no host is present, but the path or query string
        // contains a colon followed by a number
        $uri = parse_url('http://dummy' . $_SERVER['REQUEST_URI']);
        $query = isset($uri['query']) ? $uri['query'] : '';
        $uri = isset($uri['path']) ? $uri['path'] : '';

        if (isset($_SERVER['SCRIPT_NAME'][0])) {
                if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
                        $uri = (string)substr($uri, strlen($_SERVER['SCRIPT_NAME']));
                    } elseif (strpos($uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
                        $uri = (string)substr($uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
                    }
            }

        // This section ensures that even on servers that require the URI to be in the query string (Nginx) a correct
        // URI is found, and also fixes the QUERY_STRING server var and $_GET array.
        if (trim($uri, '/') === '' && strncmp($query, '/', 1) === 0) {
                $query = explode('?', $query, 2);
                $uri = $query[0];
                $_SERVER['QUERY_STRING'] = isset($query[1]) ? $query[1] : '';
            } else {
                $_SERVER['QUERY_STRING'] = $query;
            }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        if ($uri === '/' or $uri === '') {
                return '/';
            }

        // Do some final cleaning of the URI and return it
        return $this->_remove_relative_directory($uri);
    }

    // --------------------------------------------------------------------

    /**
	 * Parse QUERY_STRING
	 *
	 * Will parse QUERY_STRING and automatically detect the URI from it.
	 *
	 * @return	string
	 */
    protected function _parse_query_string()
    {
        $uri = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : @getenv('QUERY_STRING');

        if (trim($uri, '/') === '') {
                return '';
            } elseif (strncmp($uri, '/', 1) === 0) {
                $uri = explode('?', $uri, 2);
                $_SERVER['QUERY_STRING'] = isset($uri[1]) ? $uri[1] : '';
                $uri = $uri[0];
            }

        parse_str($_SERVER['QUERY_STRING'], $_GET);

        return $this->_remove_relative_directory($uri);
    }

    // --------------------------------------------------------------------

    /**
	 * Parse CLI arguments
	 *
	 * Take each command line argument and assume it is a URI segment.
	 *
	 * @return	string
	 */
    protected function _parse_argv()
    {
        $args = array_slice($_SERVER['argv'], 1);
        return $args ? implode('/', $args) : '';
    }

    // --------------------------------------------------------------------

    /**
	 * Remove relative directory (../) and multi slashes (///)
	 *
	 * Do some final cleaning of the URI and return it, currently only used in self::_parse_request_uri()
	 *
	 * @param	string	$uri
	 * @return	string
	 */
    protected function _remove_relative_directory($uri)
    {
        $uris = array();
        $tok = strtok($uri, '/');
        while ($tok !== false) {
                if ((!empty($tok) or $tok === '0') && $tok !== '..') {
                        $uris[] = $tok;
                    }
                $tok = strtok('/');
            }

        return implode('/', $uris);
    }

    // --------------------------------------------------------------------

    /**
	 * Filter URI
	 *
	 * Filters segments for malicious characters.
	 *
	 * @param	string	$str
	 * @return	void
	 */
    public function filter_uri(&$str)
    {
        if (!empty($str) && !empty($this->_permitted_uri_chars) && !preg_match('/^[' . $this->_permitted_uri_chars . ']+$/i' . (UTF8_ENABLED ? 'u' : ''), $str)) {
                show_error('The URI you submitted has disallowed characters.', 400);
            }
    }

    // --------------------------------------------------------------------

    /**
	 * Fetch URI Segment
	 *
	 * @see		URIManager::$segments
	 * @param	int		$n		Index
	 * @param	mixed		$no_result	What to return if the segment index is not found
	 * @return	mixed
	 */
    public function segment($n, $no_result = null)
    {
        return isset($this->segments[$n]) ? $this->segments[$n] : $no_result;
    }

    // --------------------------------------------------------------------

    /**
	 * Fetch URI "routed" Segment
	 *
	 * Returns the re-routed URI segment (assuming routing rules are used)
	 * based on the index provided. If there is no routing, will return
	 * the same result as URIManager::segment().
	 *
	 * @see		URIManager::$rsegments
	 * @see		URIManager::segment()
	 * @param	int		$n		Index
	 * @param	mixed		$no_result	What to return if the segment index is not found
	 * @return	mixed
	 */
    public function rsegment($n, $no_result = null)
    {
        return isset($this->rsegments[$n]) ? $this->rsegments[$n] : $no_result;
    }

    // --------------------------------------------------------------------

    /**
	 * URI to assoc
	 *
	 * Generates an associative array of URI data starting at the supplied
	 * segment index. For example, if this is your URI:
	 *
	 *	example.com/user/search/name/joe/location/UK/gender/male
	 *
	 * You can use this method to generate an array with this prototype:
	 *
	 *	array (
	 *		name => joe
	 *		location => UK
	 *		gender => male
	 *	 )
	 *
	 * @param	int	$n		Index (default: 3)
	 * @param	array	$default	Default values
	 * @return	array
	 */
    public function uri_to_assoc($n = 3, $default = array())
    {
        return $this->_uri_to_assoc($n, $default, 'segment');
    }

    // --------------------------------------------------------------------

    /**
	 * Routed URI to assoc
	 *
	 * Identical to URIManager::uri_to_assoc(), only it uses the re-routed
	 * segment array.
	 *
	 * @see		URIManager::uri_to_assoc()
	 * @param 	int	$n		Index (default: 3)
	 * @param 	array	$default	Default values
	 * @return 	array
	 */
    public function ruri_to_assoc($n = 3, $default = array())
    {
        return $this->_uri_to_assoc($n, $default, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
	 * Internal URI-to-assoc
	 *
	 * Generates a key/value pair from the URI string or re-routed URI string.
	 *
	 * @used-by	URIManager::uri_to_assoc()
	 * @used-by	URIManager::ruri_to_assoc()
	 * @param	int	$n		Index (default: 3)
	 * @param	array	$default	Default values
	 * @param	string	$which		Array name ('segment' or 'rsegment')
	 * @return	array
	 */
    protected function _uri_to_assoc($n = 3, $default = array(), $which = 'segment')
    {
        if (!is_numeric($n)) {
                return $default;
            }

        if (isset($this->keyval[$which], $this->keyval[$which][$n])) {
                return $this->keyval[$which][$n];
            }

        $total_segments = "total_{$which}s";
        $segment_array = "{$which}_array";

        if ($this->$total_segments() < $n) {
                return (count($default) === 0)
                    ? array()
                    : array_fill_keys($default, null);
            }

        $segments = array_slice($this->$segment_array(), ($n - 1));
        $i = 0;
        $lastval = '';
        $retval = array();
        foreach ($segments as $seg) {
                if ($i % 2) {
                        $retval[$lastval] = $seg;
                    } else {
                        $retval[$seg] = null;
                        $lastval = $seg;
                    }

                $i++;
            }

        if (count($default) > 0) {
                foreach ($default as $val) {
                        if (!array_key_exists($val, $retval)) {
                                $retval[$val] = null;
                            }
                    }
            }

        // Cache the array for reuse
        isset($this->keyval[$which]) or $this->keyval[$which] = array();
        $this->keyval[$which][$n] = $retval;
        return $retval;
    }

    // --------------------------------------------------------------------

    /**
	 * Assoc to URI
	 *
	 * Generates a URI string from an associative array.
	 *
	 * @param	array	$array	Input array of key/value pairs
	 * @return	string	URI string
	 */
    public function assoc_to_uri($array)
    {
        $temp = array();
        foreach ((array)$array as $key => $val) {
                $temp[] = $key;
                $temp[] = $val;
            }

        return implode('/', $temp);
    }

    // --------------------------------------------------------------------

    /**
	 * Slash segment
	 *
	 * Fetches an URI segment with a slash.
	 *
	 * @param	int	$n	Index
	 * @param	string	$where	Where to add the slash ('trailing' or 'leading')
	 * @return	string
	 */
    public function slash_segment($n, $where = 'trailing')
    {
        return $this->_slash_segment($n, $where, 'segment');
    }

    // --------------------------------------------------------------------

    /**
	 * Slash routed segment
	 *
	 * Fetches an URI routed segment with a slash.
	 *
	 * @param	int	$n	Index
	 * @param	string	$where	Where to add the slash ('trailing' or 'leading')
	 * @return	string
	 */
    public function slash_rsegment($n, $where = 'trailing')
    {
        return $this->_slash_segment($n, $where, 'rsegment');
    }

    // --------------------------------------------------------------------

    /**
	 * Internal Slash segment
	 *
	 * Fetches an URI Segment and adds a slash to it.
	 *
	 * @used-by	URIManager::slash_segment()
	 * @used-by	URIManager::slash_rsegment()
	 *
	 * @param	int	$n	Index
	 * @param	string	$where	Where to add the slash ('trailing' or 'leading')
	 * @param	string	$which	Array name ('segment' or 'rsegment')
	 * @return	string
	 */
    protected function _slash_segment($n, $where = 'trailing', $which = 'segment')
    {
        $leading = $trailing = '/';

        if ($where === 'trailing') {
                $leading    = '';
            } elseif ($where === 'leading') {
                $trailing    = '';
            }

        return $leading . $this->$which($n) . $trailing;
    }

    // --------------------------------------------------------------------

    /**
	 * Segment Array
	 *
	 * @return	array	URIManager::$segments
	 */
    public function segment_array()
    {
        return $this->segments;
    }

    // --------------------------------------------------------------------

    /**
	 * Routed Segment Array
	 *
	 * @return	array	URIManager::$rsegments
	 */
    public function rsegment_array()
    {
        return $this->rsegments;
    }

    // --------------------------------------------------------------------

    /**
	 * Total number of segments
	 *
	 * @return	int
	 */
    public function total_segments()
    {
        return count($this->segments);
    }

    // --------------------------------------------------------------------

    /**
	 * Total number of routed segments
	 *
	 * @return	int
	 */
    public function total_rsegments()
    {
        return count($this->rsegments);
    }

    // --------------------------------------------------------------------

    /**
	 * Fetch URI string
	 *
	 * @return	string	URIManager::$uri_string
	 */
    public function uri_string()
    {
        return $this->uri_string;
    }

    // --------------------------------------------------------------------

    /**
	 * Fetch Re-routed URI string
     * 
     * This Function has being depreciated and will soon be removed.
	 *
	 * @return	string
	 */
    public function ruri_string()
    {
        //return ltrim(load_class('Router', 'core')->directory, '/') . implode('/', $this->rsegments);
    }
}
