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

namespace Rade\Handler;

use Flight\Routing\Handlers\RouteHandler as BaseRouteHandler;
use Flight\Routing\Route;
use Psr\Http\Message\ServerRequestInterface;
use Rade\Application;
use Rade\DI\Container;
use Rade\Event\ControllerEvent;

/**
 * Default route's handler for rade framework.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteHandler extends BaseRouteHandler
{
    public function __construct(Container $container)
    {
        if ($container instanceof Application) {
            $handlerResolver = function ($handler, array $parameters) use ($container) {
                $event = new ControllerEvent($container, $handler, $parameters, $parameters[ServerRequestInterface::class]);
                $container->getDispatcher()->dispatch($event);

                return $container->getResolver()->resolve($event->getController(), $event->getArguments());
            };
        }

        parent::__construct($container->get('psr17.factory'), $handlerResolver ?? [$container->getResolver(), 'resolve']);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveArguments(ServerRequestInterface $request, Route $route): array
    {
        $parameters = $route->getArguments();
        $parameters[\get_class($request)] = $request;

        foreach (\class_implements($request) as $psr7Interface) {
            if (\Stringable::class === $psr7Interface) {
                continue;
            }

            $parameters[$psr7Interface] = $request;
        }

        return $parameters;
    }
}
