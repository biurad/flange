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

namespace Rade\Middleware;

use Flight\Routing\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rade\Application;
use Rade\Event\ControllerEvent;
use Rade\Events;

class RouteHandlerMiddleware implements MiddlewareInterface
{
    private Application $application;

    public function __construct(Application $app)
    {
        $this->application = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute(Route::class);

        if (!$route instanceof Route) {
            return $handler->handle($request);
        }

        $arguments = $route->get('arguments');
        $arguments['_route'] = $route->get('name');

        $event = new ControllerEvent(
            $this->application, 
            $route->get('controller'),
            $arguments,
            $request->withoutAttribute(Route::class)
        );
        $this->application['dispatcher']->dispatch($event, Events::CONTROLLER);

        // set the new controller, if modified during event listening
        $route->run($event->getController());

        // Get the new arguments passed to controller.
        $arguments = $event->getArguments();
        unset($arguments['_route']);
        
        if ([] !== $arguments) {
            $route->arguments($arguments);
        }

        return $handler->handle($event->getRequest()->withAttribute(Route::class, $route));
    }
}