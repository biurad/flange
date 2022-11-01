<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Extensions\Security\Provider;

use Rade\DI\Container;
use Rade\DI\Definition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;

/**
 * InMemoryFactory creates services for the memory provider.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class InMemoryFactory implements ProviderFactoryInterface
{
    public function create(Container $container, string $id, array $config): void
    {
        $definition = $container->set($id, new Definition(InMemoryUserProvider::class));
        $users = [];

        foreach ($config['users'] as $username => $user) {
            $users[$username] = ['password' => $user['password'], 'roles' => $user['roles']];
        }

        $definition->arg(0, $users);
    }

    public function getKey(): string
    {
        return 'memory';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('user')
            ->children()
                ->arrayNode('users')
                    ->useAttributeAsKey('identifier')
                    ->normalizeKeys(false)
                    ->prototype('array')
                        ->children()
                            ->scalarNode('password')->defaultNull()->end()
                            ->arrayNode('roles')
                                ->beforeNormalization()->ifString()->then(fn ($v) => \preg_split('/\s*,\s*/', $v))->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
