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

use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;

class ConfigServiceProvider implements ConfigurationInterface, ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'config';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getName());

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('locale')->defaultValue('en')->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->arrayNode('paths')
                    ->defaultValue([])
                    ->prototype('scalar')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app): void
    {
        $config = $app->parameters['config'] ?? [];

        if ([] !== $config['paths']) {
            $config['paths'] = \array_map(static fn (string $path) => $app->parameters['project_dir'] . $path, $config['paths']);
        }

        $app['config.loader_file'] = new FileLocator($config['paths']);
        $app['config.loader_resolver'] = new LoaderResolver();
        $app['config.loader'] = $app->lazy(DelegatingLoader::class);

        // The default debug environment.
        $app->parameters['debug']  = $config['debug'];
        $app->parameters['locale'] = $config['locale'];

        unset($app->parameters['config']); // Remove default config from DI.
    }
}
