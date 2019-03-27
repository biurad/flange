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
 * Class TimeTrackr
 * Manage everything related to Date and Time
 *
 * @package Core
 */
class TimeTrackr extends \DateTime
{
    ////////////////
    // Properties //
    ////////////////

    /**
     * Constant default format.
     *
     * @constant
     */
    const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    const AGO = 'ago';

    const FROM_NOW = 'from now';

    /**
     * Holds the configurations for TimeTrackr (e.g: timezone).
     *
     * @static
     *
     * @var
     */
    private static $config;

    /**
     * format of the date set by on method.
     *
     * @var
     *
     * @see on()
     */
    private static $format;

    /**
     * the date & time to compare with.
     *
     * @var
     */
    private $dateToCompareWith;

    /**
     * interval between two dates (now/on and compareWith).
     *
     * @var
     *
     * @see compareWith()
     */
    private $interval;

    /**
     * Function loadConfig - get the configuration file from config/datetime.php.
     *
     * @static
     *
     * @since 0.5.0
     */
    private static function loadConfig()
    {
        if (is_null(self::$config)) {
            self::$config = Config::get('timetrackr');
        }
    }

    /**
     * Method applyConfig - calls the function prepare, and set the timezone from config file.
     *
     * @static
     *
     * @since 0.5.0
     */
    public static function init()
    {
        self::loadConfig();
        self::setDefaultTimeZoneFromConfigFile();
    }

    /**
     * Set default timezone from configuration file, for PHP.
     *
     * @return bool
     *
     * @since 0.5.0
     */
    private static function setDefaultTimeZoneFromConfigFile()
    {
        return date_default_timezone_set(self::$config['timezone']);
    }

    /**
     * Set the date time to now.
     *
     * @param null $timezone
     *
     * @return static
     *
     * @since 0.5.0
     */
    public static function now($timezone = null)
    {
        return new static(null, $timezone);
    }

    /**
     * Set the date on.
     *
     * @param $format
     * @param $time
     * @param null $timezone
     *
     * @return static
     *
     * @since 0.5.0
     */
    public static function on($format, $time, $timezone = null)
    {
        self::$format = $format;

        return new static($time, $timezone);
    }

    //////////////////
    // constructors //
    //////////////////

    /**
     * TimeTrackr constructor.
     *
     * @param null          $time
     * @param \DateTimeZone $timezone
     *
     * @throws \Exception
     *
     * @since 0.5.0
     */
    public function __construct($time, $timezone)
    {
        // converts the time according to the format.
        if (isset(self::$format)) {
            $time = $this->createFromFormat(self::$format, $time);
            // if the format is not understandable by DateTime, it will simply return false
            // we will set the date to now (should be noticeable) instead of throwing obstructive errors
            if ($time == false) {
                $p = null;
            } else {
                $p = $time->format(self::DEFAULT_FORMAT);
            }
            $time = $p;
        } else {
            // if format isn't defined
            $time = null;
        }

        // When we call TimeTrackr::now() is there $timezone passed? If
        // there isn't we will get it from the config file
        if ($timezone == null) {
            if (is_null(self::$config)) {
                self::prepare();
            }
            $timezone = new \DateTimeZone(self::$config['timezone']);
        } else {
            $timezone = new \DateTimeZone($timezone);
        }

        // Properly initialize DateTime
        parent::__construct($time, $timezone);

        // Set the timezone
        $this->setTimezone($timezone);
    }

    /**
     * Add one second.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addSecond()
    {
        $this->modify('+1 second');

        return $this;
    }

    /**
     * Add seconds according to $seconds.
     *
     * @param $seconds
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addSeconds($seconds)
    {
        $this->modify('+'.((float) $seconds).' second');

        return $this;
    }

    /**
     * Add one minute.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addMinute()
    {
        $this->modify('+1 minute');

        return $this;
    }

    /**
     * Add minutes according to $minutes.
     *
     * @param $minutes
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addMinutes($minutes)
    {
        $this->modify('+'.((float) $minutes).' minute');

        return $this;
    }

    /**
     * Add one hour.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addHour()
    {
        $this->modify('+1 hour');

        return $this;
    }

    /**
     * Add hours according to $hours.
     *
     * @param $hours
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addHours($hours)
    {
        $this->modify('+'.((float) $hours).' hour');

        return $this;
    }

    /**
     * Add one day.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addDay()
    {
        $this->modify('+1 day');

        return $this;
    }

    /**
     * Add days according to $days.
     *
     * @param $days
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addDays($days)
    {
        $this->modify('+'.((float) $days).' day');

        return $this;
    }

    /**
     * Add one week.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addWeek()
    {
        $this->modify('+1 week');

        return $this;
    }

    /**
     * Add weeks according to $weeks.
     *
     * @param $weeks
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addWeeks($weeks)
    {
        $this->modify('+'.((float) $weeks).' week');

        return $this;
    }

    /**
     * Add one year.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addYear()
    {
        $this->modify('+1 year');

        return $this;
    }

    /**
     * Add years according to $years.
     *
     * @param $years
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addYears($years)
    {
        $this->modify('+'.((float) $years).' year');

        return $this;
    }

    /**
     * Add one decade.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addDecade()
    {
        $this->modify('+10 year');

        return $this;
    }

    /**
     * Add decades according to $decades.
     *
     * @param $decades
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addDecades($decades)
    {
        $decades = $decades * 10;
        $this->modify('+'.((float) $decades).' year');

        return $this;
    }

    /**
     * Add one century.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addCentury()
    {
        $this->modify('+100 year');

        return $this;
    }

    /**
     * Add centuries according to $centuries.
     *
     * @param $centuries
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addCenturies($centuries)
    {
        $centuries = $centuries * 100;
        $this->modify('+'.((float) $centuries).' year');

        return $this;
    }

    /**
     * Add one millennium.
     *
     * @return TimeTrackr $this
     *
     * @since 0.5.0
     */
    public function addMillennium()
    {
        $this->modify('+1000 year');

        return $this;
    }

    /**
     * Break down each of the symbols used in formatting, and return each individual formats.
     *
     * @return array
     *
     * @since 0.5.0
     */
    public function toArray()
    {
        $t = [];
        $symbols = ['r', 'D', 'd', 'S', 'm', 'M', 'F', 'y', 'Y', 'h', 'H', 'i', 's', 'A', 'a'];
        foreach ($symbols as $symbol) {
            $t[$symbol] = $this->format($symbol);
        }

        return $t;
    }

    /**
     * Returns the string of datetime in default format.
     *
     * @return string
     *
     * @since 0.5.0
     */
    public function __toString()
    {
        return (string) $this->format(self::DEFAULT_FORMAT);
    }

    ///////////////
    // Intervals //
    ///////////////

    /**
     * Gets the date we want to compare with and create date intervals between these two.
     *
     * @param $format
     * @param $time
     * @param null $timezone
     *
     * @return $this
     *
     * @since 0.5.0
     */
    public function compareWith($format, $time, $timezone = null)
    {
        if ($timezone == null) {
            $timezone = self::$config['timezone'];
        }

        $this->dateToCompareWith = $this->createFromFormat($format, $time, new \DateTimeZone($timezone));

        $this->interval = date_diff($this, $this->dateToCompareWith);

        return $this;
    }

    /**
     * Pretty printing of two datetime intervals in human readable format
     * e.g: 1 hour ago, or 1 month, 12 days, 2 minutes ago.
     *
     * @param int    $justNowSeconds How many seconds between to consider it as "just now"
     * @param string $justNowText    Change the "just now" text to something else
     *
     * @return null|string
     *
     * @since 0.5.0
     */
    public function diffInHuman($justNowSeconds = 5, $justNowText = 'just now')
    {
        // in method chaining, compareWith must come first
        if (!isset($this->interval)) {
            return;
        }

        // all symbols used for the pretty printing
        $symbols = ['y', 'm', 'd', 'h', 'i', 's'];
        $i = [];

        // format each portion of interval
        foreach ($symbols as $s) {
            $i[$s] = $this->interval->format('%'.$s);
        }

        // string for storing human readable intervals
        $string = '';

        // used to count how many intervals has been on the string
        $count = 0;

        foreach ($i as $symbol => $interval) {
            // print only non-zero intervals
            if (intval($interval) !== 0) {
                // if the interval is more than one, set $singular = false
                if ($interval > 1) {
                    $humanSymbol = $this->translateSymbol($symbol, $singular = false);
                } else {
                    $humanSymbol = $this->translateSymbol($symbol, $singular = true);
                }
                // append to the string
                $string .= $interval.' '.$humanSymbol.', ';
                $count++;
            }
        }
        // remove trailing commas
        $string = rtrim($string, ', ');

        // end for 'ago' or 'from now'
        $end = '';

        if ($count !== 0) {
            // calculate timestamps of the two datetime
            $timestampDiff = ($this->getTimestamp() - $this->dateToCompareWith->getTimestamp());

            // later intervals have positive value, and newer intervals have negative value
            if ($timestampDiff >= 0) {
                $end = ' '.self::AGO;
            } elseif ($timestampDiff < 0) {
                $end = ' '.self::FROM_NOW;
            }
        } else {
            $end .= $justNowText;
        }

        // if newer intervals, return the string straightaway
        if ($end == ' '.self::FROM_NOW) {
            return $string.$end;
        }

        // any intervals (seconds) fall in the range will be fuzzy printed
        // "just now".
        $array = explode(' ', $string);

        // make sure seconds/second are the only one existing in the string
        // not any other or not none.
        if (isset($array[count($array) - 3])) {
            return $string.$end;
        } else {
            // the second last word in the string holds number of seconds
            if (isset($array[count($array) - 2])) {
                $a = $array[count($array) - 2];
            } else {
                $a = 0;
            }

            // the last one holds the word second/seconds
            $b = $array[count($array) - 1];

            if ($b == 'seconds' || $b == 'second') {
                if ($justNowSeconds >= $a) {
                    return $justNowText;
                }
            }

            return $string.$end;
        }
    }

    /**
     * Get the date_diff / Interval.
     *
     * @return mixed Interval
     *
     * @since 0.5.0
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * Translate symbol into human readable language
     * e.g: y into years/year.
     *
     * @static
     *
     * @param $symbol        the symbol
     * @param bool $singular singular/plural, e.g: years(false)/year(true)
     *
     * @return string
     *
     * @since 0.5.0
     */
    public static function translateSymbol($symbol, $singular = true)
    {
        switch ($symbol) {
            case 'y':
                if ($singular) {
                    return 'year';
                } else {
                    return 'years';
                }
                break;
            case 'm':
                if ($singular) {
                    return 'month';
                } else {
                    return 'months';
                }
                break;
            case 'd':
                if ($singular) {
                    return 'day';
                } else {
                    return 'days';
                }
                break;
            case 'h':
                if ($singular) {
                    return 'hour';
                } else {
                    return 'hours';
                }
                break;
            case 'i':
                if ($singular) {
                    return 'minute';
                } else {
                    return 'minutes';
                }
                break;
            case 's':
                if ($singular) {
                    return 'second';
                } else {
                    return 'seconds';
                }
                break;
            default:
                return '';
                break;
        }
    }
}
