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

namespace Rade\Provider\HttpGalaxy;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CorsConfiguration
{
    /**
     * {@inheritDoc}
     */
    public static function getConfigNode(): ArrayNodeDefinition
    {
        $rootNode = new ArrayNodeDefinition('cors');

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->append(self::getAllowCredentials())
                ->append(self::getAllowOrigin())
                ->append(self::getAllowHeaders())
                ->append(self::getAllowMethods())
                ->append(self::getExposeHeaders())
                ->append(self::getMaxAge())
                ->append(self::getHosts())
                ->append(self::getOriginRegex())
                ->arrayNode('allow_paths')
                    ->useAttributeAsKey('path')
                    ->normalizeKeys(false)
                    ->prototype('array')
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
                    if ($v === '*') {
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
                ->always(function ($v) {
                    if ($v === '*') {
                        return ['*'];
                    }

                    return $v;
                })
            ->end()
            ->prototype('scalar')->end();

        return $node;
    }

    private static function getAllowMethods(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('allow_methods');

        $node->prototype('scalar')->end();

        return $node;
    }

    private static function getExposeHeaders(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('expose_headers');

        $node->prototype('scalar')->end();

        return $node;
    }

    private static function getMaxAge(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition('max_age');

        $node
            ->defaultValue(0)
            ->validate()
                ->ifTrue(fn ($v) => !is_numeric($v))
                ->thenInvalid('max_age must be an integer (seconds)')
            ->end()
        ;

        return $node;
    }

    private static function getHosts(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('hosts');

        $node->prototype('scalar')->end();

        return $node;
    }

    private static function getOriginRegex(): BooleanNodeDefinition
    {
        $node = new BooleanNodeDefinition('origin_regex');
        $node->defaultFalse();

        return $node;
    }
}
