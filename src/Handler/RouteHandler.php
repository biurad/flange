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

namespace Flange\Handler;

use Biurad\Http\Request;
use Flange\Event\ControllerEvent;
use Flight\Routing\Handlers\RouteHandler as BaseRouteHandler;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Default route's handler for rade framework.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteHandler extends BaseRouteHandler
{
    public function __construct(\Flange\Application $container)
    {
        if ($container->has('events.dispatcher')) {
            $resolver = static function (mixed $handler, array $parameters) use ($container) {
                $event = new ControllerEvent($container, $parameters[ServerRequestInterface::class], $handler, $parameters);
                $container->getDispatcher()->dispatch($event);
                $request = $event->getRequest();

                if ($request instanceof Request) {
                    $container->get('request_stack')->push($request->getRequest());
                }

                return $container->call($event->getController(), $event->getArguments());
            };
        }

        parent::__construct($container->get('psr17.factory'), $resolver ?? [$container->getResolver(), 'resolve']);
    }

    /**
     * {@inheritdoc}
     */
    protected function resolveArguments(ServerRequestInterface $request, $parameters): array
    {
        if (!\is_array($parameters)) {
            $parameters = $parameters->getArguments();
        }
        $requests = \array_merge([$request::class], \class_implements($request) ?: [], \class_parents($request) ?: []);

        foreach ($requests as $psr7Interface) {
            if (\Stringable::class === $psr7Interface) {
                continue;
            }

            $parameters[$psr7Interface] = $request;
        }

        return $parameters;
    }
}
