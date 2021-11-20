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

use Biurad\Http\Cookie;
use Biurad\Http\Factory\CookieFactory;
use Biurad\Http\Factory\NyholmPsr7Factory;
use Biurad\Http\Interfaces\CookieFactoryInterface;
use Biurad\Http\Middlewares\CacheControlMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\HttpCorsMiddleware;
use Biurad\Http\Middlewares\HttpHeadersMiddleware;
use Biurad\Http\Middlewares\HttpPolicyMiddleware;
use Biurad\Http\Session;
use Biurad\Http\Sessions\HandlerFactory;
use Biurad\Http\Sessions\Handlers\AbstractSessionHandler;
use Biurad\Http\Sessions\Handlers\NativeFileSessionHandler;
use Biurad\Http\Sessions\Handlers\NullSessionHandler;
use Biurad\Http\Sessions\Handlers\StrictSessionHandler;
use Biurad\Http\Sessions\MetadataBag;
use Biurad\Http\Sessions\Storage\NativeSessionStorage;
use Rade\DI\AbstractContainer;
use Rade\DI\Definitions\Statement;
use Rade\DI\Services\AliasedInterface;
use Rade\Provider\HttpGalaxy\CorsConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function Rade\DI\Loader\{referenced, service, wrap};

/**
 * Biurad Http Galaxy Provider.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpGalaxyExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'http_galaxy';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getAlias());
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('psr17_factory')->end()
                ->arrayNode('caching')
                    ->canBeEnabled()
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
                    ->canBeEnabled()
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
                        ->booleanNode('expose_csp_nonce')->defaultValue(true)->end()
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
                ->arrayNode('cookie')
                    ->info('cookie configuration')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('prefix_name')->defaultValue('rade_')->end()
                        ->scalarNode('encrypter')->defaultValue(null)->end()
                        ->arrayNode('excludes_encryption')
                            ->prototype('scalar')->defaultValue([])->end()
                        ->end()
                        ->arrayNode('cookies')
                            ->arrayPrototype()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('name')->end()
                                    ->scalarNode('value')->end()
                                    ->variableNode('expires')
                                        ->beforeNormalization()
                                            ->always()
                                            ->then(fn ($v) => wrap('Nette\Utils\DateTime::from', [$v]))
                                        ->end()
                                    ->end()
                                    ->scalarNode('domain')->end()
                                    ->scalarNode('path')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('session')
                    ->info('session configuration')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
                        ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
                        ->scalarNode('name')
                            ->validate()
                                ->ifTrue(function ($v): bool {
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
                        ->enumNode('cookie_samesite')
                            ->values([null, Cookie::SAME_SITE_LAX, Cookie::SAME_SITE_NONE, Cookie::SAME_SITE_STRICT])
                            ->defaultValue(Cookie::SAME_SITE_LAX)
                        ->end()
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
    public function register(AbstractContainer $container, array $configs = []): void
    {
        if (!$container->has('psr17.factory')) {
            $container->autowire('psr17.factory', service($configs['psr17_factory'] ?? NyholmPsr7Factory::class));
        }

        if ($configs['cookie']['enabled']) {
            unset($configs['cookie']['enabled']);
            $cookie = $container->set('http.cookie', service(CookieFactory::class))->typed([CookieFactory::class, CookieFactoryInterface::class]);
            $cookieMiddleware = $container->set('http.middleware.cookie', service(CookiesMiddleware::class));
            $cookies = [];

            foreach ($configs['cookie']['cookies'] as $cookieData) {
                $cookies[] = new Statement(Cookie::class, $cookieData);
            }

            if (!empty($cookies)) {
                $cookie->bind('addCookie', [$cookies]);
            }

            if (!empty($excludeCookies = $configs['cookie']['excludes_encryption'])) {
                $cookieMiddleware->bind('excludeEncodingFor', $excludeCookies);
            }
        }

        $container->set('http.middleware.headers', service(HttpHeadersMiddleware::class, [\array_diff_key($configs['headers'], ['cors' => []])]));

        if ($configs['policies']['enabled']) {
            unset($configs['policies']['enabled']);
            $container->set('http.middleware.policies', service(HttpPolicyMiddleware::class, [$configs['policies']]));
        }

        if ($configs['headers']['cors']['enabled']) {
            unset($configs['headers']['cors']['enabled']);
            $container->set('http.middleware.cors', service(HttpCorsMiddleware::class, [$configs['headers']['cors']]));
        }

        if ($configs['caching']['enabled']) {
            unset($configs['caching']['enabled']);
            $container->set('http.middleware.cache', service(CacheControlMiddleware::class, [3 => $configs['caching']]));
        }

        if (($session = $configs['session'])['enabled']) {
            unset($session['enabled']);

            if (!\extension_loaded('session')) {
                throw new \LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }

            $container->autowire('session.storage.native', service(NativeSessionStorage::class, [
                \array_intersect_key($session, ['storage_id' => null, 'handler_id' => null, 'meta_storage_key' => null, 'metadata_update_threshold' => null]),
                referenced('session.handler'),
            ]))
                ->arg(2, wrap(MetadataBag::class, [$session['meta_storage_key'], $session['metadata_update_threshold']]));

            $container->set('session.handler.native_file', service(StrictSessionHandler::class, [
                $container->isRunningInConsole() ? wrap(NullSessionHandler::class) : wrap(NativeFileSessionHandler::class, [$container->parameters['project_dir'] . '/' . $session['save_path']]),
            ]))->autowire([AbstractSessionHandler::class]);

            if ($container->has($session['handler_id'])) {
                $container->alias('session.handler', $session['handler_id']);
            } else {
                $container->set('session.handler', service([wrap(HandlerFactory::class, [2 => $session['cookie_lifetime']]), 'createHandler']))
                    ->args([$session['handler_id']])
                    ->autowire([AbstractSessionHandler::class]);
            }

            $container->autowire('http.session', service(Session::class, [referenced($session['storage_id'])]));
        }
    }
}
