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
use Rade\Event\ControllerEvent;

/**
 * Default route's handler for rade framework.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteHandler extends BaseRouteHandler
{
    public function __construct(\Rade\Application $container)
    {
        $handlerResolver = static function ($handler, array $parameters) use ($container) {
            $event = new ControllerEvent($container, $parameters[ServerRequestInterface::class], $handler, $parameters);
            $container->getDispatcher()->dispatch($event);
            $request = $event->getRequest();

            if ($request instanceof Request) {
                $container->get('request_stack')->push($request->getRequest());
            }

            return $container->getResolver()->resolve($event->getController(), $event->getArguments());
        };

        parent::__construct($container->get('psr17.factory'), $handlerResolver);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveArguments(ServerRequestInterface $request, Route $route): array
    {
        $parameters = $route->getArguments();
        $requests = \array_merge([\get_class($request)], \class_implements($request) ?: [], (\class_parents($request) ?: []));

        foreach ($requests as $psr7Interface) {
            if (\Stringable::class === $psr7Interface) {
                continue;
            }

            $parameters[$psr7Interface] = $request;
        }

        return $parameters;
    }
}
