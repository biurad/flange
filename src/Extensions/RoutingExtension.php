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
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Flight\Routing\Route;
use Laminas\Stratigility\Middleware\OriginalMessages;
use Nette\Utils\Arrays;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rade\DI\AbstractContainer;
use Rade\DI\Builder\PhpLiteral;
use Rade\DI\Container;
use Rade\DI\ContainerBuilder;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\DI\Services\AliasedInterface;
use Rade\DI\Services\DependenciesInterface;
use Rade\Handler\RouteHandler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Flight Routing Extension. (Recommend being used with AppBuilder).
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RoutingExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, DependenciesInterface, ExtensionInterface
{
    protected const ROUTE_DATA_TO_METHOD = [
        'name' => 'bind',
        'prefix' => 'prefix',
        'hosts' => 'domain',
        'schemes' => 'scheme',
        'methods' => 'method',
        'defaults' => 'defaults',
        'arguments' => 'arguments',
        'middlewares' => 'piped',
        'patterns' => 'asserts',
        'namespace' => 'namespace',
    ];

    /** @var array<int,object> */
    private array $middlewares = [];

    private ?string $routeNamespace;

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
            ->children()
                ->booleanNode('redirect_permanent')->defaultFalse()->end()
                ->booleanNode('keep_request_method')->defaultFalse()->end()
                ->booleanNode('response_error')->defaultValue(null)->end()
                ->booleanNode('resolve_route_paths')->defaultTrue()->end()
                ->scalarNode('namespace')->defaultValue(null)->end()
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
                            ->scalarNode('prefix')->defaultValue(null)->end()
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
                            ->arrayNode('middlewares')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(fn (string $v): array => [$v])
                                ->end()
                                ->prototype('scalar')->defaultValue([])->end()
                            ->end()
                            ->arrayNode('patterns')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected patterns values to be an associate array of string keys mapping to mixed values.')
                                    ->always(function (array $value) {
                                        foreach ($value as $key => $val) {
                                            if (\is_array($val)) {
                                                $value[$key] = \array_merge(...$val);
                                            }
                                        }

                                        return $value;
                                    })
                                ->end()
                                ->prototype('variable')->end()
                            ->end()
                            ->arrayNode('defaults')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected defaults values to be an associate array of string keys mapping to mixed values.')
                                    ->always(function (array $value) {
                                        foreach ($value as $key => $val) {
                                            if (\is_array($val)) {
                                                $value[$key] = \array_merge(...$val);
                                            }
                                        }

                                        return $value;
                                    })
                                ->end()
                                ->prototype('variable')->end()
                            ->end()
                            ->arrayNode('arguments')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                                    ->thenInvalid('Expected arguments values to be an associate array of string keys mapping to mixed values.')
                                    ->always(function (array $value) {
                                        foreach ($value as $key => $val) {
                                            if (\is_array($val)) {
                                                $value[$key] = \array_merge(...$val);
                                            }
                                        }

                                        return $value;
                                    })
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
        $this->routeNamespace = $configs['namespace'] ?? null;
        $pipesMiddleware = [];

        if ($container->has($routeHandler)) {
            $container->alias(RequestHandlerInterface::class, $routeHandler);
        } else {
            $container->set(RequestHandlerInterface::class, new Definition($routeHandler));
        }

        if ($container->hasExtension(AnnotationExtension::class)) {
            $container->autowire('router.annotation_listener', new Definition(Listener::class, [new Statement(RouteCollection::class)]));
        }

        if (!$container->has('http.router')) {
            $container->set('http.router', new Definition(Router::class))->autowire([Router::class, RouteMatcherInterface::class]);
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

        if ($configs['resolve_route_paths']) {
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
                $router->arg(1, $container->has($configs['cache']) ? new Reference($configs['cache']) : $configs['cache']);
            }
        }

        $container->parameters['routes'] = \array_merge($configs['routes'] ?? [], $container->parameters['routes'] ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        [$collection, $groups] = $this->bootRoutes($container, $this->routeNamespace);

        $routes = $container->findBy(RouteCollection::class, static function (string $routesId) use ($container, $groups) {
            if (!empty($collection = $groups[$routesId] ?? [])) {
                $grouped = $container->definition($routesId);

                if ($grouped instanceof RouteCollection) {
                    if (!$container instanceof Container) {
                        throw new ServiceCreationException(\sprintf('Constructing a "%s" instance requires non-builder container.', RouteCollection::class));
                    }

                    return $grouped->prototype($collection)->end();
                }

                $grouped->bind('prototype', [$collection]);
            }

            return new Reference($routesId);
        });
        $middlewares = $container->findBy(MiddlewareInterface::class, function (string $middlewareId) use ($container) {
            $middleware = $container->definition($middlewareId);

            if ($middleware instanceof MiddlewareInterface) {
                if (!$container instanceof Container) {
                    throw new ServiceCreationException(\sprintf('Constructing a "%s" instance requires non-builder container.', MiddlewareInterface::class));
                }

                return $middleware;
            }

            return new Reference($middlewareId);
        });

        $router = $container->definition('http.router');
        $defaultMiddlewares = []; $mIndex = -1; $mSorted = false;

        foreach ($this->middlewares as $mK => $middleware) {
            if (!$mSorted && \in_array($mK, ['a', 'b'], true)) {
                $mIndex += \count($defaultMiddlewares = [...$defaultMiddlewares, ...$middlewares, $middleware]) - $mIndex - 1;
                $mSorted = true;

                continue;
            }

            $defaultMiddlewares[++$mIndex] = $middleware;
        }

        if ($router instanceof Router) {
            if (!$container instanceof Container) {
                throw new ServiceCreationException(\sprintf('Constructing a "%s" instance requires non-builder container.', Router::class));
            }

            $router->pipe(...$container->getResolver()->resolveArguments($defaultMiddlewares));

            if (!empty($collection)) {
                $router->getCollection()->routes($collection[0]);
            }

            foreach ($routes as $group) {
                $router->getCollection()->populate($group instanceof Reference ? $container->get((string) $group) : $group, true);
                $container->removeDefinition((string) $group);
            }

            $container->removeType(RouteCollection::class);
        } else {
            if ($container instanceof Container) {
                throw new ServiceCreationException(\sprintf('Constructing a "%s" instance requires a builder container.', Router::class));
            }

            $router->bind('pipe', [$defaultMiddlewares]);
            $groupedCollection = 'function (\Flight\Routing\RouteCollection $collection) {';

            if (!empty($collection)) {
                $groupedCollection .= '$collection->routes(\'??\');';
            }

            foreach ($routes as $group) {
                $groupedCollection .= '$collection->populate(\'??\', true);';
                $collection[] = $group;
            }

            $router->bind('setCollection', new PhpLiteral($groupedCollection . '};', $collection));
        }

        unset($container->parameters['routes'], $this->middlewares);
    }

    public function bootRoutes(AbstractContainer $container, ?string $routeNamespace): array
    {
        $collection = $groups = [];

        foreach ($container->parameters['routes'] ?? [] as $routeData) {
            if (isset($routeData['debug']) && $container->parameters['debug'] !== $routeData['debug']) {
                continue;
            }

            if ('@' === $routeData['path'][0]) {
                $routesId = \substr($routeData['path'], 1);

                if (null !== $routeData['to'] ?? $routeData['run'] ?? null) {
                    throw new ServiceCreationException(\sprintf('Route declared for collection with id "%s", must not include a handler.', $routesId));
                }

                if (null !== $routeData['name'] ?? null) {
                    throw new ServiceCreationException(\sprintf('Route declared for collection with id "%s", must not include a name.', $routesId));
                }

                unset($routeData['to'], $routeData['run'], $routeData['path'], $routeData['name']);

                foreach ($routeData as $key => $value) {
                    if (!empty($value)) {
                        if ($container instanceof Container) {
                            $value = \is_array($value) && !\array_is_list($value) ? [$value] : $value;
                        }

                        $groups[$routesId][self::ROUTE_DATA_TO_METHOD[$key] ?? $key] = $value;
                    }
                }

                continue;
            }

            /** @var array<int,mixed> $routeArgs */
            $routeArgs = [
                Arrays::pick($routeData, 'path'),
                Arrays::pick($routeData, isset($routeData['methods']) ? 'methods' : 'method', []),
                Arrays::pick($routeData, isset($routeData['to']) ? 'to' : 'run', null),
            ];

            $routeNamespace = (string) $routeNamespace . ($routeData['namespace'] ?? '');
            unset($routeData['namespace']);

            if ($container instanceof ContainerBuilder) {
                $createRoute = Route::class . '::to(\'??\',\'??\', \'??\')';

                if (!empty($routeNamespace)) {
                    $createRoute .= '->namespace(\'??\')';
                    $routeArgs[] = $routeNamespace;
                }

                foreach ($routeData as $key => $value) {
                    if (!empty($value)) {
                        $createRoute .= '->' . (self::ROUTE_DATA_TO_METHOD[$key] ?? $key) . \sprintf("(%s'??')", \is_array($value) && \array_is_list($value) ? '...' : '');
                        $routeArgs[] = $value;
                    }
                }

                $createRoute = new PhpLiteral($createRoute . ';', $routeArgs);
            } else {
                $createRoute = Route::to($routeArgs[0], $routeArgs[1], $routeArgs[2]);

                if (!empty($routeNamespace)) {
                    $createRoute->namespace($routeNamespace);
                }

                foreach ($routeData as $key => $value) {
                    if (!empty($value)) {
                        $container->getResolver()->resolveCallable([$createRoute, self::ROUTE_DATA_TO_METHOD[$key] ?? $key], [$value]);
                    }
                }
            }

            $collection[0][] = $createRoute;
        }

        return [$collection, $groups];
    }
}
