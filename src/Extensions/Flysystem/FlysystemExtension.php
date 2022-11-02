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

namespace Flange\Extensions\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;
use Nette\Utils\Arrays;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Theleague Flysystem Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class FlysystemExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /** @var array<int,AdapterFactoryInterface> */
    private array $builders;

    /**
     * @param array<int,AdapterFactoryInterface> $builders
     */
    public function __construct(array $builders = [])
    {
        $this->builders = [
            new Adapter\AsyncAwsAdapterFactory(),
            new Adapter\AwsAdapterFactory(),
            new Adapter\FtpAdapterFactory(),
            new Adapter\GcloudAdapterFactory(),
            new Adapter\LocalAdapterFactory(),
            new Adapter\MemoryAdapterFactory(),
            new Adapter\SftpAdapterFactory(),
            ...$builders,
        ];
    }

    /**
     * Add a adapter factory.
     */
    public function addAdapterFactory(AdapterFactoryInterface $factory): void
    {
        $this->builders[] = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'flysystem';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->fixXmlConfig('storage')
            ->children()
                ->arrayNode('storages')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('adapter')->isRequired()->end()
                            ->arrayNode('options')
                                ->defaultValue([])
                                ->variablePrototype()->end()
                            ->end()
                            ->scalarNode('visibility')->defaultNull()->end()
                            ->booleanNode('case_sensitive')->defaultTrue()->end()
                            ->booleanNode('disable_asserts')->defaultFalse()->end()
                        ->end()
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
        if (!\class_exists(Filesystem::class)) {
            throw new \LogicException('Flysystem support cannot be enabled as the Flysystem library is not installed. Try running "composer require league/flysystem".');
        }

        if (!\class_exists(OptionsResolver::class)) {
            throw new \LogicException('Flysystem support cannot be enabled as the OptionsResolver library is not installed. Try running "composer require symfony/options-resolver".');
        }
        $mounts = [];

        foreach ($configs['storages'] as $storageConfig) {
            $storageName = Arrays::pick($storageConfig, 'adapter');

            foreach ($this->builders as $builder) {
                if ($builder->getName() === $storageName) {
                    $container->autowire($storageName = 'flysystem.adapter.'.$storageName, $builder->createDefinition($storageConfig['options']));

                    $mounts[] = new Statement(
                        Filesystem::class,
                        [
                            new Reference($storageName),
                            [
                                'visibility' => $storageConfig['visibility'],
                                'case_sensitive' => $storageConfig['case_sensitive'],
                                'disable_asserts' => $storageConfig['disable_asserts'],
                            ],
                        ]
                    );
                }
            }
        }

        if (!empty($mounts)) {
            $container->autowire('flysystem', 1 === \count($mounts) ? $mounts[0] : new Definition(MountManager::class, [$mounts]));
        }
    }
}
