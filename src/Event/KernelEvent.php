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
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events thrown in the Application's class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class KernelEvent extends Event
{
    private Application $kernel;

    private Request $request;

    /**
     * @param Application $app
     * @param Request     $request
     */
    public function __construct(Application $app, Request $request)
    {
        $this->kernel  = $app;
        $this->request = $request;
    }

    /**
     * Returns the kernel in which this event was thrown.
     *
     * @return Application
     */
    public function getApplication(): Application
    {
        return $this->kernel;
    }

    /**
     * Returns the request the kernel is currently processing.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
