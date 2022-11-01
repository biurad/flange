<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange;

/**
 * Contains all events thrown in the Application.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class Events
{
    /**
     * The REQUEST event occurs at the very beginning of request
     * dispatching.
     *
     * This event allows you to create a response for a request before any
     * other code in the framework is executed.
     */
    public const REQUEST = Event\RequestEvent::class;

    /**
     * The EXCEPTION event occurs when an uncaught exception appears.
     *
     * This event allows you to create a response for a thrown exception or
     * to modify the thrown exception.
     */
    public const EXCEPTION = Event\ExceptionEvent::class;

    /**
     * The CONTROLLER event occurs once a controller was found for
     * handling a request.
     *
     * This event allows you to change the controller and arguments that will be passed to
     * the controller while handling the request.
     */
    public const CONTROLLER = Event\ControllerEvent::class;

    /**
     * The RESPONSE event occurs once a response was created for
     * replying to a request.
     *
     * This event allows you to modify or replace the response that will be
     * replied.
     */
    public const RESPONSE = Event\ResponseEvent::class;

    /**
     * The TERMINATE event occurs once a response was sent.
     *
     * This event allows you to run expensive post-response jobs.
     */
    public const TERMINATE = Event\TerminateEvent::class;
}
