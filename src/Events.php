<?php

declare(strict_types=1);

/*
 * This file is part of DivineNii opensource projects.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 DivineNii (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rade;

/**
 * Contains all events thrown in the Applicatiob.
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
     *
     * @Event("Rade\Event\RequestEvent")
     */
    public const REQUEST = 'kernel.request';

    /**
     * The EXCEPTION event occurs when an uncaught exception appears.
     *
     * This event allows you to create a response for a thrown exception or
     * to modify the thrown exception.
     *
     * @Event("Rade\Event\ExceptionEvent")
     */
    public const EXCEPTION = 'kernel.exception';

    /**
     * The CONTROLLER event occurs once a controller was found for
     * handling a request.
     *
     * This event allows you to change the controller and arguments that will be passed to
     * the controller while handling the request.
     *
     * @Event("Rade\Event\ControllerEvent")
     */
    public const CONTROLLER = 'kernel.controller';

    /**
     * The RESPONSE event occurs once a response was created for
     * replying to a request.
     *
     * This event allows you to modify or replace the response that will be
     * replied.
     *
     * @Event("Rade\Event\ResponseEvent")
     */
    public const RESPONSE = 'kernel.response';

    /**
     * The TERMINATE event occurs once a response was sent.
     *
     * This event allows you to run expensive post-response jobs.
     *
     * @Event("Rade\Event\TerminateEvent")
     */
    public const TERMINATE = 'kernel.terminate';
}
