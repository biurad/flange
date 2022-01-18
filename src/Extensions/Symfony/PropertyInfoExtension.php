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
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Resolver;
use Rade\DI\Services\AliasedInterface;
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
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new \LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $definition = $container->set('property_info', new Definition(PropertyInfoExtractor::class));
        $container->set('property_info.reflection_extractor', new Definition(ReflectionExtractor::class))
            ->tag('property_info.list_extractor')
            ->tag('property_info.type_extractor')
            ->tag('property_info.access_extractor')
            ->tag('property_info.initializable_extractor')
            ->autowire([PropertyReadInfoExtractorInterface::class, PropertyWriteInfoExtractorInterface::class]);

        if ($container->hasExtension(CacheExtension::class)) {
            $definition->public(false);
            $container->autowire('property_info.cache', new Definition(PropertyInfoCacheExtractor::class, [new Reference('property_info'), new Reference('cache.system')]));
        } else {
            $container->type('property_info', Resolver::autowireService($definition->getEntity()));
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
    public function boot(AbstractContainer $container): void
    {
        $definition = $container->definition('property_info');
        $defCallable = static fn ($v) => $container->has($v) ? new Reference($v) : new Statement($v);

        $listExtractors = $container->findBy('property_info.list_extractor', $defCallable);
        $definition->arg(0, $listExtractors);

        $typeExtractors = $container->findBy('property_info.type_extractor', $defCallable);
        $definition->arg(1, $typeExtractors);

        $descriptionExtractors = $container->findBy('property_info.description_extractor', $defCallable);
        $definition->arg(2, $descriptionExtractors);

        $accessExtractors = $container->findBy('property_info.access_extractor', $defCallable);
        $definition->arg(3, $accessExtractors);

        $initializableExtractors = $container->findBy('property_info.initializable_extractor', $defCallable);
        $definition->arg(4, $initializableExtractors);

        if (!$container->has('property_info.constructor_extractor')) {
            return;
        }

        $definition = $container->definition('property_info.constructor_extractor');
        $listExtractors = $container->findBy('property_info.constructor_extractor', $defCallable);
        $definition->arg(0, $listExtractors);
    }
}