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

use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\GraphQL\Schema;
use Rade\GraphQL\Types;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Simple extension to register GraphQL service.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class GraphQLExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'graphql';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('cache_dir')->end()
                ->arrayNode('schema')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
                ->arrayNode('types')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->prototype('scalar')->end()
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
        if (!\class_exists(\GraphQL\GraphQL::class)) {
            throw new \LogicException('GraphQL support cannot be enabled as the GraphQL library is not installed. Try running "composer require webonyx/graphql-php".');
        }

        $container->set('graphql.schema', new Definition(Schema::class, [$configs['schema'], $configs['cache_dir'] ?? null]));
        $container->set('graphql.types', new Definition(Types::class, \array_map(fn ($v) => new Statement($v), $configs['types'])));
    }
}
