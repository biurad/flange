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

namespace Flange\Extensions\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class RouteSection
{
    public static function getConfigNode(string $name, bool $asGroup = false): ArrayNodeDefinition
    {
        $rootNode = new ArrayNodeDefinition($name);

        if ($asGroup) {
            $rootNode->useAttributeAsKey('name');
        }
        $rootNode = $rootNode->arrayPrototype()->children();

        if (!$asGroup) {
            $rootNode
                ->scalarNode('path')->defaultValue(null)->end()
                ->scalarNode('run')->defaultValue(null)->end()
            ;
        }

        return $rootNode
            ->scalarNode('bind')->defaultValue(null)->end()
            ->scalarNode('prefix')->defaultValue(null)->end()
            ->scalarNode('namespace')->defaultValue(null)->end()
            ->booleanNode('debug')->defaultValue(null)->end()
            ->arrayNode('methods')
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn (string $v): array => [$v])
                ->end()
                ->defaultValue(!$asGroup ? ['GET'] : [])
                ->prototype('scalar')->end()
            ->end()
            ->arrayNode('scheme')
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn (string $v): array => [$v])
                ->end()
                ->prototype('scalar')->defaultValue([])->end()
            ->end()
            ->arrayNode('domain')
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn (string $v): array => [$v])
                ->end()
                ->prototype('scalar')->defaultValue([])->end()
            ->end()
            ->arrayNode('piped')
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn (string $v): array => [$v])
                ->end()
                ->prototype('scalar')->defaultValue([])->end()
            ->end()
            ->arrayNode('placeholders')
                ->normalizeKeys(false)
                ->defaultValue([])
                ->beforeNormalization()
                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                    ->thenInvalid('Expected patterns values to be an associate array of string keys mapping to mixed values.')
                ->end()
                ->prototype('variable')->end()
            ->end()
            ->arrayNode('defaults')
                ->normalizeKeys(false)
                ->defaultValue([])
                ->beforeNormalization()
                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                    ->thenInvalid('Expected defaults values to be an associate array of string keys mapping to mixed values.')
                ->end()
                ->prototype('variable')->end()
            ->end()
            ->arrayNode('arguments')
                ->normalizeKeys(false)
                ->defaultValue([])
                ->beforeNormalization()
                    ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                    ->thenInvalid('Expected arguments values to be an associate array of string keys mapping to mixed values.')
                ->end()
                ->prototype('variable')->end()
            ->end()
        ->end();
    }
}
