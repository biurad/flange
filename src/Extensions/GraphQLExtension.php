<?php

namespace Rade\DI\Extensions;

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Services\AliasedInterface;
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
    public function register(AbstractContainer $container, array $configs): void
    {
        $container->set('graphql.schema', new Definition(Schema::class, [$configs['schema'], $configs['cache_dir'] ?? null]));
        $container->set('graphql.types', new Definition(Types::class, \array_map(fn ($v) => new Statement($v), $configs['types'])));
    }
}

