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

namespace Rade\DI\Extensions;

use Biurad\Http\Middlewares\ErrorHandlerMiddleware;
use Biurad\Http\Middlewares\PrepareResponseMiddleware;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Interfaces\UrlGeneratorInterface;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\Router;
use Flight\Routing\Route;
use Laminas\Stratigility\Middleware\OriginalMessages;
use Psr\Http\Server\RequestHandlerInterface;
use Rade\DI\AbstractContainer;
use Rade\DI\Builder\PhpLiteral;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\Handler\RouteHandler;
use Rade\RouterInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Flight Routing Extension. (Recommend being used with AppBuilder).
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RoutingExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, DependenciesInterface, ExtensionInterface
{
    /** @var array<int,object> */
    private array $middlewares = [];

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [HttpGalaxyExtension::class];
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'routing';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('pipe')
            ->fixXmlConfig('route')
            ->children()
                ->booleanNode('redirect_permanent')->defaultFalse()->end()
                ->booleanNode('keep_request_method')->defaultFalse()->end()
                ->booleanNode('response_error')->defaultValue(null)->end()
                ->booleanNode('resolve_route_paths')->defaultTrue()->end()
                ->scalarNode('routes_handler')->end()
                ->scalarNode('cache')->end()
                ->arrayNode('pipes')
                    ->normalizeKeys(false)
                    ->defaultValue([])
                    ->beforeNormalization()
                        ->ifString()->then(fn ($v) => [$v])
                        ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                        ->thenInvalid('Expected pipes values to be an associate array of string keys mapping to mixed values.')
                        ->always(function ($value) {
                            foreach ($value as $key => $middlewares) {
                                if (\is_array($middlewares)) {
                                    if (!\array_is_list($middlewares)) {
                                        $value[\key($middlewares)] = $mValue = \current($middlewares);
                                        unset($value[$key]);

                                        if (!\array_is_list($mValue)) {
                                            throw new ServiceCreationException(\sprintf('Expected pipes values to be strings, "%s" given.', \gettype($mValue)));
                                        }
                                    }

                                    continue;
                                }

                                if (!\is_int($key)) {
                                    throw new ServiceCreationException(\sprintf('Expected pipes key to be an integer, "%s" given.', \gettype($key)));
                                }

                                if (!\is_string($middlewares)) {
                                    throw new ServiceCreationException(\sprintf('Expected pipes offset key %s with a string value, "%s" given.', $key, \gettype($middlewares)));
                                }
                            }

                            return $value;
                        })
                    ->end()
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('routes')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('name')->defaultValue(null)->end()
                            ->scalarNode('path')->isRequired()->end()
                            ->scalarNode('to')->defaultValue(null)->end()
                            ->scalarNode('namespace')->defaultValue(null)->end()
                            ->booleanNode('debug')->defaultValue(null)->end()
                            ->arrayNode('methods')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(fn (string $v): array => [$v])
                                ->end()
                                ->defaultValue(Route::DEFAULT_METHODS)
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('schemes')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(fn (string $v): array => [$v])
                                ->end()
                                ->prototype('scalar')->defaultValue([])->end()
                            ->end()
                            ->arrayNode('hosts')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(fn (string $v): array => [$v])
                                ->end()
                                ->prototype('scalar')->defaultValue([])->end()
                            ->end()
                            ->arrayNode('piped')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(fn (string $v): array => [$v])
                                ->end()
                                ->prototype('scalar')->defaultValue([])->end()
                            ->end()
                            ->arrayNode('asserts')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected patterns values to be an associate array of string keys mapping to mixed values.')
                                ->end()
                                ->prototype('variable')->end()
                            ->end()
                            ->arrayNode('defaults')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected defaults values to be an associate array of string keys mapping to mixed values.')
                                ->end()
                                ->prototype('variable')->end()
                            ->end()
                            ->arrayNode('arguments')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected arguments values to be an associate array of string keys mapping to mixed values.')
                                ->end()
                                ->prototype('variable')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs = []): void
    {
        $routeHandler = $configs['routes_handler'] ?? RouteHandler::class;
        $pipesMiddleware = [];

        if ($container->has($routeHandler)) {
            $container->alias(RequestHandlerInterface::class, $routeHandler);
        } else {
            $container->set(RequestHandlerInterface::class, new Definition($routeHandler));
        }

        if ($container->hasExtension(AnnotationExtension::class)) {
            $container->tag(Listener::class, 'annotation.listener', Listener::class);
            $container->set('router.annotation.collection', new Definition([new Reference('annotation.loader'), 'load'], [Listener::class]))
                ->public(false)
                ->tag('router.collection')
            ;
        }

        if (!$container->has('http.router')) {
            $container->set('http.router', new Definition(Router::class))->autowire([Router::class, RouteMatcherInterface::class, UrlGeneratorInterface::class]);
        }

        if ($container->has('http.middleware.cookie')) {
            $this->middlewares[] = new Reference('http.middleware.cookie');
        }

        if ($container->has('http.middleware.session')) {
            $this->middlewares[] = new Reference('http.middleware.session');
        }

        if ($container->has('http.middleware.policies')) {
            $this->middlewares[] = new Reference('http.middleware.policies');
        }

        if ($container->has('http.middleware.cors')) {
            $this->middlewares[] = new Reference('http.middleware.cors');
        }

        if (isset($configs['response_error'])) {
            $this->middlewares[] = new Statement(ErrorHandlerMiddleware::class, [$configs['response_error']]);
        }

        foreach ($configs['pipes'] ?? [] as $m => $middleware) {
            if (\is_array($middleware)) {
                $pipesMiddleware[$m] = \array_map(static fn (string $m): object => $container->has($m) ? new Reference($m) : new Statement($m), $middleware);
                continue;
            }

            $this->middlewares[] = $container->has($middleware) ? new Reference($middleware) : new Statement($middleware);
        }

        if ($container->has('http.middleware.cache')) {
            $this->middlewares['a'] = new Reference('http.middleware.cache');
        }

        if (true === ($configs['resolve_route_paths'] ?? false)) {
            $this->middlewares['b'] = new Statement(PathMiddleware::class, [$configs['redirect_permanent'], $configs['keep_request_method']]);
        }

        $router = $container->definition('http.router');
        $this->middlewares[] = new Reference('http.middleware.headers');
        $this->middlewares[] = new Statement(PrepareResponseMiddleware::class);
        $this->middlewares[] = new Statement(OriginalMessages::class);

        if ($router instanceof Router) {
            if (!$container instanceof Container) {
                throw new ServiceCreationException(\sprintf('Constructing a "%s" instance requires non-builder container.', Router::class));
            }

            foreach ($pipesMiddleware as $middlewareId => $middlewares) {
                $router->pipes($middlewareId, ...$container->getResolver()->resolveArguments($middlewares));
            }
        } else {
            foreach ($pipesMiddleware as $middlewareId => $middlewares) {
                $router->bind('pipes', [$middlewareId, $middlewares]);
            }

            if (isset($configs['cache'])) {
                $router->arg(1, \str_starts_with($configs['cache'], 'cache.') ? new Reference($configs['cache']) : $configs['cache']);
            }
        }

        foreach ($configs['routes'] as $routeData) {
            if (isset($routeData['debug']) && $container->parameters['debug'] !== $routeData['debug']) {
                continue;
            }

            if ($container instanceof RouterInterface) {
                $route = $container->match($routeData['path'], $routeData['methods'], $routeData['to']);
                unset($routeData['path'], $routeData['methods'], $routeData['to'], $routeData['debug']);

                foreach ($routeData as $key => $value) {
                    if (!empty($value)) {
                        \call_user_func_array([$route, 'name' === $key ? 'bind' : $key], [$value]);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $router = $container->definition('http.router');
        $collection = $container->parameters['routes'] ?? null;
        $routes = $groups = $defaultMiddlewares = $middlewares = $pipesMiddleware = [];
        $mIndex = -1;
        $mSorted = false;

        if (null !== $collection) {
            foreach ($collection->getRoutes() as $route) {
                $routeData = $route->getData();
                $handler = $routeData['handler'] ?? null;
                unset($routeData['handler']);
                $routes[] = new PhpLiteral(Route::class . '::__set_state(\'%?\');', [['handler' => $handler, 'data' => $routeData]]);
            }
        }

        foreach ($container->tagged('router.collection') as $routesId => $values) {
            if ($router instanceof Router) {
                $c = $router->getCollection();
                \is_bool($values) ? $c->populate($container->get($routesId), $values) : $c->group(null, $container->get($routesId))->prototype($values);
                continue;
            }

            $v = [new Reference($routesId), $values];
            $groups[] = [\is_bool($values) ? "\$collection->populate('%?', '%?');" : "\$collection->group(null, '%?')->prototype('%?');", $v];
        }

        foreach ($container->tagged('router.middleware') as $pipeId => $pipeValue) {
            if (\is_string($pipeValue)) {
                $pipesMiddleware[$pipeId][] = new Reference($pipeId);
                continue;
            }
            $middlewares[] = new Reference($pipeId);
        }

        foreach ($this->middlewares as $mK => $middleware) {
            if (!$mSorted && \in_array($mK, ['a', 'b'], true)) {
                $mIndex += \count($defaultMiddlewares = [...$defaultMiddlewares, ...$middlewares, $middleware]) - $mIndex - 1;
                $mSorted = true;

                continue;
            }

            $defaultMiddlewares[++$mIndex] = $middleware;
        }

        if ($router instanceof Router) {
            $router->pipe(...$container->getResolver()->resolveArguments($defaultMiddlewares));

            foreach ($pipesMiddleware as $key => $values) {
                $router->pipes($key, ...$values);
            }
        } else {
            $router->bind('pipe', [$defaultMiddlewares]);
            $groupedCollection = 'function (\Flight\Routing\RouteCollection $collection) {';
            $groupArgs = [];

            foreach ($pipesMiddleware as $key => $values) {
                $router->bind('pipes', [$key, $values]);
            }

            if (!empty($routes)) {
                $groupedCollection .= '$collection->routes(\'%?\', false);';
                $groupArgs[] = $routes;
            }

            foreach ($groups as [$group, $groupArg]) {
                $groupedCollection .= $group;
                $groupArgs = \array_merge($groupArgs, $groupArg);
            }

            $router->bind('setCollection', new PhpLiteral($groupedCollection . '};', $groupArgs));
        }

        $this->middlewares = [];
        unset($container->parameters['routes']);
    }
}
