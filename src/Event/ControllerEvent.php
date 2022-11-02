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

/**
 * Allows filtering of controller arguments.
 *
 * You can call getController() to retrieve the controller and getArguments
 * to retrieve the current arguments. With setArguments() you can replace
 * arguments that are used to call the controller.
 *
 * Arguments set in the event must be compatible with the signature of the controller.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
final class ControllerEvent extends KernelEvent
{
    /** @var array<int|string,mixed> */
    private array $arguments;

    /** @var mixed */
    private $controller;

    /**
     * @param mixed $controller
     */
    public function __construct(Application $app, Request $request, $controller, array $arguments)
    {
        parent::__construct($app, $request);

        $this->controller = $controller;
        $this->arguments = $arguments;
    }

    /**
     * @return mixed
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * @param mixed $controller
     */
    public function setController($controller): void
    {
        $this->controller = $controller;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * @param mixed $value
     */
    public function setArgument(string $name, $value): void
    {
        $this->arguments[$name] = $value;
    }
}
