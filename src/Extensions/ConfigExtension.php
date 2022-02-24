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

use Rade\AppBuilder;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Loader\{ClosureLoader, DirectoryLoader, GlobFileLoader, PhpFileLoader, YamlFileLoader};
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;

/**
 * Symfony's Config component extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ConfigExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    private string $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = \rtrim($rootDir, \DIRECTORY_SEPARATOR);
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'config';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->beforeNormalization()->ifString()->then(fn ($v) =>  ['paths' => [$v]])->end()
            ->children()
                ->scalarNode('locale')->defaultValue('en')->end()
                ->booleanNode('debug')->defaultNull()->end()
                ->booleanNode('auto_configure')->defaultFalse()->end()
                ->arrayNode('paths')
                    ->prototype('scalar')->isRequired()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('loaders')
                    ->prototype('scalar')->defaultValue([])->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs = []): void
    {
        // The default configs ...
        $container->parameters['default_locale'] = $configs['locale'] ?? 'en';
        $container->parameters['project_dir'] = $this->rootDir;

        if (isset($configs['debug'])) {
            $container->parameters['debug'] = $configs['debug'];
        }

        // Configurations ...
        $configLoaders = [...$configs['loaders'], PhpFileLoader::class, ClosureLoader::class, DirectoryLoader::class, GlobFileLoader::class];

        if (\class_exists('Symfony\Component\Yaml\Yaml')) {
            $configLoaders[] = YamlFileLoader::class;
        }

        if ($container instanceof AppBuilder) {
            $builderResolver = new LoaderResolver();
            $fileLocator = new FileLocator(\array_map([$container, 'parameter'], $configs['paths']));

            foreach ($configLoaders as $builderLoader) {
                $builderResolver->addLoader(new $builderLoader($container, $fileLocator));
            }

            $container->set('config.builder.loader_resolver', $builderResolver);
        }

        foreach ($configLoaders as &$configLoader) {
            $configLoader = new Statement($configLoader);
        }

        $container->autowire('config.loader_locator', new Definition(FileLocator::class, [\array_map([$container, 'parameter'], $configs['paths'])]));
        $container->set('config.loader_resolver', new Definition(LoaderResolver::class, [$configLoaders]));
        $container->autowire('config_cache_factory', new Definition(ConfigCacheFactory::class, ['%debug%']));

        if ($configs['auto_configure'] && $container instanceof \Rade\KernelInterface) {
            foreach ($configs['paths'] as $path) {
                $container->load($path, 'directory');
            }
        }
    }
}
