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
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Services\AliasedInterface;
use Spiral\Attributes\AnnotationReader as DoctrineReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;
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
        $loader = $container->autowire('annotation.loader', new Definition(AnnotationLoader::class));

        if (isset($configs['class_loader'])) {
            $loader->arg(1, $configs['class_loader']);
        }

        if (!empty($resources = $configs['resources'] ?? [])) {
            $loader->bind('resource', [$resources]);
        }

        if (isset($configs['use_reader'])) {
            $loader->arg(0, $container->has($configs['use_reader']) ? new Reference($configs['use_reader']) : new Statement($configs['use_reader']));
            $attribute = AttributeReader::class;

            if (null !== \class_exists(AnnotationReader::class)) {
                $doctrineService = AnnotationReader::class;

                if ($container->has('psr6.cache')) {
                    $doctrineService = PsrCachedReader::class;
                    $doctrineArgs = [new Statement($doctrineService), $container->get('psr6.cache'), $container->parameter('debug')];
                }

                $doctrineService = $container->autowire('annotation.doctrine', new Definition($doctrineService, $doctrineArgs ?? []));

                foreach ($configs['doctrine_ignores'] as $doctrineExclude) {
                    $doctrineService->bind('addGlobalIgnoredName', $doctrineExclude);
                }

                //$attribute = new MergeReader([$reader, new DoctrineReader($app['annotation.doctrine'])]);
                $attributeArgs = [[new Statement($attribute), new Statement(DoctrineReader::class)]];
                $attribute = MergeReader::class;

                // doctrine/annotations ^1.0 compatibility.
                if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
                    AnnotationRegistry::registerUniqueLoader('class_exists');
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
