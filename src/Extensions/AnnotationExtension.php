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

namespace Rade\DI\Extensions;

use Biurad\Annotations\AnnotationLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\PsrCachedReader;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Parameter;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Spiral\Attributes\Composite\MergeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\Internal\{NativeAttributeReader, DoctrineAnnotationReader};
use Spiral\Attributes\Internal\Instantiator\NamedArgumentsInstantiator;
use Spiral\Attributes\Psr6CachedReader;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Biurad Annotation Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class AnnotationExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'annotation';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifTrue(fn ($v) => \is_array($v) && \array_is_list($v))
                ->then(fn ($v) => ['resources' => $v])
            ->end()
            ->children()
                ->scalarNode('use_reader')->end()
                ->booleanNode('merge_readers')->defaultTrue()->end()
                ->variableNode('class_loader')
                    ->beforeNormalization()
                        ->ifTrue(fn ($v): bool => !\is_callable($v))
                        ->thenInvalid('Class loader must be a class invoker or a callable')
                    ->end()
                ->end()
                ->arrayNode('listeners')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('resources')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('doctrine_ignores')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue(['persistent', 'serializationVersion', 'inject'])
                ->end()
                ->scalarNode('cache')->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        if (!\class_exists(AnnotationLoader::class)) {
            throw new \LogicException('Annotations/Attributes support cannot be enabled as the Annotation component is not installed. Try running "composer require biurad/annotations".');
        }

        $loader = $container->autowire('annotation.loader', new Definition(AnnotationLoader::class, [new Reference('?annotation.reader')]));

        if (isset($configs['class_loader'])) {
            $loader->arg(1, $configs['class_loader']);
        }

        if (!empty($resources = $configs['resources'] ?? [])) {
            $loader->bind('resource', [$resources]);
        }

        if ($useAttribute = $configs['use_reader'] ?? false) {
            if (!\class_exists($attribute = NativeAttributeReader::class)) {
                throw new \LogicException('Annotations/Attributes support cannot be enabled as the Spiral Attribute component is not installed. Try running "composer require spiral/attributes".');
            }

            $attributeArgs = [new Statement($attribute, [new Statement(NamedArgumentsInstantiator::class)])];
            $hasCache = $container->hasExtension(Symfony\CacheExtension::class) || $container->hasExtension(Symfony\FrameworkExtension::class);

            if (\is_string($useAttribute)) {
                $attribute = $configs['merge_readers'] ? MergeReader::class : SelectiveReader::class;

                if (\in_array($useAttribute, [$doctrineService = AnnotationReader::class, 'doctrine'], true)) {
                    if (!\class_exists($doctrineService)) {
                        throw new \LogicException('Annotations support cannot be enabled as the Doctrine component is not installed. Try running "composer require doctrine/annotations".');
                    }

                    if ($hasCache) {
                        $doctrineArgs = [new Statement($doctrineService), new Reference('cache.system'), new Parameter('debug')];
                        $doctrineService = PsrCachedReader::class;
                    }

                    $doctrineService = $container->autowire('annotation.doctrine', new Definition($doctrineService, $doctrineArgs ?? []));
                    $attributeArgs[] = new Statement(DoctrineAnnotationReader::class, [new Reference('annotation.doctrine')]);

                    foreach ($configs['doctrine_ignores'] as $doctrineExclude) {
                        $doctrineService->call(new Statement(AnnotationReader::class . '::addGlobalIgnoredName', [$doctrineExclude]));
                    }

                    // doctrine/annotations ^1.0 compatibility.
                    if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
                        $doctrineService->call(new Statement(AnnotationRegistry::class . '::registerUniqueLoader', ['class_exists']));
                    }
                } else {
                    $attributeArgs[] = $container->has($useAttribute) ? new Reference($useAttribute) : new Statement($useAttribute);
                }

                if ($hasCache) {
                    $attributeArgs = [new Statement($attribute, [$attributeArgs]), new Reference('cache.system')];
                    $attribute = Psr6CachedReader::class;
                }
            }
            $container->autowire('annotation.reader', new Definition($attribute, $attributeArgs));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        if (empty($listeners = $container->tagged('annotation.listener'))) {
            return;
        }
        $loader = $container->definition('annotation.loader');

        foreach ($listeners as $listener => $value) {
            $value = \is_string($value) ? $value : null;

            if ($loader instanceof AnnotationLoader) {
                $loader->listener($container->get($listener), $value);
            } else {
                $loader->bind('listener', [$container->has($listener) ? new Reference($listener) : new Statement($listener), $value]);
            }
        }
    }
}
