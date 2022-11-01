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

use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\ContextFactory;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoCacheExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

/**
 * Symfony component property info extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class PropertyInfoExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'property_info';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('Property info configuration')
            ->canBeEnabled()
        ->end();

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

        if (!\interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new \LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $definition = $container->set('property_info', new Definition(PropertyInfoExtractor::class, [
            new TaggedLocator('property_info.list_extractor'),
            new TaggedLocator('property_info.type_extractor'),
            new TaggedLocator('property_info.description_extractor'),
            new TaggedLocator('property_info.access_extractor'),
            new TaggedLocator('property_info.initializable_extractor'),
        ]));
        $container->set('property_info.reflection_extractor', new Definition(ReflectionExtractor::class))
            ->tag('property_info.list_extractor')
            ->tag('property_info.type_extractor')
            ->tag('property_info.access_extractor')
            ->tag('property_info.initializable_extractor')
            ->typed(ReflectionExtractor::class, PropertyReadInfoExtractorInterface::class, PropertyWriteInfoExtractorInterface::class);

        if (!empty($container->getExtensionConfig(CacheExtension::class, $container->hasExtension(FrameworkExtension::class) ? 'symfony' : null))) {
            $definition->public(false);
            $container->autowire('property_info.cache', new Definition(PropertyInfoCacheExtractor::class, [new Reference('property_info'), new Reference('cache.system')]));
        } else {
            $definition->typed();
        }

        if (\class_exists(PhpDocParser::class) && \class_exists(ContextFactory::class)) {
            $container->set('property_info.phpstan_extractor', new Definition(PhpStanExtractor::class))->tag('property_info.type_extractor');
        }

        if (\class_exists(DocBlockFactoryInterface::class)) {
            $definition = $container->set('property_info.php_doc_extractor', new Definition(PhpDocExtractor::class));
            $definition->tag('property_info.description_extractor');
            $definition->tag('property_info.type_extractor');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        if (!$container->has('property_info.constructor_extractor')) {
            return;
        }

        $container->definition('property_info.constructor_extractor')->arg(0, new TaggedLocator('property_info.constructor_extractor'));
    }
}
