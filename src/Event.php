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

class EventManager
{
     /**
	 * All of the registered events.
	 *
	 * @var array
	 */
	public static $events = array();

	/**
	 * The queued events waiting for flushing.
	 *
	 * @var array
	 */
	public static $queued = array();

	/**
	 * All of the registered queue flusher callbacks.
	 *
	 * @var array
	 */
	public static $flushers = array();

	/**
	 * Determine if an event has any registered listeners.
	 *
	 * @param  string  $event
	 * @return bool
	 */
	public static function listeners($event)
	{
		return isset(static::$events[$event]);
	}

	/**
	 * Register a callback for a given event.
	 *
	 * <code>
	 *		// Register a callback for the "start" event
	 *		Event::listen('start', function() {return 'Started!';});
	 *
	 *		// Register an object instance callback for the given event
	 *		Event::listen('event', array($object, 'method'));
	 * </code>
	 *
	 * @param  string  $event
	 * @param  mixed   $callback
	 * @return void
	 */
	public static function listen($event, $callback)
	{
		static::$events[$event][] = $callback;
	}

	/**
	 * Override all callbacks for a given event with a new callback.
	 *
	 * @param  string  $event
	 * @param  mixed   $callback
	 * @return void
	 */
	public static function override($event, $callback)
	{
		static::clear($event);

		static::listen($event, $callback);
	}

	/**
	 * Add an item to an event queue for processing.
	 *
	 * @param  string  $queue
	 * @param  string  $key
	 * @param  mixed   $data
	 * @return void
	 */
	public static function queue($queue, $key, $data = array())
	{
		static::$queued[$queue][$key] = $data;
	}

	/**
	 * Register a queue flusher callback.
	 *
	 * @param  string  $queue
	 * @param  mixed   $callback
	 * @return void
	 */
	public static function flusher($queue, $callback)
	{
		static::$flushers[$queue][] = $callback;
	}

	/**
	 * Clear all event listeners for a given event.
	 *
	 * @param  string  $event
	 * @return void
	 */
	public static function clear($event)
	{
		unset(static::$events[$event]);
	}

	/**
	 * Fire an event and return the first response.
	 *
	 * <code>
	 *		// Fire the "start" event
	 *		$response = Event::first('start');
	 *
	 *		// Fire the "start" event passing an array of parameters
	 *		$response = Event::first('start', array('Laravel', 'Framework'));
	 * </code>
	 *
	 * @param  string  $event
	 * @param  array   $parameters
	 * @return mixed
	 */
	public static function first($event, $parameters = array())
	{
		return head(static::fire($event, $parameters));
	}

	/**
	 * Fire an event and return the first response.
	 *
	 * Execution will be halted after the first valid response is found.
	 *
	 * @param  string  $event
	 * @param  array   $parameters
	 * @return mixed
	 */
	public static function until($event, $parameters = array())
	{
		return static::fire($event, $parameters, true);
	}

	/**
	 * Flush an event queue, firing the flusher for each payload.
	 *
	 * @param  string  $queue
	 * @return void
	 */
	public static function flush($queue)
	{
		foreach (static::$flushers[$queue] as $flusher)
		{
			// We will simply spin through each payload registered for the event and
			// fire the flusher, passing each payloads as we go. This allows all
			// the events on the queue to be processed by the flusher easily.
			if ( ! isset(static::$queued[$queue])) continue;

			foreach (static::$queued[$queue] as $key => $payload)
			{
				array_unshift($payload, $key);

				call_user_func_array($flusher, $payload);
			}
		}
	}

	/**
	 * Fire an event so that all listeners are called.
	 *
	 * <code>
	 *		// Fire the "start" event
	 *		$responses = Event::fire('start');
	 *
	 *		// Fire the "start" event passing an array of parameters
	 *		$responses = Event::fire('start', array('Laravel', 'Framework'));
	 *
	 *		// Fire multiple events with the same parameters
	 *		$responses = Event::fire(array('start', 'loading'), $parameters);
	 * </code>
	 *
	 * @param  string|array  $events
	 * @param  array         $parameters
	 * @param  bool          $halt
	 * @return array
	 */
	public static function fire($events, $parameters = array(), $halt = false)
	{
		$responses = array();

		$parameters = (array) $parameters;

		// If the event has listeners, we will simply iterate through them and call
		// each listener, passing in the parameters. We will add the responses to
		// an array of event responses and return the array.
		foreach ((array) $events as $event)
		{
			if (static::listeners($event))
			{
				foreach (static::$events[$event] as $callback)
				{
					$response = call_user_func_array($callback, $parameters);

					// If the event is set to halt, we will return the first response
					// that is not null. This allows the developer to easily stack
					// events but still get the first valid response.
					if ($halt and ! is_null($response))
					{
						return $response;
					}

					// After the handler has been called, we'll add the response to
					// an array of responses and return the array to the caller so
					// all of the responses can be easily examined.
					$responses[] = $response;
				}
			}
		}

		return $halt ? null : $responses;
	}
}