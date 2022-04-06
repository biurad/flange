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

namespace Rade\DI\Extensions\Security\Provider;

use Biurad\Security\Authenticator\FormLoginAuthenticator;
use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Security\Core\User\MissingUserProvider;

class FormLoginFactory extends AbstractFactory
{
    public function getKey(): string
    {
        return 'form-login';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
                ->ifTrue(fn ($v) => null === $v || \is_string($v))
                ->then(fn ($v) => ['provider' => $v])
            ->end()
            ->children()
                ->scalarNode('provider')->end()
                ->booleanNode('erase_credentials')->defaultTrue()->end()
            ->end()
        ;
    }

    public function create(AbstractContainer $container, string $id, array $config): void
    {
        if (isset($config['provider'])) {
            $config['provider'] = new Reference('security.user.provider.concrete.' . \strtolower($config['provider']));
        }

        $container->set($id, new Definition(FormLoginAuthenticator::class, [
            $config['provider'] ?? new Statement(MissingUserProvider::class, ['main']),
            new Reference('security.password_hasher_factory'),
            5 => $config['erase_credentials'],
        ]));
    }
}
