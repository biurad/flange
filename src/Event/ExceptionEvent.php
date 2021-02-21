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

namespace Rade\Event;

use Psr\Http\Message\ServerRequestInterface as Request;
use Rade\Application;
use Throwable;

/**
 * Allows to create a response for a thrown exception.
 *
 * Call setResponse() to set the response that will be returned for the
 * current request. The propagation of this event is stopped as soon as a
 * response is set.
 *
 * You can also call setThrowable() to replace the thrown exception. This
 * exception will be thrown if no response is set during processing of this
 * event.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ExceptionEvent extends RequestEvent
{
    private Throwable $throwable;

    private bool $allowCustomResponseCode = false;

    public function __construct(Application $kernel, Request $request, Throwable $e)
    {
        parent::__construct($kernel, $request);

        $this->setThrowable($e);
    }

    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }

    /**
     * Replaces the thrown exception.
     *
     * This exception will be thrown if no response is set in the event.
     */
    public function setThrowable(Throwable $exception): void
    {
        $this->throwable = $exception;
    }

    /**
     * Mark the event as allowing a custom response code.
     */
    public function allowCustomResponseCode(): void
    {
        $this->allowCustomResponseCode = true;
    }

    /**
     * Returns true if the event allows a custom response code.
     */
    public function isAllowingCustomResponseCode(): bool
    {
        return $this->allowCustomResponseCode;
    }
}
