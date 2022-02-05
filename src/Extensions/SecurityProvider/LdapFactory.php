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

namespace Rade\DI\Extensions\SecurityProvider;

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Ldap\Security\LdapUserProvider;

/**
 * LdapFactory creates services for Ldap user provider.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class LdapFactory implements ProviderFactoryInterface
{
    public function create(AbstractContainer $container, string $id, array $config): void
    {
        $container->set($id, new Definition(LdapUserProvider::class, [
            new Reference($config['service']),
            $config['base_dn'],
            $config['search_dn'],
            $config['search_password'],
            $config['default_roles'],
            $config['uid_key'],
            $config['filter'],
            $config['password_attribute'],
            $config['extra_fields'],
        ]));
    }

    public function getKey(): string
    {
        return 'ldap';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->fixXmlConfig('extra_field')
            ->fixXmlConfig('default_role')
            ->children()
                ->scalarNode('service')->isRequired()->cannotBeEmpty()->defaultValue('ldap')->end()
                ->scalarNode('base_dn')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('search_dn')->defaultNull()->end()
                ->scalarNode('search_password')->defaultNull()->end()
                ->arrayNode('extra_fields')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('default_roles')
                    ->beforeNormalization()->ifString()->then(function ($v) { return \preg_split('/\s*,\s*/', $v); })->end()
                    ->requiresAtLeastOneElement()
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('uid_key')->defaultValue('sAMAccountName')->end()
                ->scalarNode('filter')->defaultValue('({uid_key}={username})')->end()
                ->scalarNode('password_attribute')->defaultNull()->end()
            ->end()
        ;
    }
}
