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

use Biurad\Annotations\AnnotationLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\PsrCachedReader;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Parameter;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Spiral\Attributes\Composite\MergeReader;
use Spiral\Attributes\Composite\SelectiveReader;
use Spiral\Attributes\Internal\Instantiator\NamedArgumentsInstantiator;
use Spiral\Attributes\Internal\{DoctrineAnnotationReader, NativeAttributeReader};
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
            ->fixXmlConfig('use_reader')
            ->children()
                ->arrayNode('use_readers')
                    ->beforeNormalization()
                        ->ifString()->then(fn ($v) => [$v])
                        ->ifTrue(fn ($v) => true === $v)->then(fn ($v) => [])
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
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
                ->scalarNode('cache')->defaultNull()->end()
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

        foreach ($configs['listeners'] ?? [] as $listener) {
            $loader->bind('listener', $container->has($listener) ? new Reference($listener) : [new Statement($listener), $listener]);
        }

        if ($useAttributes = $configs['use_readers'] ?? []) {
            if (!\class_exists($attribute = NativeAttributeReader::class)) {
                throw new \LogicException('Annotations/Attributes support cannot be enabled as the Spiral Attribute component is not installed. Try running "composer require spiral/attributes".');
            }

            $attributeArgs = [new Statement($attribute, [new Statement(NamedArgumentsInstantiator::class)])];
            $attribute = $configs['merge_readers'] ? MergeReader::class : SelectiveReader::class;
            $hasCache = $configs['cache'] ??
                !empty($container->getExtensionConfig(Symfony\CacheExtension::class, $container->hasExtension(Symfony\FrameworkExtension::class) ? 'symfony' : null)) ? 'cache.system' : null;

            foreach ($useAttributes as $useAttribute) {
                if (!\in_array($useAttribute, [$doctrineService = AnnotationReader::class, 'doctrine'], true)) {
                    $attributeArgs[] = $container->has($useAttribute) ? new Reference($useAttribute) : new Statement($useAttribute);
                    continue;
                }

                if (!\class_exists($doctrineService)) {
                    throw new \LogicException('Annotations support cannot be enabled as the Doctrine component is not installed. Try running "composer require doctrine/annotations".');
                }

                if ($hasCache) {
                    $doctrineArgs = [new Statement($doctrineService), new Reference($hasCache), new Parameter('debug')];
                    $doctrineService = PsrCachedReader::class;
                }

                $doctrineService = $container->autowire('annotation.doctrine', new Definition($doctrineService, $doctrineArgs ?? []));
                $attributeArgs[] = new Statement(DoctrineAnnotationReader::class, [new Reference('annotation.doctrine')]);

                foreach ($configs['doctrine_ignores'] as $doctrineExclude) {
                    $doctrineService->call(new Statement(AnnotationReader::class.'::addGlobalIgnoredName', [$doctrineExclude]));
                }

                // doctrine/annotations ^1.0 compatibility.
                if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
                    $doctrineService->call(new Statement(AnnotationRegistry::class.'::registerUniqueLoader', ['class_exists']));
                }
            }

            if ($hasCache) {
                $attributeArgs = [new Statement($attribute, [$attributeArgs]), new Reference($hasCache)];
                $attribute = Psr6CachedReader::class;
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
            $loader->bind('listener', [$container->has($listener) ? new Reference($listener) : new Statement($listener), $value]);
        }
    }
}
