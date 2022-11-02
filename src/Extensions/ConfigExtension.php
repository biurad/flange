<?php declare(strict_types=1);

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

use Flange\AppBuilder;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Parameter;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Loader\{ClosureLoader, DirectoryLoader, GlobFileLoader, PhpFileLoader, YamlFileLoader};
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;

/**
 * Required Config component extension.
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
            ->canBeEnabled()
            ->beforeNormalization()->ifString()->then(fn ($v) => ['paths' => [$v]])->end()
            ->children()
                ->scalarNode('locale')->defaultValue('en')->end()
                ->booleanNode('auto_configure')->defaultFalse()->end()
                ->arrayNode('paths')
                    ->prototype('scalar')->isRequired()->cannotBeEmpty()->end()
                ->end()
                ->scalarNode('var_path')->defaultValue('%project_dir%/var')->end()
                ->scalarNode('cache_path')->defaultValue('%project.var_dir%/cache')->end()
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
    public function register(Container $container, array $configs = []): void
    {
        // The default configs ...
        $container->parameters['default_locale'] = $configs['locale'] ?? 'en';
        self::setPath($container, $this->rootDir, $configs['var_path'] ?? null, $configs['cache_path'] ?? null);

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

            $container->parameters['config.builder.loader_resolver'] = $builderResolver;
        }

        foreach ($configLoaders as &$configLoader) {
            $configLoader = new Statement($configLoader);
        }

        $container->autowire('config.loader_locator', new Definition(FileLocator::class, [\array_map([$container, 'parameter'], $configs['paths'])]));
        $container->set('config.loader_resolver', new Definition(LoaderResolver::class, [$configLoaders]));
        $container->autowire('config_cache_factory', new Definition(ConfigCacheFactory::class, [new Parameter('debug')]));

        if ($configs['auto_configure'] && $container instanceof \Flange\KernelInterface) {
            foreach ($configs['paths'] as $path) {
                $container->load($path, 'directory');
            }
        }
    }

    public static function setPath(Container $container, string $rootDir, string $varDir = null, string $cacheDir = null): void
    {
        $container->parameters['project_dir'] = $rootDir;
        $container->parameters['project.var_dir'] = $container->parameter($varDir ?? $rootDir.'/var');
        $container->parameters['project.cache_dir'] = $container->parameter($cacheDir ?? '%project.var_dir%/cache');
    }
}
