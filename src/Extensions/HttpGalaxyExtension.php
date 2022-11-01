<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions;

use Biurad\Http\Factory\CookieFactory;
use Biurad\Http\Factory\Psr17Factory;
use Biurad\Http\Interfaces\CookieFactoryInterface;
use Biurad\Http\Middlewares\CacheControlMiddleware;
use Biurad\Http\Middlewares\CookiesMiddleware;
use Biurad\Http\Middlewares\HttpCorsMiddleware;
use Biurad\Http\Middlewares\HttpHeadersMiddleware;
use Biurad\Http\Middlewares\HttpPolicyMiddleware;
use Biurad\Http\Middlewares\SessionMiddleware;
use Flange\KernelInterface;
use Rade\DI\Container;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;

use function Rade\DI\Loader\{reference, service, wrap};

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

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
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('HTTP Galaxy configuration')
            ->fixXmlConfig('policy')
            ->fixXmlConfig('header')
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
                ->arrayNode('csrf_protection')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => \is_bool($v))
                        ->then(fn ($v) => ['enabled' => $v])
                    ->end()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
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
                        ->append(Config\HttpCorsSection::getConfigNode())
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
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => \is_bool($v))->then(fn ($v) => ['enabled' => $v])
                        ->ifString()->then(fn ($v) => ['prefix_name' => $v, 'enabled' => true])
                    ->end()
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
                                    ->scalarNode('name')->isRequired()->end()
                                    ->scalarNode('value')->defaultNull()->end()
                                    ->variableNode('expire')
                                        ->beforeNormalization()
                                            ->always()
                                            ->then(fn ($v) => wrap('Nette\Utils\DateTime::from', [$v]))
                                        ->end()
                                        ->defaultValue(0)
                                    ->end()
                                    ->scalarNode('path')->defaultValue('/')->end()
                                    ->scalarNode('domain')->defaultNull()->end()
                                    ->booleanNode('secure')->defaultNull()->end()
                                    ->booleanNode('httpOnly')->defaultTrue()->end()
                                    ->booleanNode('raw')->defaultFalse()->end()
                                    ->enumNode('sameSite')
                                        ->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_NONE, Cookie::SAMESITE_STRICT])
                                        ->defaultValue(Cookie::SAMESITE_LAX)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('session')
                    ->info('session configuration')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => \is_bool($v))
                        ->then(fn ($v) => ['enabled' => $v])
                    ->end()
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
                        ->enumNode('cookie_secure')->values([true, false])->end()
                        ->booleanNode('cookie_httponly')->defaultTrue()->end()
                        ->enumNode('cookie_samesite')
                            ->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_NONE, Cookie::SAMESITE_STRICT])
                            ->defaultValue(Cookie::SAMESITE_LAX)
                        ->end()
                        ->booleanNode('use_cookies')->end()
                        ->scalarNode('gc_divisor')->end()
                        ->scalarNode('gc_probability')->defaultValue(1)->end()
                        ->scalarNode('gc_maxlifetime')->end()
                        ->scalarNode('save_path')->defaultValue('%project.var_dir%/sessions')->end()
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
    public function register(Container $container, array $configs = []): void
    {
        $definitions = [
            'http.middleware.headers' => service(HttpHeadersMiddleware::class, [\array_diff_key($configs['headers'], ['cors' => []])])->public(false),
        ];

        if (!$container->has('psr17.factory')) {
            $definitions['psr17.factory'] = service($configs['psr17_factory'] ?? Psr17Factory::class)->typed();
        }

        if ($configs['cookie']['enabled']) {
            unset($configs['cookie']['enabled']);
            $definitions += [
                'http.cookie' => $cookie = service(CookieFactory::class)->typed(CookieFactory::class, CookieFactoryInterface::class),
                'http.middleware.cookie' => $cookieMiddleware = service(CookiesMiddleware::class)->public(false),
            ];
            $cookies = [];

            foreach ($configs['cookie']['cookies'] as $cookieData) {
                $cookies[] = wrap(Cookie::class, $cookieData);
            }

            if (!empty($cookies)) {
                $cookie->bind('addCookie', [$cookies]);
            }

            if (!empty($excludeCookies = $configs['cookie']['excludes_encryption'])) {
                $cookieMiddleware->bind('excludeEncodingFor', $excludeCookies);
            }
        }

        if ($configs['policies']['enabled']) {
            unset($configs['policies']['enabled']);
            $definitions['http.middleware.policies'] = service(HttpPolicyMiddleware::class, [$configs['policies']])->public(false);
        }

        if ($configs['headers']['cors']['enabled']) {
            unset($configs['headers']['cors']['enabled']);
            $definitions['http.middleware.cors'] = service(HttpCorsMiddleware::class, [$configs['headers']['cors']])->public(false);
        }

        if ($configs['caching']['enabled']) {
            unset($configs['caching']['enabled']);
            $definitions['http.middleware.cache'] = service(CacheControlMiddleware::class, [3 => $configs['caching']])->public(false);
        }

        if (($session = $configs['session'])['enabled']) {
            unset($session['enabled']);

            if (!\extension_loaded('session')) {
                throw new \LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }

            $inConsole = $container instanceof KernelInterface && $container->isRunningInConsole();
            $metaBag = wrap(MetadataBag::class, [$session['meta_storage_key'], $session['metadata_update_threshold']]);
            $sessionArgs = \array_diff_key($session, ['storage_id' => null, 'handler_id' => null, 'meta_storage_key' => null, 'metadata_update_threshold' => null]);
            $container->multiple([
                'session.storage.native' => ($inConsole ? service(MockArraySessionStorage::class, [1 => $metaBag]) : service(NativeSessionStorage::class, [
                    $sessionArgs,
                    reference('session.handler'),
                    $metaBag,
                ]))->typed(),
                'session.handler.native_file' => ($inConsole ? service(NullSessionHandler::class) : service(NativeFileSessionHandler::class, [$session['save_path']]))->typed(),
                'http.middleware.session' => service(SessionMiddleware::class, [wrap(reference('http.session'), $sessionArgs, true)])->public(false),
                'http.session' => service(Session::class, [reference($session['storage_id'])])->typed(),
            ]);

            if ($container->has($session['handler_id'])) {
                $container->alias('session.handler', $session['handler_id']);
            } else {
                $definitions['session.handler'] = service([wrap(SessionHandlerFactory::class), 'createHandler'], [$session['handler_id']])->typed(AbstractSessionHandler::class);
            }
        }

        if ($configs['csrf_protection']['enabled']) {
            unset($configs['csrf_protection']['enabled']);

            if (!\class_exists(\Symfony\Component\Security\Csrf\CsrfToken::class)) {
                throw new \LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
            }

            $definitions += [
                'http.csrf.token_storage' => ($container->has('http.session') ? service(SessionTokenStorage::class, [reference('request_stack')]) : service(NativeSessionTokenStorage::class))->typed(),
                'http.csrf.token_manager' => service(CsrfTokenManager::class, [1 => reference('http.csrf.token_storage'), 2 => reference('?request_stack')])->typed(),
            ];
        }

        $container->multiple($definitions);
    }
}
