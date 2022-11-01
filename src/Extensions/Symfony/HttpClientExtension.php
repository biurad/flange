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

namespace Flange\Extensions\Symfony;

use Flange\Extensions\Config\HttpClientRetrySection;
use Psr\Http\Client\ClientInterface;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttplugClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\ScopingHttpClient;

/**
 * Symfony component http client extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpClientExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'http_client';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('HTTP Client configuration')
            ->canBeEnabled()
            ->fixXmlConfig('scoped_client')
            ->beforeNormalization()
                ->always(function ($config) {
                    if (empty($config['scoped_clients']) || !\is_array($config['default_options']['retry_failed'] ?? null)) {
                        return $config;
                    }

                    foreach ($config['scoped_clients'] as &$scopedConfig) {
                        if (!isset($scopedConfig['retry_failed']) || true === $scopedConfig['retry_failed']) {
                            $scopedConfig['retry_failed'] = $config['default_options']['retry_failed'];

                            continue;
                        }

                        if (\is_array($scopedConfig['retry_failed'])) {
                            $scopedConfig['retry_failed'] = $scopedConfig['retry_failed'] + $config['default_options']['retry_failed'];
                        }
                    }

                    return $config;
                })
            ->end()
            ->children()
                ->integerNode('max_host_connections')
                    ->info('The maximum number of connections to a single host.')
                ->end()
                ->arrayNode('default_options')
                    ->fixXmlConfig('header')
                    ->children()
                        ->arrayNode('headers')
                            ->info('Associative array: header => value(s).')
                            ->useAttributeAsKey('name')
                            ->normalizeKeys(false)
                            ->variablePrototype()->end()
                        ->end()
                        ->integerNode('max_redirects')
                            ->info('The maximum number of redirects to follow.')
                        ->end()
                        ->scalarNode('http_version')
                            ->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')
                        ->end()
                        ->arrayNode('resolve')
                            ->info('Associative array: domain => IP.')
                            ->useAttributeAsKey('host')
                            ->beforeNormalization()
                                ->always(function ($config) {
                                    if (!\is_array($config)) {
                                        return [];
                                    }

                                    if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                                        return $config;
                                    }

                                    return [$config['host'] => $config['value']];
                                })
                            ->end()
                            ->normalizeKeys(false)
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('proxy')
                            ->info('The URL of the proxy to pass requests through or null for automatic detection.')
                        ->end()
                        ->scalarNode('no_proxy')
                            ->info('A comma separated list of hosts that do not require a proxy to be reached.')
                        ->end()
                        ->floatNode('timeout')
                            ->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')
                        ->end()
                        ->floatNode('max_duration')
                            ->info('The maximum execution time for the request+response as a whole.')
                        ->end()
                        ->scalarNode('bindto')
                            ->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')
                        ->end()
                        ->booleanNode('verify_peer')
                            ->info('Indicates if the peer should be verified in an SSL/TLS context.')
                        ->end()
                        ->booleanNode('verify_host')
                            ->info('Indicates if the host should exist as a certificate common name.')
                        ->end()
                        ->scalarNode('cafile')
                            ->info('A certificate authority file.')
                        ->end()
                        ->scalarNode('capath')
                            ->info('A directory that contains multiple certificate authority files.')
                        ->end()
                        ->scalarNode('local_cert')
                            ->info('A PEM formatted certificate file.')
                        ->end()
                        ->scalarNode('local_pk')
                            ->info('A private key file.')
                        ->end()
                        ->scalarNode('passphrase')
                            ->info('The passphrase used to encrypt the "local_pk" file.')
                        ->end()
                        ->scalarNode('ciphers')
                            ->info('A list of SSL/TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')
                        ->end()
                        ->arrayNode('peer_fingerprint')
                            ->info('Associative array: hashing algorithm => hash(es).')
                            ->normalizeKeys(false)
                            ->children()
                                ->variableNode('sha1')->end()
                                ->variableNode('pin-sha256')->end()
                                ->variableNode('md5')->end()
                            ->end()
                        ->end()
                        ->append(HttpClientRetrySection::getConfigNode())
                    ->end()
                ->end()
                ->scalarNode('mock_response_factory')
                    ->info('The id of the service that should generate mock responses. It should be either an invokable or an iterable.')
                ->end()
                ->arrayNode('scoped_clients')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->fixXmlConfig('header')
                        ->beforeNormalization()
                            ->always()
                            ->then(function ($config) {
                                if (!\class_exists(HttpClient::class)) {
                                    throw new \LogicException('HttpClient support cannot be enabled as the component is not installed. Try running "composer require symfony/http-client".');
                                }

                                return \is_array($config) ? $config : ['base_uri' => $config];
                            })
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !isset($v['scope']) && !isset($v['base_uri']);
                            })
                            ->thenInvalid('Either "scope" or "base_uri" should be defined.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !empty($v['query']) && !isset($v['base_uri']);
                            })
                            ->thenInvalid('"query" applies to "base_uri" but no base URI is defined.')
                        ->end()
                        ->children()
                            ->scalarNode('scope')
                                ->info('The regular expression that the request URL must match before adding the other options. When none is provided, the base URI is used instead.')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('base_uri')
                                ->info('The URI to resolve relative URLs, following rules in RFC 3985, section 2.')
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('auth_basic')
                                ->info('An HTTP Basic authentication "username:password".')
                            ->end()
                            ->scalarNode('auth_bearer')
                                ->info('A token enabling HTTP Bearer authorization.')
                            ->end()
                            ->scalarNode('auth_ntlm')
                                ->info('A "username:password" pair to use Microsoft NTLM authentication (requires the cURL extension).')
                            ->end()
                            ->arrayNode('query')
                                ->info('Associative array of query string values merged with the base URI.')
                                ->useAttributeAsKey('key')
                                ->beforeNormalization()
                                    ->always(function ($config) {
                                        if (!\is_array($config)) {
                                            return [];
                                        }

                                        if (!isset($config['key'], $config['value']) || \count($config) > 2) {
                                            return $config;
                                        }

                                        return [$config['key'] => $config['value']];
                                    })
                                ->end()
                                ->normalizeKeys(false)
                                ->scalarPrototype()->end()
                            ->end()
                            ->arrayNode('headers')
                                ->info('Associative array: header => value(s).')
                                ->useAttributeAsKey('name')
                                ->normalizeKeys(false)
                                ->variablePrototype()->end()
                            ->end()
                            ->integerNode('max_redirects')
                                ->info('The maximum number of redirects to follow.')
                            ->end()
                            ->scalarNode('http_version')
                                ->info('The default HTTP version, typically 1.1 or 2.0, leave to null for the best version.')
                            ->end()
                            ->arrayNode('resolve')
                                ->info('Associative array: domain => IP.')
                                ->useAttributeAsKey('host')
                                ->beforeNormalization()
                                    ->always(function ($config) {
                                        if (!\is_array($config)) {
                                            return [];
                                        }

                                        if (!isset($config['host'], $config['value']) || \count($config) > 2) {
                                            return $config;
                                        }

                                        return [$config['host'] => $config['value']];
                                    })
                                ->end()
                                ->normalizeKeys(false)
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('proxy')
                                ->info('The URL of the proxy to pass requests through or null for automatic detection.')
                            ->end()
                            ->scalarNode('no_proxy')
                                ->info('A comma separated list of hosts that do not require a proxy to be reached.')
                            ->end()
                            ->floatNode('timeout')
                                ->info('The idle timeout, defaults to the "default_socket_timeout" ini parameter.')
                            ->end()
                            ->floatNode('max_duration')
                                ->info('The maximum execution time for the request+response as a whole.')
                            ->end()
                            ->scalarNode('bindto')
                                ->info('A network interface name, IP address, a host name or a UNIX socket to bind to.')
                            ->end()
                            ->booleanNode('verify_peer')
                                ->info('Indicates if the peer should be verified in an SSL/TLS context.')
                            ->end()
                            ->booleanNode('verify_host')
                                ->info('Indicates if the host should exist as a certificate common name.')
                            ->end()
                            ->scalarNode('cafile')
                                ->info('A certificate authority file.')
                            ->end()
                            ->scalarNode('capath')
                                ->info('A directory that contains multiple certificate authority files.')
                            ->end()
                            ->scalarNode('local_cert')
                                ->info('A PEM formatted certificate file.')
                            ->end()
                            ->scalarNode('local_pk')
                                ->info('A private key file.')
                            ->end()
                            ->scalarNode('passphrase')
                                ->info('The passphrase used to encrypt the "local_pk" file.')
                            ->end()
                            ->scalarNode('ciphers')
                                ->info('A list of SSL/TLS ciphers separated by colons, commas or spaces (e.g. "RC3-SHA:TLS13-AES-128-GCM-SHA256"...)')
                            ->end()
                            ->arrayNode('peer_fingerprint')
                                ->info('Associative array: hashing algorithm => hash(es).')
                                ->normalizeKeys(false)
                                ->children()
                                    ->variableNode('sha1')->end()
                                    ->variableNode('pin-sha256')->end()
                                    ->variableNode('md5')->end()
                                ->end()
                            ->end()
                            ->append(HttpClientRetrySection::getConfigNode())
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
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(HttpClient::class)) {
            throw new \LogicException('HttpClient support cannot be enabled as the HttpClient component is not installed. Try running "composer require symfony/http-client".');
        }

        $options = $config['default_options'] ?? [];
        $retryOptions = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);

        $client = $container->set('http_client', new Definition(HttpClient::class . '::create', [$options, $configs['max_host_connections'] ?? 6]));

        if (\class_exists(\Http\Client\HttpClient::class)) {
            $container->set(\Http\Client\HttpClient::class, new Definition(HttplugClient::class, [new Reference('http_client')]));
        }

        if ($hasPsr18 = \interface_exists(ClientInterface::class)) {
            $client->public(false);
            $container->autowire('psr18.http_client', new Definition(Psr18Client::class, [new Reference('http_client')]));
        } else {
            $client->typed();
        }

        if (\array_key_exists('enabled', $retryOptions)) {
            $this->registerRetryableHttpClient($options, 'http_client', $container);
        }

        $httpClientId = ($retryOptions['enabled'] ?? false) ? 'http_client.retryable' : 'http_client';

        foreach ($configs['scoped_clients'] as $name => $scopeConfig) {
            if ('http_client' === $name) {
                throw new \InvalidArgumentException(\sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);
            $retryOptions = $scopeConfig['retry_failed'] ?? ['enabled' => false];
            unset($scopeConfig['retry_failed']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                $container->set($name, new Definition(ScopingHttpClient::class . '::forBaseUri', [new Reference($httpClientId), $baseUri, $scopeConfig]));
            } else {
                $container->set($name, new Definition(ScopingHttpClient::class, [new Reference($httpClientId), [$scope => $scopeConfig], $scope]));
            }

            if (\array_key_exists('enabled', $retryOptions)) {
                $this->registerRetryableHttpClient($retryOptions, $name, $container);
            }

            $container->type($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->set('psr18.' . $name, new Reference('psr18.http_client'))->arg(0, new Reference($name))->typed(ClientInterface::class);
            }
        }

        if ($responseFactoryId = $config['mock_response_factory'] ?? null) {
            $container->set($httpClientId . '.mock_client', new Definition(MockHttpClient::class, [new Reference($responseFactoryId)]));
        }
    }

    private function registerRetryableHttpClient(array $options, string $name, Container $container): void
    {
        if (!\class_exists(RetryableHttpClient::class)) {
            throw new \LogicException('Support for retrying failed requests requires symfony/http-client 5.2 or higher, try upgrading.');
        }

        if (null !== ($options['retry_strategy'] ?? null)) {
            $retryStrategy = new Reference($options['retry_strategy']);
        } else {
            $codes = [];

            foreach ($options['http_codes'] ?? [] as $code => $codeOptions) {
                if ($codeOptions['methods']) {
                    $codes[$code] = $codeOptions['methods'];
                } else {
                    $codes[] = $code;
                }
            }

            $retryArgs = [1 => $options['delay'] ?? 1000, 2 => $options['multiplier'] ?? 2.0, 3 => $options['max_delay'] ?? 0, 4 => $options['jitter'] ?? 0.1];
            $retryStrategy = new Statement(GenericRetryStrategy::class, (!empty($codes) ? [0 => $codes] : []) + $retryArgs);
        }

        $container->set($name . '.retryable', new Definition(RetryableHttpClient::class, [new Reference($name), $retryStrategy, $options['max_retries'] ?? 3]));
    }
}
