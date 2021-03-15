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

namespace Rade\Provider;

use Biurad\Annotations\AnnotationLoader;
use Biurad\Annotations\ListenerInterface;
use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Annotations\Reader;
use Rade\API\BootableProviderInterface;
use Rade\Application;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Spiral\Attributes\AnnotationReader as DoctrineReader;
use Spiral\Attributes\AttributeReader;
use Spiral\Attributes\Composite\MergeReader;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class AnnotationServiceProvider implements ConfigurationInterface, ServiceProviderInterface, BootableProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'annotation';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getName());

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('resources')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('debug')->end()
                ->arrayNode('ignores')
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
    public function register(Container $app): void
    {
        $composer = new ClassLoader();
        $composer->setPsr4('App\\', $app->parameters['project_dir']);
        $composer->register();

        $config = $app->parameters['annotation'] ?? [];

        if (!isset($config['debug'])) {
            $config['debug'] = $app->parameters['debug'];
        }

        if (null !== $doctrine  = \class_exists(AnnotationReader::class)) {
            $app['annotation.doctrine'] = static function (Container $app) use ($config): Reader {
                $annotation = new AnnotationReader();

                foreach ($config['ignores'] as $excluded) {
                    $annotation::addGlobalIgnoredName($excluded);
                }

                if (isset($app['cache.doctrine'])) {
                    return new CachedReader($annotation, $app['cache.doctrine'], $config['debug']);
                }

                return $annotation;
            };
        }

        $app['annotation'] = static function (Container $app) use ($doctrine, $config): AnnotationLoader {
            $reader = new AttributeReader();

            if (null !== $doctrine) {
                $reader = new MergeReader([$reader, new DoctrineReader($app['annotation.doctrine'])]);
            }

            $annotation = new AnnotationLoader($reader);
            $annotation->attach(...$config['resources']);

            return $annotation;
        };

        // doctrine/annotations ^1.0 compatibility.
        if (\method_exists(AnnotationRegistry::class, 'registerLoader')) {
            AnnotationRegistry::registerUniqueLoader('class_exists');
        }

        unset($app->parameters['annotation']);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
        $listeners = $app->get(ListenerInterface::class);

        if (!\is_array($listeners)) {
            $listeners = [$listeners];
        }

        $app['annotation']->attachListener(...$listeners);

        //Build annotations ...
        $app['annotation']->build();
    }
}
