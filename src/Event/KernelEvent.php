<?php declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Event;

use Flange\Application;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events thrown in the Application's class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class KernelEvent extends Event
{
    public function __construct(private Application $app, private Request $request)
    {
    }

    /**
     * Returns the kernel in which this event was thrown.
     */
    public function getApplication(): Application
    {
        return $this->kernel;
    }

    /**
     * Returns the request the kernel is currently processing.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
