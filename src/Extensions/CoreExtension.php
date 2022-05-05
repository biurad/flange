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
use Rade\Handler\EventHandler;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Event\ConsoleEvent;
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
        $this->rootDir = \rtrim(\realpath($rootDir), '\\/');
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
                ->scalarNode('var_path')->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        $dependencies = [[ConfigExtension::class, [$this->rootDir]], RoutingExtension::class];

        if (\class_exists(ConsoleEvent::class)) {
            $dependencies[] = Symfony\ConsoleExtension::class;
        }

        return $dependencies;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs = []): void
    {
        $container->parameters['project.var_dir'] = $configs['var_path'] ?? ($this->rootDir . '/var');

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
