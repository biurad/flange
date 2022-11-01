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
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class HttpCorsSection
{
    public static function getConfigNode(): ArrayNodeDefinition
    {
        $rootNode = new ArrayNodeDefinition('cors');

        $rootNode
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('allow_origin')->defaultFalse()->end()
                ->append(self::getAllowCredentials())
                ->append(self::getAllowHeaders())
                ->append(self::getAllowMethods())
                ->append(self::getExposeHeaders())
                ->append(self::getMaxAge())
                ->append(self::getHosts())
                ->append(self::getOriginRegex())
                ->arrayNode('allow_paths')
                    ->useAttributeAsKey('path')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->append(self::getAllowCredentials())
                        ->append(self::getAllowOrigin())
                        ->append(self::getAllowHeaders())
                        ->append(self::getAllowMethods())
                        ->append(self::getExposeHeaders())
                        ->append(self::getMaxAge())
                        ->append(self::getHosts())
                        ->append(self::getOriginRegex())
                    ->end()
                ->end()
            ->end()
        ;

        return $rootNode;
    }

    private static function getAllowCredentials(): BooleanNodeDefinition
    {
        $node = new BooleanNodeDefinition('allow_credentials');
        $node->defaultFalse();

        return $node;
    }

    private static function getAllowOrigin(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('allow_origin');

        $node
            ->beforeNormalization()
                ->always(function ($v) {
                    if ('*' === $v) {
                        return ['*'];
                    }

                    return $v;
                })
            ->end()
            ->prototype('scalar')->end()
        ;

        return $node;
    }

    private static function getAllowHeaders(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('allow_headers');

        $node
            ->beforeNormalization()
                ->ifString()
                ->then(fn ($v) => [$v])
            ->end()
            ->prototype('scalar')->end();

        return $node;
    }

    private static function getAllowMethods(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('allow_methods');

        $node
            ->beforeNormalization()
                ->ifString()
                ->then(fn ($v) => [$v])
            ->end()
            ->prototype('scalar')->end();

        return $node;
    }

    private static function getExposeHeaders(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('expose_headers');

        $node
            ->beforeNormalization()
                ->ifString()
                ->then(fn ($v) => [$v])
            ->end()
            ->prototype('scalar')->end();

        return $node;
    }

    private static function getMaxAge(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('max_age');

        $node
            ->defaultValue(0)
            ->validate()
                ->ifTrue(fn ($v) => !\is_numeric($v))
                ->thenInvalid('max_age must be an integer (seconds)')
            ->end()
        ;

        return $node;
    }

    private static function getHosts(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('hosts');

        $node
            ->beforeNormalization()
                ->ifString()
                ->then(fn ($v) => [$v])
            ->end()
            ->prototype('scalar')->end();

        return $node;
    }

    private static function getOriginRegex(): BooleanNodeDefinition
    {
        $node = new BooleanNodeDefinition('origin_regex');
        $node->defaultFalse();

        return $node;
    }
}
