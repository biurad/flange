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

namespace Rade\DI\Extensions\Symfony;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Cache\Adapter\ParameterNormalizer;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\ProxyAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Symfony component cache extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CacheExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'cache';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('Cache configuration')
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('pool')
            ->canBeEnabled()
            ->children()
                ->scalarNode('prefix_seed')
                    ->info('Used to namespace cache keys when using several apps with the same shared backend')
                    ->defaultValue('_%project_dir%.rade')
                    ->example('my-application-name/rade')
                ->end()
                ->scalarNode('app')
                    ->info('App related cache pools configuration')
                    ->defaultValue('cache.adapter.filesystem')
                ->end()
                ->scalarNode('system')
                    ->info('System related cache pools configuration')
                    ->defaultValue('cache.adapter.system')
                ->end()
                ->scalarNode('directory')->isRequired()->end()
                ->scalarNode('default_psr6_provider')->end()
                ->scalarNode('default_redis_provider')->defaultValue('redis://localhost')->end()
                ->scalarNode('default_memcached_provider')->defaultValue('memcached://localhost')->end()
                ->scalarNode('default_doctrine_dbal_provider')->defaultValue('database_connection')->end()
                ->scalarNode('default_pdo_provider')->defaultValue(null)->end()
                ->arrayNode('pools')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->fixXmlConfig('adapter')
                        ->beforeNormalization()
                            ->ifTrue(function ($v) {
                                return isset($v['provider']) && \is_array($v['adapters'] ?? $v['adapter'] ?? null) && 1 < \count($v['adapters'] ?? $v['adapter']);
                            })
                            ->thenInvalid('Pool cannot have a "provider" while more than one adapter is defined')
                        ->end()
                        ->children()
                            ->arrayNode('adapters')
                                ->performNoDeepMerging()
                                ->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')
                                ->beforeNormalization()->castToArray()->end()
                                ->beforeNormalization()
                                    ->always()->then(function ($values) {
                                        if ([0] === \array_keys($values) && \is_array($values[0])) {
                                            return $values[0];
                                        }
                                        $adapters = [];

                                        foreach ($values as $k => $v) {
                                            if (\is_int($k) && \is_string($v)) {
                                                $adapters[] = $v;
                                            } elseif (!\is_array($v)) {
                                                $adapters[$k] = $v;
                                            } elseif (isset($v['provider'])) {
                                                $adapters[$v['provider']] = $v['name'] ?? $v;
                                            } else {
                                                $adapters[] = $v['name'] ?? $v;
                                            }
                                        }

                                        return $adapters;
                                    })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('tags')->defaultNull()->end()
                            ->booleanNode('public')->defaultFalse()->end()
                            ->scalarNode('default_lifetime')
                                ->info('Default lifetime of the pool')
                                ->example('"600" for 5 minutes expressed in seconds, "PT5M" for five minutes expressed as ISO 8601 time interval, or "5 minutes" as a date expression')
                            ->end()
                            ->scalarNode('provider')
                                ->info('Overwrite the setting from the default provider for this adapter.')
                            ->end()
                            ->scalarNode('early_expiration_message_bus')
                                ->example('"messenger.default_bus" to send early expiration events to the default Messenger bus.')
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return isset($v['cache.app']) || isset($v['cache.system']);
                        })
                        ->thenInvalid('"cache.app" and "cache.system" are reserved names')
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(CacheItem::class)) {
            throw new \LogicException('Cache support cannot be enabled as the Cache component is not installed. Try running "composer require symfony/cache".');
        }

        $version = \Rade\Application::VERSION;
        $container->parameters['project.cache_dir'] = $container->parameter($configs['directory']);

        $container->set('cache.adapter.system', new Definition(AbstractAdapter::class . '::createSystemCache', ['', 0, $version, '%project.cache_dir%' . '/pools/system']))->abstract()->public(false)->tag('cache.pool');
        $container->set('cache.adapter.filesystem', new Definition(FilesystemAdapter::class, ['', 0, '%project.cache_dir%' . '/pools/app']))->abstract()->public(false)->tag('cache.pool');
        $container->set('cache.adapter.pdo', new Definition(PdoAdapter::class, [1 => '', 2 => 0]))->public(false)->abstract()->tag('cache.pool', ['provider' => 'cache.default_pdo_provider']);
        $container->set('cache.adapter.array', new Definition(ArrayAdapter::class, [0]))->public(false)->abstract()->tag('cache.pool');

        if ($container->typed(CacheItemPoolInterface::class)) {
            $container->set('cache.adapter.psr6', new Definition(ProxyAdapter::class, [1 => '', 2 => 0]))->public(false)->abstract()->tag('cache.pool', ['provider' => 'cache.default_psr6_provider']);
        }

        if (\function_exists('apcu_fetch')) {
            $container->set('cache.adapter.apcu', new Definition(ApcuAdapter::class, ['', 0, $version]))->public(false)->abstract()->tag('cache.pool');
        }

        if (\class_exists(\Redis::class)) {
            $container->set('cache.adapter.redis', new Definition(RedisAdapter::class, [1 => '', 2 => 0]))->public(false)->abstract()->tag('cache.pool', ['provider' => 'cache.default_redis_provider']);
            $container->set('cache.adapter.redis_tag_aware', new Definition(RedisTagAwareAdapter::class, [1 => '', 2 => 0]))->public(false)->abstract()->tag('cache.pool', ['provider' => 'cache.default_redis_provider']);
        }

        if (\class_exists(\Memcached::class)) {
            $container->set('cache.adapter.memcached', new Definition(MemcachedAdapter::class, [1 => '', 2 => 0]))->public(false)->abstract()->tag('cache.pool', ['provider' => 'cache.default_memcached_provider']);
        }

        $container->set('cache.app', new Reference('cache.adapter.filesystem'))->autowire([CacheItemPoolInterface::class, CacheInterface::class])->tag('cache.pool');
        $container->set('cache.system', new Reference('cache.adapter.system'))->autowire([AdapterInterface::class])->tag('cache.pool');
        $container->set('cache.app.taggable', new Definition(TagAwareAdapter::class, [new Reference('cache.app')]))->public(false);

        if (\class_exists(DefaultMarshaller::class)) {
            $container->set('cache.default_marshaller', new Definition(DefaultMarshaller::class, [1 => '%debug%']))->autowire([MarshallerInterface::class]);
        }

        if (isset($configs['prefix_seed'])) {
            $container->parameters['cache.prefix.seed'] = $configs['prefix_seed'];
        }

        foreach (['psr6', 'redis', 'memcached', 'doctrine_dbal', 'pdo'] as $name) {
            if (isset($config[$name = 'default_' . $name . '_provider'])) {
                $container->alias('cache.' . $name, static::getServiceProvider($container, $config[$name]));
            }
        }

        foreach (['app', 'system'] as $name) {
            $configs['pools']['cache.' . $name] = [
                'adapters' => [$configs[$name]],
                'public' => true,
                'tags' => false,
            ];
        }

        foreach ($configs['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];
            $isRedisTagAware = ['cache.adapter.redis_tag_aware'] === $pool['adapters'];

            foreach ($pool['adapters'] as $provider => $adapter) {
                if (($configs['pools'][$adapter]['adapters'] ?? null) === ['cache.adapter.redis_tag_aware']) {
                    $isRedisTagAware = true;
                } elseif ($configs['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.' . $adapter . '.inner';
                }
            }

            if (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = $container->has($adapter) ? new Reference($adapter) : new Definition($adapter);
            } else {
                $definition = new Definition(ChainAdapter::class, [\array_map(fn ($v) => new Reference($v), $pool['adapters']), 0]);
            }

            if ($isRedisTagAware && 'cache.app' === $name) {
                $container->alias('cache.app.taggable', $name);
            } elseif ($isRedisTagAware) {
                $tagAwareId = $name;
                $container->alias('.' . $name . '.inner', $name);
            } elseif ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.' . $pool['tags'] . '.inner';
                }

                $container->set($name, new Definition(TagAwareAdapter::class), [new Reference('.' . $name . '.inner'), true !== $pool['tags'] ? new Reference($pool['tags']) : null])->public($pool['public']);

                if ($container->typed(LoggerInterface::class)) {
                    $container->definition($name)->bind('setLogger', [new Reference('?logger')]);
                }

                $pool['name'] = $tagAwareId = $name;
                $pool['public'] = false;
                $name = '.' . $name . '.inner';
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $tagAwareId = '.' . $name . '.taggable';
                $container->set($tagAwareId, new Definition(TagAwareAdapter::class, [new Reference($name), null]))->public(false);
            }

            if (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->types([$tagAwareId => TagAwareCacheInterface::class, $name => [CacheInterface::class, CacheItemPoolInterface::class]]);
            }

            $public = $pool['public'];
            unset($pool['adapters'], $pool['public'], $pool['tags']);
            $container->set($name, $definition)->tag('cache.pool', $pool)->public($public);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $seed = $container->parameters['cache.prefix.seed'] ?? ('_' . $container->parameters['project_dir'] . 'rade');
        $allPools = [];
        $attributes = [
            'provider',
            'name',
            'namespace',
            'default_lifetime',
        ];

        foreach ($container->tagged('cache.pool') as $id => $tags) {
            $adapter = $pool = $container->definition($id);

            if ($pool->isAbstract()) {
                continue;
            }

            $class = $adapter->getEntity();
            $name = $tags['name'] ?? $id;

            if (!isset($tags['namespace'])) {
                $namespaceSeed = $seed;

                if (null !== $class) {
                    $namespaceSeed .= '.' . $class;
                }

                $tags['namespace'] = $this->getNamespace($namespaceSeed, $name);
            }

            unset($tags['name']);

            if (isset($tags['provider'])) {
                $tags['provider'] = new Reference(static::getServiceProvider($container, $tags['provider']));
            }

            if (ChainAdapter::class === $class) {
                foreach ($adapter->getArguments()[0] as $provider => $adapter) {
                    if ($adapter instanceof Reference) {
                        $chainedPool = $container->definition((string) $adapter);

                        if (null === $chainedPool) {
                            continue;
                        }
                    } else {
                        $chainedPool = new Definition($adapter);
                    }

                    $chainedTags = [\is_int($provider) ? [] : ['provider' => $provider]];
                    $chainedClass = '';
                    $i = 0;

                    if (ChainAdapter::class === $chainedClass) {
                        throw new \InvalidArgumentException(\sprintf('Invalid service "%s": chain of adapters cannot reference another chain, found "%s".', $id, (string) $chainedPool));
                    }

                    if (isset($chainedTags['provider'])) {
                        $chainedPool->arg($i++, new Reference(static::getServiceProvider($container, $chainedTags['provider'])));
                    }

                    if (isset($tags['namespace']) && !\in_array($chainedPool->getEntity(), [ArrayAdapter::class, NullAdapter::class], true)) {
                        $chainedPool->arg($i++, $tags['namespace']);
                    }

                    if (isset($tags['default_lifetime'])) {
                        $chainedPool->arg($i++, $tags['default_lifetime']);
                    }

                    $chainedPool->abstract(false);
                }

                unset($tags['provider'], $tags['namespace']);
                $i = 1;
            } else {
                $i = 0;
            }

            foreach ($attributes as $attr) {
                if (!isset($tags[$attr])) {
                    // no-op
                } elseif ('namespace' !== $attr || !\in_array($class, [ArrayAdapter::class, NullAdapter::class], true)) {
                    $argument = $tags[$attr];

                    if ('default_lifetime' === $attr && !\is_numeric($argument)) {
                        if (null === $builder = $container->getResolver()->getBuilder()) {
                            $argument = new Statement(ParameterNormalizer::class . '::normalizeDuration', [$argument]);
                        } else {
                            $argument = $builder->staticCall(ParameterNormalizer::class, 'normalizeDuration', [$argument]);
                        }
                    }

                    $pool->arg($i++, $argument);
                }
                unset($tags[$attr]);
            }

            if (!empty($tags)) {
                throw new \InvalidArgumentException(\sprintf('Invalid "cache.pool" tag for service "%s": accepted attributes are "provider", "name", "namespace" and "default_lifetime", found "%s".', $id, \implode('", "', \array_keys($tags))));
            }

            $allPools[$name] = new Reference('?' . $id);
        }

        $allPoolsKeys = \array_keys($allPools);

        if ($container->has('console.command.cache_pool_list')) {
            $container->definition('console.command.cache_pool_list')->arg(0, $allPoolsKeys);
        }

        if ($container->has('console.command.cache_pool_delete')) {
            $container->definition('console.command.cache_pool_delete')->arg(0, $allPoolsKeys);
        }

        unset($container->parameters['cache.prefix.seed']);
    }

    private function getNamespace(string $seed, string $id)
    {
        return \substr(\str_replace('/', '-', \base64_encode(\hash('sha256', $id . $seed, true))), 0, 10);
    }

    /**
     * @internal
     */
    public static function getServiceProvider(AbstractContainer $container, string $name)
    {
        if (\preg_match('#^[a-z]++:#', $name)) {
            $dsn = $name;

            if (!$container->has($name = '.cache_connection.' . \md5($dsn))) {
                $container->set($name, new Definition(AbstractAdapter::class . '::createConnection', [$dsn, ['lazy' => true]]))->public(false);
            }
        }

        return $name;
    }
}
