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

namespace Rade\DI\Extensions\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Doctrine database extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class DatabaseExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /** @var bool */
    private $debug;

    /** @param bool $debug Whether to use the debug mode */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    public static function createConnectionTypes(Connection $connection, array $types): void
    {
        $platform = $connection->getDatabasePlatform();

        foreach ($types as $name => $type) {
            if (!Type::hasType($name)) {
                Type::addType($name, $type['class']);
            } else {
                Type::overrideType($name, $type['class']);
            }
            $platform->registerDoctrineTypeMapping($type['dbType'], $name);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'database';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $connectionNode = $treeBuilder->getRootNode()
            ->fixXmlConfig('type')
            ->fixXmlConfig('connection')
            ->children()
                ->scalarNode('default_connection')->defaultValue('default')->end()
                ->arrayNode('types')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('class')->isRequired()->end()
                            ->scalarNode('dbType')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->arrayPrototype();
        $this->configureDbalDriverNode($connectionNode);

        $connectionNode
            ->fixXmlConfig('option')
            ->fixXmlConfig('mapping_type')
            ->fixXmlConfig('slave')
            ->fixXmlConfig('replica')
            ->fixXmlConfig('shard')
            ->fixXmlConfig('default_table_option')
            ->children()
                ->scalarNode('driver')->defaultValue('pdo_mysql')->end()
                ->scalarNode('platform_service')->end()
                ->booleanNode('auto_commit')->end()
                ->scalarNode('schema_filter')->end()
                ->booleanNode('logging')->defaultValue($this->debug)->end()
                ->booleanNode('profiling')->defaultValue($this->debug)->end()
                ->booleanNode('profiling_collect_backtrace')
                    ->defaultValue(false)
                    ->info('Enables collecting backtraces when profiling is enabled')
                ->end()
                ->booleanNode('profiling_collect_schema_errors')
                    ->defaultValue(true)
                    ->info('Enables collecting schema errors when profiling is enabled')
                ->end()
                ->scalarNode('server_version')->end()
                ->scalarNode('driver_class')->end()
                ->scalarNode('wrapper_class')->end()
                ->scalarNode('shard_manager_class')->end()
                ->scalarNode('shard_choser')->end()
                ->scalarNode('shard_choser_service')->end()
                ->booleanNode('keep_replica')->end()
                ->arrayNode('options')
                    ->useAttributeAsKey('key')
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('mapping_types')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('default_table_options')
                    ->info("This option is used by the schema-tool and affects generated SQL. Possible keys include 'charset','collate', and 'engine'.")
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
            ->end();

        // dbal >= 2.11
        $replicaNode = $connectionNode
            ->children()
                ->arrayNode('replicas')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype();
        $this->configureDbalDriverNode($replicaNode);

        $shardNode = $connectionNode
            ->children()
                ->arrayNode('shards')
                    ->arrayPrototype();

        $shardNode
            ->children()
                ->integerNode('id')
                    ->min(1)
                    ->isRequired()
                ->end()
            ->end();
        $this->configureDbalDriverNode($shardNode);

        $connectionNode->end()
            ->end()
        ->end();

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!\class_exists('Doctrine\DBAL\DriverManager')) {
            throw new \LogicException('The Doctrine DBAL support cannot be enabled as the Doctrine DBAL is not installed. Try running "composer require doctrine/dbal".');
        }

        foreach ($configs['connections'] as $name => $connectionConfig) {
            $connection = $container->set('doctrine.database_connection.' . $name, new Definition('Doctrine\DBAL\DriverManager::getConnection', [$connectionConfig]));

            if (!empty($configs['types'])) {
                $connection->call(new Statement(__CLASS__ . '::createConnectionTypes', [1 => $configs['types']]), true);
            }

            if ($name === $configs['default_connection']) {
                $connection->autowire(['Doctrine\DBAL\Connection']);
                $container->set('doctrine.database_platform', new Definition([$dc = new Reference('doctrine.database_connection.' . $name), 'getDatabasePlatform']))->autowire(['Doctrine\DBAL\Platforms\AbstractPlatform']);
                $container->set('doctrine.database_query_builder', new Definition([$dc, 'createQueryBuilder']))->autowire(['Doctrine\DBAL\Query\QueryBuilder']);
            }
        }
    }

    /**
     * Adds config keys related to params processed by the DBAL drivers.
     *
     * These keys are available for replica configurations too.
     */
    private function configureDbalDriverNode(ArrayNodeDefinition $node): void
    {
        $node
            ->validate()
            ->always(static function (array $values) {
                if (!isset($values['url'])) {
                    return $values;
                }

                $urlConflictingOptions = ['host' => true, 'port' => true, 'user' => true, 'password' => true, 'path' => true, 'dbname' => true, 'unix_socket' => true, 'memory' => true];
                $urlConflictingValues = \array_keys(\array_intersect_key($values, $urlConflictingOptions));

                if ($urlConflictingValues) {
                    $tail = \count($urlConflictingValues) > 1 ? \sprintf('or "%s" options', \array_pop($urlConflictingValues)) : 'option';
                    trigger_deprecation(
                        'doctrine/dbal',
                        '2.4',
                        'Setting the "doctrine.dbal.%s" %s while the "url" one is defined is deprecated',
                        \implode('", "', $urlConflictingValues),
                        $tail
                    );
                }

                return $values;
            })
            ->end()
            ->children()
                ->scalarNode('url')->info('A URL with connection information; any parameter value parsed from this string will override explicitly set parameters')->end()
                ->scalarNode('dbname')->end()
                ->scalarNode('host')->info('Defaults to "localhost" at runtime.')->end()
                ->scalarNode('port')->info('Defaults to null at runtime.')->end()
                ->scalarNode('user')->info('Defaults to "root" at runtime.')->end()
                ->scalarNode('password')->info('Defaults to null at runtime.')->end()
                ->scalarNode('dbname_suffix')->end()
                ->scalarNode('application_name')->end()
                ->scalarNode('charset')->end()
                ->scalarNode('path')->end()
                ->booleanNode('memory')->end()
                ->scalarNode('unix_socket')->info('The unix socket to use for MySQL')->end()
                ->booleanNode('persistent')->info('True to use as persistent connection for the ibm_db2 driver')->end()
                ->scalarNode('protocol')->info('The protocol to use for the ibm_db2 driver (default to TCPIP if omitted)')->end()
                ->booleanNode('service')
                    ->info('True to use SERVICE_NAME as connection parameter instead of SID for Oracle')
                ->end()
                ->scalarNode('servicename')
                    ->info(
                        'Overrules dbname parameter if given and used as SERVICE_NAME or SID connection parameter ' .
                        'for Oracle depending on the service parameter.'
                    )
                ->end()
                ->scalarNode('sessionMode')
                    ->info('The session mode to use for the oci8 driver')
                ->end()
                ->scalarNode('server')
                    ->info('The name of a running database server to connect to for SQL Anywhere.')
                ->end()
                ->scalarNode('default_dbname')
                    ->info(
                        'Override the default database (postgres) to connect to for PostgreSQL connexion.'
                    )
                ->end()
                ->scalarNode('sslmode')
                    ->info(
                        'Determines whether or with what priority a SSL TCP/IP connection will be negotiated with ' .
                        'the server for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslrootcert')
                    ->info(
                        'The name of a file containing SSL certificate authority (CA) certificate(s). ' .
                        'If the file exists, the server\'s certificate will be verified to be signed by one of these authorities.'
                    )
                ->end()
                ->scalarNode('sslcert')
                    ->info(
                        'The path to the SSL client certificate file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslkey')
                    ->info(
                        'The path to the SSL client key file for PostgreSQL.'
                    )
                ->end()
                ->scalarNode('sslcrl')
                    ->info(
                        'The file name of the SSL certificate revocation list for PostgreSQL.'
                    )
                ->end()
                ->booleanNode('pooled')->info('True to use a pooled server with the oci8/pdo_oracle driver')->end()
                ->booleanNode('MultipleActiveResultSets')->info('Configuring MultipleActiveResultSets for the pdo_sqlsrv driver')->end()
                ->booleanNode('use_savepoints')->info('Use savepoints for nested transactions')->end()
                ->scalarNode('instancename')
                ->info(
                    'Optional parameter, complete whether to add the INSTANCE_NAME parameter in the connection.' .
                    ' It is generally used to connect to an Oracle RAC server to select the name' .
                    ' of a particular instance.'
                )
                ->end()
                ->scalarNode('connectstring')
                ->info(
                    'Complete Easy Connect connection descriptor, see https://docs.oracle.com/database/121/NETAG/naming.htm.' .
                    'When using this option, you will still need to provide the user and password parameters, but the other ' .
                    'parameters will no longer be used. Note that when using this parameter, the getHost and getPort methods' .
                    ' from Doctrine\DBAL\Connection will no longer function as expected.'
                )
                ->end()
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return !isset($v['sessionMode']) && isset($v['session_mode']);
                })
                ->then(static function ($v) {
                    $v['sessionMode'] = $v['session_mode'];
                    unset($v['session_mode']);

                    return $v;
                })
            ->end()
            ->beforeNormalization()
                ->ifTrue(static function ($v) {
                    return !isset($v['MultipleActiveResultSets']) && isset($v['multiple_active_result_sets']);
                })
                ->then(static function ($v) {
                    $v['MultipleActiveResultSets'] = $v['multiple_active_result_sets'];
                    unset($v['multiple_active_result_sets']);

                    return $v;
                })
            ->end();
    }
}
