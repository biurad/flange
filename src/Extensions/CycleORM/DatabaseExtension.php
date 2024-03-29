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

namespace Flange\Extensions\CycleORM;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\FileRepository;
use Cycle\Migrations\Migrator;
use Flange\Commands\CycleORM\DatabaseListCommand;
use Flange\Commands\CycleORM\DatabaseMigrateCommand;
use Flange\Commands\CycleORM\DatabaseTableCommand;
use Flange\Database\Cycle\Config;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Cycle Database Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class DatabaseExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'cycle_dbal';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('default')->end()
                ->arrayNode('aliases')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('databases')
                    ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(fn ($v) => ['connection' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('connection')->end()
                            ->scalarNode('tablePrefix')->end()
                            ->scalarNode('readConnection')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('connections')
                   ->arrayPrototype()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(fn ($v) => ['class' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('driver')->isRequired()->end()
                            ->scalarNode('dsn')->isRequired()->end()
                            ->booleanNode('reconnect')->defaultTrue()->end()
                            ->scalarNode('timezone')->defaultValue('UTC')->end()
                            ->scalarNode('username')->defaultNull()->end()
                            ->scalarNode('password')->defaultNull()->end()
                            ->scalarNode('queryCache')->defaultTrue()->end()
                            ->scalarNode('readonlySchema')->defaultFalse()->end()
                            ->scalarNode('readonly')->defaultFalse()->end()
                            ->variableNode('options')
                                ->beforeNormalization()
                                    ->ifTrue(fn ($v) => !\is_array($v))
                                    ->thenInvalid('Options must be an array supported by PDO')
                                    ->always(fn ($v) => \array_merge(...$v))
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('migrations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('directory')->end()
                        ->scalarNode('table')->defaultValue('migrations')->end()
                        ->booleanNode('safe')->defaultTrue()->end()
                        ->scalarNode('namespace')->defaultValue('Migration')->end()
                    ->end()
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
        if (!\interface_exists(DatabaseProviderInterface::class)) {
            throw new \LogicException('Cycle Database support cannot be enabled as the Database component is not installed. Try running "composer require cycle/database".');
        }

        if (!empty($configs['connections'])) {
            $connections = [];

            foreach ($configs['connections'] as $name => $con) {
                if (!\str_starts_with($con['dsn'], $name)) {
                    $con['dsn'] = $name.':'.$con['dsn'];
                }

                $connections[$name] = new Statement(
                    Config\DriverConfig::class,
                    [
                        new Statement(Config\Connection::class, [$con['dsn'], $con['username'], $con['password'], $con['options'] ?? []]),
                        \class_exists($con['driver']) ? $con['driver'] : 'Cycle\\Database\\Driver\\'.$con['driver'].'\\'.$con['driver'].'Driver',
                        $con['reconnect'],
                        $con['timezone'],
                        $con['queryCache'],
                        $con['readonlySchema'],
                        $con['readonly'],
                    ]
                );
            }

            $configs['connections'] = $connections;
        }

        $migrateConfig = $configs['migrations'] ?? [];

        if (\class_exists(Migrator::class)) {
            $container->autowire('cycle.database.migrator', new Definition(Migrator::class, [
                $migrateConfig = $container->call(MigrationConfig::class, [$migrateConfig]),
                new Reference('cycle.database.factory'),
                new Statement(FileRepository::class, [$migrateConfig]),
            ]));
        }
        unset($configs['migrations']);

        $container->autowire('cycle.database.config', new Definition(DatabaseConfig::class, [$configs]))->public(false);
        $container->autowire('cycle.database.factory', new Definition(DatabaseManager::class, [new Reference('cycle.database.config')]));
        $container->autowire('cycle.database', new Definition([new Reference('cycle.database.factory'), 'database']));

        if ($container->has('console')) {
            $container->types([DatabaseTableCommand::class => Command::class, DatabaseListCommand::class => Command::class]);
            $container->set('console.command.cycle.database.table', new Definition(DatabaseTableCommand::class))->public(false)->tag('console.command');
            $container->set('console.command.cycle.database.list', new Definition(DatabaseListCommand::class))->public(false)->tag('console.command');

            if (!\is_array($migrateConfig)) {
                $container->set('console.command.cycle.database.migrate', new Definition(DatabaseMigrateCommand::class))->public(false)->tag('console.command');
            }
        }
    }
}
