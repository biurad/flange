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

namespace Rade\Provider;

use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\ContentSecurityPolicyMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\ErrorHandlerMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Middlewares\SessionMiddleware;
use Flight\Routing\Annotation\Listener;
use Flight\Routing\Middlewares\PathMiddleware;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Rade\API\BootableProviderInterface;
use Rade\Application;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Rade\Middleware\RouteHandlerMiddleware;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Flight Routing Provider.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RoutingServiceProvider implements ConfigurationInterface, ServiceProviderInterface, BootableProviderInterface
{
    public const TAG_MIDDLEWARE = 'routing.middleware';

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'routing';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getName());

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('redirect_permanent')->defaultFalse()->end()
                ->booleanNode('response_error')->defaultTrue()->end()
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('namespace')->end()
                        ->scalarNode('matcher_class')->end()
                        ->scalarNode('matcher_dumper_class')->end()
                        ->booleanNode('options_skip')->end()
                        ->booleanNode('debug')->end()
                        ->scalarNode('cache_dir')->end()
                    ->end()
                ->end()
                ->arrayNode('middlewares')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app): void
    {
        $app['routes_factory']  = $app->factory(fn () => new RouteCollection());
        $config = $app->parameters['routing'] ?? [];

        // If debug is not set, use default
        if (!isset($config['options']['debug'])) {
            $config['options']['debug'] = $app->parameters['debug'];
        }

        if (isset($config['redirect_permanent'])) {
            $app['path_middleware'] = new PathMiddleware($config['redirect_permanent']);
        }

        if (isset($app['annotation'])) {
            $app['router.annotation_listener'] = new Listener($app['routes_factory']);
        }

        $app['router'] = static function () use ($app, $config): Router {
            $router = $app->call(Router::class, ['options' => $config['options'] ?? []]);
            $middlewares = array_merge([PathMiddleware::class, HttpMiddleware::class], $config['middlewares'] ?? []);

            if ($app instanceof \Rade\Application) {
                \array_push(
                    $middlewares,
                    CookiesMiddleware::class,
                    SessionMiddleware::class,
                    AccessControlMiddleware::class,
                    ContentSecurityPolicyMiddleware::class,
                    RouteHandlerMiddleware::class,
                    new ErrorHandlerMiddleware($config['response_error']),
                );
            }
            $router->addMiddleware(...$middlewares);

            return $router;
        };
        $app['routes'] = $app['router']->getCollection();

        unset($app->parameters['routing']);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app): void
    {
        // Add tagged middlewares to router.
        foreach ($app->tagged(self::TAG_MIDDLEWARE) as [$middleware, $tag]) {
            if (\is_string($tag)) {
                $app['router']->addMiddleware([$tag => $middleware]);

                continue;
            }

            $app['router']->addMiddleware($middleware);
        }

        // Load routes from annotation ...
        if (isset($app['annotation'])) {
            $app['router']->loadAnnotation($app['annotation']);
        }
    }
}
