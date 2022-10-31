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
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Laminas\Stratigility\Middleware\OriginalMessages;
use Psr\Http\Server\RequestHandlerInterface;
use Rade\AppBuilder;
use Rade\DI\Container;
use Rade\DI\Builder\PhpLiteral;
use Rade\DI\ContainerBuilder;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\Handler\RouteHandler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * Flight Routing Extension. (Recommend being used with AppBuilder).
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RoutingExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, DependenciesInterface, ExtensionInterface
{
    /** @var array<int,object> */
    private array $middlewares = [];

    /** @var array<string,mixed> */
    private array $defaults = [];

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
            ->fixXmlConfig('method')
            ->children()
                ->booleanNode('redirect_permanent')->defaultFalse()->end()
                ->booleanNode('keep_request_method')->defaultFalse()->end()
                ->booleanNode('response_error')->defaultValue(null)->end()
                ->booleanNode('resolve_route_paths')->defaultTrue()->end()
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
                ->append(Config\RouteSection::getConfigNode('import', true)->end())
                ->append(Config\RouteSection::getConfigNode('routes')->end())
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        $pipesMiddleware = [];

        if (!$container->has(RequestHandlerInterface::class)) {
            $container->set(RequestHandlerInterface::class, new Definition(RouteHandler::class));
        }

        if ($container->hasExtension(AnnotationExtension::class)) {
            $container->tag(Listener::class, 'annotation.listener', Listener::class);
            $container->set('router.annotation.collection', new Definition([new Reference('annotation.loader'), 'load'], [Listener::class]))
                ->public(false)
                ->tag('router.collection', false)
            ;
        }

        if (!$container->has('http.router')) {
            $container->set('http.router', new Definition(Router::class))->typed(Router::class, RouteMatcherInterface::class, UrlGeneratorInterface::class);
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

        if ($container->shared('http.router') && !$container instanceof ContainerBuilder) {
            $container->removeShared('http.router');
        }

        foreach ($pipesMiddleware as $middlewareId => $middlewares) {
            $router->bind('pipes', [$middlewareId, $middlewares]);
        }

        if (isset($configs['cache'])) {
            $router->arg(1, $configs['cache']);
        }

        foreach ($configs['routes'] as $routeData) {
            if (isset($routeData['debug']) && $container->parameters['debug'] !== $routeData['debug']) {
                continue;
            }

            if (empty($routeData['path'] ?? null)) {
                throw new \InvalidArgumentException('Route path is required.');
            }
            $routeData['add'] = [$routeData['path'], $routeData['methods'], $routeData['run']];
            unset($routeData['path'], $routeData['methods'], $routeData['run'], $routeData['debug']);

            if (!$container instanceof AppBuilder) {
                \ksort($routeData);
                $router->bind('getCollection|prototype', [$routeData]);
            } else {
                $route = $container->match(...$routeData['add']);
                unset($routeData['add']);

                foreach ($routeData as $key => $value) {
                    if (empty($value)) {
                        continue;
                    }
                    \call_user_func_array([$route, $key], \is_array($value) ? $value : [$value]);
                }
            }
        }

        foreach ($configs['import'] ?? [] as $routeFile => $collection) {
            if (isset($collection['debug']) && $container->parameters['debug'] !== $collection['debug']) {
                continue;
            }
            unset($collection['debug']);

            if ('@' === $routeFile) {
                $this->defaults = $collection;
                continue;
            }

            if (\str_ends_with($routeFile = $container->parameter($routeFile), '.yaml')) {
                $routes = \function_exists('yaml_parse_file') ? \yaml_parse_file($routeFile) : Yaml::parseFile($routeFile);
            } elseif (!\str_ends_with($routeFile, '.php')) {
                throw new InvalidConfigurationException(\sprintf('Route file "%s" loading support only yaml or php.'));
            } else {
                $routes = require $routeFile;
            }

            $routes = (new Processor())->process(Config\RouteSection::getConfigNode('routes')->end()->getNode(true), ['routes' => $routes['routes'] ?? $routes]);
            $collectionDef = new RouteCollection();

            if ($collection = \array_filter($collection)) {
                $collection['method'] = $collection['methods'] ?? [];
                $groupName = $collection['bind'] ?? null;
                unset($collection['methods'], $collection['bind']);
                $collectionDef->prototype($collection);
            }

            foreach ($routes as $routeData) {
                if (empty($routeData['path'] ?? null)) {
                    throw new \InvalidArgumentException('Route path is required.');
                }

                $routeData['add'] = [$routeData['path'], $routeData['methods'], $routeData['run']];
                unset($routeData['path'], $routeData['methods'], $routeData['run']);
                \ksort($routeData);
                $collectionDef->prototype($routeData);
            }

            if (!$container instanceof AppBuilder) {
                $router->bind('getCollection|group', [$groupName ?? null, $collectionDef]);
            } else {
                $container->group($groupName ?? '', $collectionDef);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        $router = $container->definition('http.router');
        $collection = $container->parameters['routes'] ?? null;
        $routes = $groups = $defaultMiddlewares = $middlewares = $pipesMiddleware = [];
        $mIndex = -1;
        $mSorted = false;

        if (null !== $collection) {
            foreach ($collection->getRoutes() as $route) {
                if (\is_object($route)) {
                    $routeData = $route->getData();
                    $handler = $routeData['handler'] ?? null;
                    unset($routeData['handler']);
                    $routes[] = new PhpLiteral(Route::class . '::__set_state(\'%?\');', [['handler' => $handler, 'data' => $routeData]]);
                    continue;
                }
                $routes[] = new PhpLiteral('$collection->prototype(\'%?\');', [\array_filter([
                    'add' => [$route['path'], \array_keys($route['methods'] ?? Router::DEFAULT_METHODS), $route['handler'] ?? null],
                    'bind' => $route['name'] ?? null,
                    'scheme' => \array_keys($route['schemes'] ?? []),
                    'domain' => \array_keys($route['hosts'] ?? []),
                    'placeholders' => $route['placeholders'] ?? [],
                    'defaults' => $route['defaults'] ?? [],
                    'arguments' => $route['arguments'] ?? [],
                    'piped' => \array_keys($route['middlewares'] ?? [])
                ])]);
            }
        }

        foreach ($container->tagged('router.collection') as $routesId => $values) {
            $values = [new Reference($routesId), $values];

            if (!$container instanceof ContainerBuilder) {
                $router->bind('getCollection|populate', $values);
                continue;
            }
            $groups[] = ["\$collection->populate('%?', '%?');", $values];
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

        $router->bind('pipe', [$defaultMiddlewares]);

        foreach ($pipesMiddleware as $key => $values) {
            $router->bind('pipes', [$key, ...$values]);
        }

        if (!$container instanceof ContainerBuilder) {
            $router->bind('getCollection|prototype', true);
            $router->bind('getCollection|prototype', [$this->defaults]);
        } else {
            $groupedCollection = 'function (\Flight\Routing\RouteCollection $collection) {';
            $groupArgs = [];

            if (!empty($this->defaults)) {
                $groupedCollection .= '$collection->prototype("%?");';
                $groupArgs[] = $this->defaults;
            }

            if (\class_exists('Flight\Routing\Route')) {
                $groupedCollection .= '$collection->routes(\'%?\', false);';
                $groupArgs[] = $routes;
            } else {
                foreach ($routes as $r) {
                    $groupedCollection .= "'%?';";
                    $groupArgs[] = $r;
                }
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
