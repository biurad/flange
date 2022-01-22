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
use Biurad\Annotations\ListenerInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\PsrCachedReader;
use Flight\Routing\Annotation\Route;
use Flight\Routing\RouteCollection;
use Psr\Cache\CacheItemPoolInterface;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Services\AliasedInterface;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;
use Spiral\Attributes\Internal\{FallbackAttributeReader, NativeAttributeReader, DoctrineAnnotationReader};
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
            ->children()
                ->scalarNode('use_reader')->end()
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
    public function register(AbstractContainer $container, array $configs = []): void
    {
        if (!\class_exists(AnnotationLoader::class)) {
            throw new \LogicException('Annotations/Attributes support cannot be enabled as the Annotation component is not installed. Try running "composer require biurad/annotations".');
        }

        $loader = $container->autowire('annotation.loader', new Definition(AnnotationLoader::class));

        if (isset($configs['class_loader'])) {
            $loader->arg(1, $configs['class_loader']);
        }

        if (!empty($resources = $configs['resources'] ?? [])) {
            $loader->bind('resource', [$resources]);
        }

        if ($useAttribute = $configs['use_reader'] ?? false) {
            $attribute = 8 === \PHP_MAJOR_VERSION ? NativeAttributeReader::class : FallbackAttributeReader::class;
            $hasCache = ($symfony = $container->hasExtension(Symfony\CacheExtension::class) || $container->hasExtension(Symfony\FrameworkExtension::class)) || $container->typed(CacheItemPoolInterface::class);

            if (!\in_array($useAttribute, [AttributeReader::class, 'attribute'], true)) {
                $attributeArgs = [[new Statement($attribute, [new Statement(NamedArgumentsInstantiator::class)])]];

                if (\in_array($useAttribute, [DoctrineReader::class, AnnotationReader::class, 'doctrine'], true) && \class_exists(AnnotationReader::class)) {
                    $doctrineService = AnnotationReader::class;

                    if ($hasCache) {
                        $doctrineArgs = [new Statement($doctrineService), 2 => '%debug%'];
                        $doctrineService = PsrCachedReader::class;

                        if ($symfony) {
                            $doctrineArgs[1] = new Reference('cache.system');
                        }
                    }

                    $doctrineService = $container->autowire('annotation.doctrine', new Definition($doctrineService, $doctrineArgs ?? []));

                    foreach ($configs['doctrine_ignores'] as $doctrineExclude) {
                        $doctrineService->call(new Statement(AnnotationReader::class . '::addGlobalIgnoredName', [$doctrineExclude]));
                    }

                    // doctrine/annotations ^1.0 compatibility.
                    if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
                        $doctrineService->call(new Statement(AnnotationRegistry::class . '::registerUniqueLoader', ['class_exists']));
                    }

                    $attributeArgs[0][] = new Statement(DoctrineAnnotationReader::class);
                } else {
                    $attributeArgs[0][] = $container->has($useAttribute) ? new Reference($useAttribute) : new Statement($useAttribute);
                }

                $attribute = MergeReader::class;

                if ($hasCache) {
                    $attributeArgs[0] = new Statement($attribute, $attributeArgs);
                    $attribute = Psr6CachedReader::class;

                    if ($symfony) {
                        $attributeArgs[1] = new Reference('cache.system');
                    }
                }
            }

            $container->autowire('annotation.reader', new Definition($attribute, $attributeArgs ?? []));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $loader = $container->definition('annotation.loader');
        $listeners = $container->findBy(ListenerInterface::class, fn (string $listenerId) => new Reference($listenerId));

        if ($loader instanceof AnnotationLoader) {
            $loader->listener(...$container->getResolver()->resolveArguments($listeners));
        } else {
            $loader->bind('listener', [$listeners]);
        }

        if ($container->hasExtension(RoutingExtension::class)) {
            $container->set('router.annotation.collection', new Definition([new Reference('annotation.loader'), 'load'], [Route::class, false]))
                ->autowire([RouteCollection::class]);
        }
    }
}
