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
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

/**
 * Symfony component property access extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class PropertyAccessExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'property_access';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->info('Property access configuration')
            ->canBeEnabled()
            ->children()
                ->booleanNode('magic_call')->defaultFalse()->end()
                ->booleanNode('magic_get')->defaultTrue()->end()
                ->booleanNode('magic_set')->defaultTrue()->end()
                ->booleanNode('throw_exception_on_invalid_index')->defaultFalse()->end()
                ->booleanNode('throw_exception_on_invalid_property_path')->defaultTrue()->end()
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

        if (!\class_exists(PropertyAccessor::class)) {
            throw new \LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $magicMethods = PropertyAccessor::DISALLOW_MAGIC_METHODS;
        $magicMethods |= $configs['magic_call'] ? PropertyAccessor::MAGIC_CALL : 0;
        $magicMethods |= $configs['magic_get'] ? PropertyAccessor::MAGIC_GET : 0;
        $magicMethods |= $configs['magic_set'] ? PropertyAccessor::MAGIC_SET : 0;

        $throw = PropertyAccessor::DO_NOT_THROW;
        $throw |= $configs['throw_exception_on_invalid_index'] ? PropertyAccessor::THROW_ON_INVALID_INDEX : 0;
        $throw |= $configs['throw_exception_on_invalid_property_path'] ? PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH : 0;

        $container->set('property_accessor', new Definition(PropertyAccessor::class, [
            $magicMethods,
            $throw,
            new Reference('cache.system'),
            new Reference('?' . PropertyReadInfoExtractorInterface::class),
            new Reference('?' . PropertyWriteInfoExtractorInterface::class),
        ]));
    }
}
