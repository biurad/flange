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

namespace Flange\Extensions\Symfony;

use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Lock\Strategy\ConsensusStrategy;

/**
 * Symfony lock extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class LockExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'lock';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('Lock configuration')
            ->canBeEnabled()
            ->beforeNormalization()
                ->ifString()->then(fn ($v) => ['enabled' => true, 'resources' => $v])
            ->end()
            ->beforeNormalization()
                ->ifTrue(fn ($v) => \is_array($v) && !isset($v['enabled']))
                ->then(fn ($v) => $v + ['enabled' => true])
            ->end()
            ->beforeNormalization()
                ->ifTrue(fn ($v) => \is_array($v) && !isset($v['resources']) && !isset($v['resource']))
                ->then(function ($v) {
                    $e = $v['enabled'];
                    unset($v['enabled']);

                    return ['enabled' => $e, 'resources' => $v];
                })
            ->end()
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('resource')
            ->children()
                ->arrayNode('resources')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->defaultValue(['default' => [\class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock']])
                    ->beforeNormalization()
                        ->ifString()->then(fn ($v) => ['default' => $v])
                    ->end()
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => \is_array($v) && \array_is_list($v))
                        ->then(function ($v) {
                            $resources = [];

                            foreach ($v as $resource) {
                                $resources[] = \is_array($resource) && isset($resource['name'])
                                    ? [$resource['name'] => $resource['value']]
                                    : ['default' => $resource]
                                ;
                            }

                            return \array_merge_recursive([], ...$resources);
                        })
                    ->end()
                    ->arrayPrototype()
                        ->performNoDeepMerging()
                        ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                        ->prototype('scalar')->end()
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
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(StoreFactory::class)) {
            throw new \LogicException('Lock support cannot be enabled as the component is not installed. Try running "composer require symfony/lock".');
        }

        foreach ($configs['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];

            foreach ($resourceStores as $storeDsn) {
                $storeDefinition = new Statement(StoreFactory::class.'::createStore', [$container->parameter($storeDsn)]);
                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $storeDefinition = new Statement(CombinedStore::class, [$storeDefinitions, new Statement(ConsensusStrategy::class)]);
            }

            // Generate factories for each resource
            $lock = $container->set('lock.'.$resourceName.'.factory', new Definition(LockFactory::class, [$storeDefinition]));

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->alias('lock.factory', 'lock.'.$resourceName.'.factory');
                $lock->typed(LockFactory::class);
            }
        }
    }
}
