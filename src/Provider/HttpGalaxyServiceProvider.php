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

use Biurad\Http\Cookie;
use Biurad\Http\Factories\GuzzleHttpPsr7Factory;
use Biurad\Http\Factory\CookieFactory;
use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\CacheControlMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Session;
use Biurad\Http\Sessions\HandlerFactory;
use Biurad\Http\Sessions\Handlers\AbstractSessionHandler;
use Biurad\Http\Sessions\Handlers\NativeFileSessionHandler;
use Biurad\Http\Sessions\Handlers\NullSessionHandler;
use Biurad\Http\Sessions\Handlers\StrictSessionHandler;
use Biurad\Http\Sessions\MetadataBag;
use Biurad\Http\Sessions\Storage\NativeSessionStorage;
use Biurad\Http\Sessions\Storage\PhpBridgeSessionStorage;
use Biurad\Http\Strategies\ContentSecurityPolicy;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Rade\Provider\HttpGalaxy\CorsConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Biurad Http Galaxy Provider.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpGalaxyServiceProvider implements ConfigurationInterface, ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'http';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getName());
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('caching')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('cache_lifetime')->defaultValue(86400 * 30)->end()
                        ->integerNode('default_ttl')->end()
                        ->enumNode('hash_algo')->values(\hash_algos())->end()
                        ->arrayNode('methods')
                            ->defaultValue(['GET', 'HEAD'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('respect_response_cache_directives')
                            ->performNoDeepMerging()
                            ->defaultValue(['no-cache', 'private', 'max-age', 'no-store'])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('cache_key_generator')->end()
                        ->arrayNode('cache_listeners')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('blacklisted_paths')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('policies')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('content_security_policy')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('csp_report_only')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('feature_policy')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('referrer_policy')
                            ->defaultValue([])
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('frame_policy')
                            ->beforeNormalization()
                                ->ifTrue(fn ($v) => false === $v)
                                ->then(fn ($v) => 'DENY')
                            ->end()
                            ->defaultValue('SAMEORIGIN')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append(CorsConfiguration::getConfigNode())
                        ->arrayNode('request')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                        ->arrayNode('response')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('session')
                    ->info('session configuration')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                        ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
                        ->scalarNode('name')
                            ->validate()
                                ->ifTrue(function ($v) {
                                    \parse_str($v, $parsed);

                                    return \implode('&', \array_keys($parsed)) !== (string) $v;
                                })
                                ->thenInvalid('Session name %s contains illegal character(s)')
                            ->end()
                        ->end()
                        ->scalarNode('cookie_lifetime')->defaultValue(\ini_get('session.gc_maxlifetime'))->end()
                        ->scalarNode('cookie_path')->end()
                        ->scalarNode('cookie_domain')->end()
                        ->enumNode('cookie_secure')->values([true, false, 'auto'])->end()
                        ->booleanNode('cookie_httponly')->defaultTrue()->end()
                        ->enumNode('cookie_samesite')->values(Cookie::SAMESITE_COLLECTION)->defaultValue('lax')->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->defaultValue(1)->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->scalarNode('save_path')->defaultValue('sessions')->end()
                        ->scalarNode('meta_storage_key')->defaultValue('_rade_meta')->end()
                        ->integerNode('metadata_update_threshold')
                            ->defaultValue(0)
                            ->info('seconds to wait between 2 session metadata updates')
                        ->end()
                        ->integerNode('sid_length')
                            ->min(22)
                            ->max(256)
                        ->end()
                        ->integerNode('sid_bits_per_character')
                            ->min(4)
                            ->max(6)
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
    public function register(Container $app): void
    {
        $app['http.factory'] = new GuzzleHttpPsr7Factory();
        $app['http.server_request_creator'] = fn () => GuzzleHttpPsr7Factory::fromGlobalRequest();
        $app['http.emitter'] = new SapiStreamEmitter();
        $config = $app['http.config'] ?? [];

        $app['http.csp_middleare'] = new ContentSecurityPolicy([] === $config['policies']['content_security_policy']);
        $app['http.acl_middleware'] = new AccessControlMiddleware($config['headers']['cors'] ?? []);
        $app['http.middleware'] = new HttpMiddleware(\array_intersect_key($config, \array_flip(['policies', 'headers'])));

        if (isset($app['cache.psr6'])) {
            $app['http.cache_middleware'] = $app->callInstance(CacheControlMiddleware::class, [3 => $config['caching']]);
        }
        $session = $config['session'];

        $app['session.storage.metadata_bag'] = new MetadataBag($session['meta_storage_key'], $session['metadata_update_threshold']);
        $app['session.handler.native_file'] = function (Container $app) use ($session) {
            if (\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
                return new NullSessionHandler();
            }

            return new StrictSessionHandler(
                new NativeFileSessionHandler($app['project_dir'] . $session['save_path'])
            );
        };
        $app['session.handler'] =  function (Container $app) use ($session): AbstractSessionHandler {
            $handler = $app->callInstance(
                HandlerFactory::class,
                ['minutes' => $session['cookie_lifetime']]
            );

            try {
                return $handler->createHandler($session['handler_id']);
            } catch (\InvalidArgumentException $e) {
                return $app[$session['handler_id']];
            }
        };
        $app['session.storage.native'] = $app->callInstance(NativeSessionStorage::class, [$session, $app['session.handler']]);
        $app['session.storage.php_bridge'] = $app->callInstance(PhpBridgeSessionStorage::class, [$app['session.handler']]);
        $app['cookie'] = function () use ($session): CookieFactory {
            $cookie = new CookieFactory();

            return $cookie->setDefaultPathAndDomain(
                $session['cookie_path'] ?? '/',
                $session['cookie_domain'] ?? '',
                $session['cookie_secure'] ?? $session['cookie_httponly']
            );
        };
        $app['session'] = new Session($app[$session['storage_id']]);

        unset($app['http.config']);
    }
}
