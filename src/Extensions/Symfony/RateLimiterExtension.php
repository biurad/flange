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

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Symfony component rate limiter extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RateLimiterExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'rate_limiter';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('Rate limiter configuration')
            ->canBeEnabled()
            ->fixXmlConfig('limiter')
            ->beforeNormalization()
                ->ifTrue(function ($v) {
                    return \is_array($v) && !isset($v['limiters']) && !isset($v['limiter']);
                })
                ->then(function (array $v) {
                    $newV = [
                        'enabled' => $v['enabled'] ?? true,
                    ];
                    unset($v['enabled']);

                    $newV['limiters'] = $v;

                    return $newV;
                })
            ->end()
            ->children()
                ->arrayNode('limiters')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('lock_factory')
                                ->info('The service ID of the lock factory used by this limiter (or null to disable locking)')
                                ->defaultValue('lock.factory')
                            ->end()
                            ->scalarNode('cache_pool')
                                ->info('The cache pool to use for storing the current limiter state')
                                ->defaultValue('cache.app')
                            ->end()
                            ->scalarNode('storage_service')
                                ->info('The service ID of a custom storage implementation, this precedes any configured "cache_pool"')
                                ->defaultNull()
                            ->end()
                            ->enumNode('policy')
                                ->info('The algorithm to be used by this limiter')
                                ->isRequired()
                                ->values(['fixed_window', 'token_bucket', 'sliding_window', 'no_limit'])
                            ->end()
                            ->integerNode('limit')
                                ->info('The maximum allowed hits in a fixed interval or burst')
                                ->isRequired()
                            ->end()
                            ->scalarNode('interval')
                                ->info('Configures the fixed interval if "policy" is set to "fixed_window" or "sliding_window". The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                            ->end()
                            ->arrayNode('rate')
                                ->info('Configures the fill rate if "policy" is set to "token_bucket"')
                                ->children()
                                    ->scalarNode('interval')
                                        ->info('Configures the rate interval. The value must be a number followed by "second", "minute", "hour", "day", "week" or "month" (or their plural equivalent).')
                                    ->end()
                                    ->integerNode('amount')->info('Amount of tokens to add each interval')->defaultValue(1)->end()
                                ->end()
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
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\interface_exists(LimiterInterface::class)) {
            throw new \LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
        }
        $nLimiters = \count($configs['limiters']);

        foreach ($configs['limiters'] as $name => $limiterConfig) {
            // default configuration (when used by other DI extensions)
            $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.app'];
            $limiter = $container->set('limiter.' . $name, new Definition(RateLimiterFactory::class))->public(false);

            if (null !== $limiterConfig['lock_factory']) {
                if (!$container->hasExtension(LockExtension::class)) {
                    throw new \LogicException(\sprintf('Rate limiter "%s" requires the Lock component to be installed and configured.', $name));
                }

                $limiter->arg(2, new Reference($limiterConfig['lock_factory']));
            }
            unset($limiterConfig['lock_factory']);

            $limiter->arg(1, isset($limiterConfig['storage_service']) ? new Reference($limiterConfig['storage_service']) : new Statement(CacheStorage::class, [new Reference($limiterConfig['cache_pool'])]));
            unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

            $limiterConfig['id'] = $name;
            $limiter->arg(0, $limiterConfig);

            if (1 === $nLimiters) {
                $limiter->autowire([RateLimiterFactory::class]);
            }
        }
    }
}
