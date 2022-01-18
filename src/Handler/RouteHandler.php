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

use Biurad\Http\Request;
use Flight\Routing\Handlers\RouteHandler as BaseRouteHandler;
use Flight\Routing\Route;
use Psr\Http\Message\ServerRequestInterface;
use Rade\DI\Container;
use Rade\Event\ControllerEvent;
use Rade\Event\RequestEvent;

/**
 * Default route's handler for rade framework.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteHandler extends BaseRouteHandler
{
    public function __construct(Container $container)
    {
        $handlerResolver = static function ($handler, array $parameters) use ($container) {
            $request = $parameters[ServerRequestInterface::class] ?? null;

            if ($container->has('events.dispatcher')) {
                $container->get('events.dispatcher')->dispatch($event = new RequestEvent($container, $request));

                if ($event->hasResponse()) {
                    return $event->getResponse();
                }

                $request = $event->getRequest();
                $container->get('events.dispatcher')->dispatch($event = new ControllerEvent($container, $handler, $parameters, $request));
                [$handler, $parameters] = [$event->getController(), $event->getArguments()];
            }

            if ($request instanceof Request && $container->has('request_stack')) {
                $container->get('request_stack')->push($request->getRequest());
            }

            return $container->getResolver()->resolve($handler, $parameters);
        };

        parent::__construct($container->get('psr17.factory'), $handlerResolver);
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
