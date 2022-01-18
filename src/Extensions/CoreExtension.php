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

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Services\AliasedInterface;
use Rade\DI\Services\DependenciesInterface;
use Rade\Handler\EventHandler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Rade Core Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CoreExtension implements AliasedInterface, ConfigurationInterface, DependenciesInterface, ExtensionInterface
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
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('events_dispatcher')->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [[ConfigExtension::class, [$this->rootDir]], RoutingExtension::class];
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs = []): void
    {
        if (!$container->has('events.dispatcher')) {
            $eventsDispatcher = $configs['events_dispatcher'] ?? EventHandler::class;

            if ($container->has($eventsDispatcher)) {
                $container->alias('events.dispatcher', $eventsDispatcher);
            } else {
                $container->autowire('events.dispatcher', new Definition($eventsDispatcher));
            }
        }

        if (!$container->has('request_stack')) {
            $container->autowire('request_stack', new Definition(RequestStack::class));
        }
    }
}