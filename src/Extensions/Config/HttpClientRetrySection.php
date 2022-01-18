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

namespace Rade\DI\Extensions\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpClientRetrySection
{
    public static function getConfigNode(): ArrayNodeDefinition
    {
        $root = new NodeBuilder();

        return $root
            ->arrayNode('retry_failed')
                ->fixXmlConfig('http_code')
                ->canBeEnabled()
                ->addDefaultsIfNotSet()
                ->beforeNormalization()
                    ->always(function ($v) {
                        if (isset($v['retry_strategy']) && (isset($v['http_codes']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']) || isset($v['jitter']))) {
                            throw new \InvalidArgumentException('The "retry_strategy" option cannot be used along with the "http_codes", "delay", "multiplier", "max_delay" or "jitter" options.');
                        }

                        return $v;
                    })
                ->end()
                ->children()
                    ->scalarNode('retry_strategy')->defaultNull()->info('service id to override the retry strategy')->end()
                    ->arrayNode('http_codes')
                        ->performNoDeepMerging()
                        ->beforeNormalization()
                            ->ifArray()
                            ->then(static function ($v) {
                                $list = [];

                                foreach ($v as $key => $val) {
                                    if (\is_numeric($val)) {
                                        $list[] = ['code' => $val];
                                    } elseif (\is_array($val)) {
                                        if (isset($val['code']) || isset($val['methods'])) {
                                            $list[] = $val;
                                        } else {
                                            $list[] = ['code' => $key, 'methods' => $val];
                                        }
                                    } elseif (true === $val || null === $val) {
                                        $list[] = ['code' => $key];
                                    }
                                }

                                return $list;
                            })
                        ->end()
                        ->useAttributeAsKey('code')
                        ->arrayPrototype()
                            ->fixXmlConfig('method')
                            ->children()
                                ->integerNode('code')->end()
                                ->arrayNode('methods')
                                    ->beforeNormalization()
                                    ->ifArray()
                                        ->then(function ($v) {
                                            return \array_map('strtoupper', $v);
                                        })
                                    ->end()
                                    ->prototype('scalar')->end()
                                    ->info('A list of HTTP methods that triggers a retry for this status code. When empty, all methods are retried')
                                ->end()
                            ->end()
                        ->end()
                        ->info('A list of HTTP status code that triggers a retry')
                    ->end()
                    ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
                    ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used)')->end()
                    ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: delay * (multiple ^ retries)')->end()
                    ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite)')->end()
                    ->floatNode('jitter')->defaultValue(0.1)->min(0)->max(1)->info('Randomness in percent (between 0 and 1) to apply to the delay')->end()
                ->end()
            ;
    }
}
