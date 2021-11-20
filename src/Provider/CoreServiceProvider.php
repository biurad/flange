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

use Rade\DI\AbstractContainer;
use Rade\DI\Services\AliasedInterface;
use Rade\DI\Services\DependenciesInterface;
use Rade\DI\Services\ServiceProviderInterface;
use Rade\Handler\EventHandler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function Rade\DI\Loader\service;

/**
 * Rade Core Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CoreServiceProvider implements AliasedInterface, ConfigurationInterface, DependenciesInterface, ServiceProviderInterface
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
        return 'core';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getAlias());

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('events_dispatcher')->defaultValue(EventHandler::class)->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [
            [ConfigServiceProvider::class, [$this->rootDir]],
            RoutingServiceProvider::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs = []): void
    {
        if (!$container->has('events.dispatcher')) {
            if ($container->has($configs['events_dispatcher'])) {
                $container->alias('events.dispatcher', $configs['events_dispatcher']);
            } else {
                $container->autowire('events.dispatcher', service($configs['events_dispatcher'] ?? EventHandler::class));
            }
        }
    }
}
