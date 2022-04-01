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
use Nette\Utils\Callback;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
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

            if (\is_string($handler)) {
                if ($container->has($handler)) {
                    $handler = $container->get($handler);
                } elseif ($container->typed($handler)) {
                    $handler = $container->autowired($handler);
                } elseif (\class_exists($handler)) {
                    $handler = $container->getResolver()->resolveClass($handler);
                }
            } elseif (\is_array($handler) && \count($handler) === 2) {
                $handler[0] = \is_string($handler[0]) ? $container->get($handler[0]) : $handler[0];
            }

            $ref = !$handler instanceof RequestHandlerInterface ? Callback::toReflection($handler) : new \ReflectionMethod($handler, 'handle');

            if ($ref->getNumberOfParameters() > 0) {
                foreach (\class_implements($request) + (\class_parents($request) ?: []) as $psr7Interface) {
                    if (\Stringable::class === $psr7Interface) {
                        continue;
                    }
                    $parameters[$psr7Interface] = $request;
                }
                $parameters[\get_class($request)] = $request;
                $args = $container->getResolver()->autowireArguments($ref, $parameters);
            }

            if ($container->has('events.dispatcher')) {
                $container->get('events.dispatcher')->dispatch($event = new RequestEvent($container, $request));

                if ($event->hasResponse()) {
                    return $event->getResponse();
                }

                $request = $event->getRequest();
                $container->get('events.dispatcher')->dispatch($event = new ControllerEvent($container, $handler, $ref, $args ?? [], $request));
                [$handler, $ref, $args] = [$event->getController(), $event->getReflection(), $event->getArguments()];
            }

            if ($request instanceof Request && $container->has('request_stack')) {
                $container->get('request_stack')->push($request->getRequest());
            }

            if ($ref instanceof \ReflectionMethod) {
                return $ref->isStatic() ? $ref->invokeArgs(null, $args ?? []) : $ref->invokeArgs(!\is_object($handler) ? $handler[0] : $handler, $args ?? []);
            }

            return $ref->invokeArgs($args ?? []);
        };

        parent::__construct($container->get('psr17.factory'), $handlerResolver);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveArguments(ServerRequestInterface $request, Route $route): array
    {
        $route->getArguments();
        $parameters[ServerRequestInterface::class] = $request;

        return $parameters;
    }
}
