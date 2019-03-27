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
 * Benchmark Class
 *
 * This class enables you to mark points and calculate the time difference
 * between them. Memory consumption can also be displayed.
 *
 */
class Benchmark
{

    /**
	 * List of all benchmark markers
	 *
	 * @var	array
	 */
    public $marker = array();

    /**
	 * Set a benchmark marker
	 *
	 * Multiple calls to this function can be made so that several
	 * execution points can be timed.
	 *
	 * @param	string	$name	Marker name
	 * @return	void
	 */
    public function mark($name)
    {
        $this->marker[$name] = microtime(true);
    }

    // --------------------------------------------------------------------

    /**
	 * Elapsed time
	 *
	 * Calculates the time difference between two marked points.
	 *
	 * If the first parameter is empty this function instead returns the
	 * {elapsed_time} pseudo-variable. This permits the full system
	 * execution time to be shown in a template. The output class will
	 * swap the real value for this variable.
	 *
	 * @param	string	$point1		A particular marked point
	 * @param	string	$point2		A particular marked point
	 * @param	int	$decimals	Number of decimal places
	 *
	 * @return	string	Calculated elapsed time on success,
	 *			an '{elapsed_string}' if $point1 is empty
	 *			or an empty string if $point1 is not found.
	 */
    public function elapsed_time($point1 = '', $point2 = '', $decimals = 4)
    {
        if ($point1 === '') {
                return '{elapsed_time}';
            }

        if (!isset($this->marker[$point1])) {
                return '';
            }

        if (!isset($this->marker[$point2])) {
                $this->marker[$point2] = microtime(true);
            }

        return number_format($this->marker[$point2] - $this->marker[$point1], $decimals);
    }

    // --------------------------------------------------------------------

    /**
	 * Memory Usage
	 *
	 * Simply returns the {memory_usage} marker.
	 *
	 * This permits it to be put it anywhere in a template
	 * without the memory being calculated until the end.
	 * The output class will swap the real value for this variable.
	 *
	 * @return	string	'{memory_usage}'
	 */
    public function memory_usage()
    {
        return '{memory_usage}';
    }
}
