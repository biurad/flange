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
    private \ReflectionFunctionAbstract $ref;
    private array $arguments;

    /** @var mixed */
    private $controller;

    /**
     * @param mixed $controller
     */
    public function __construct(Application $app, $controller, \ReflectionFunctionAbstract $ref, array $arguments, Request $request)
    {
        parent::__construct($app, $request);

        $this->controller = $controller;
        $this->arguments = $arguments;
        $this->ref = $ref;
    }

    public function getReflection(): \ReflectionFunctionAbstract
    {
        return $this->ref;
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
