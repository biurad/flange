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

namespace Flange\Extensions\Security\Provider;

use Biurad\Security\Authenticator\RemoteUserAuthenticator;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Security\Core\User\MissingUserProvider;

class RemoteUserFactory extends AbstractFactory
{
    public function getKey(): string
    {
        return 'remote-user';
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
                ->scalarNode('user')->defaultValue('SSL_CLIENT_S_DN_Email')->end()
                ->scalarNode('credentials')->defaultValue('SSL_CLIENT_S_DN')->end()
            ->end()
        ;
    }

    public function create(Container $container, string $id, array $config): void
    {
        if (isset($config['provider'])) {
            $config['provider'] = new Reference('security.user.provider.concrete.'.\strtolower($config['provider']));
        }

        $container->set($id, new Definition(RemoteUserAuthenticator::class, [
            $config['provider'] ?? new Statement(MissingUserProvider::class, ['main']),
            new Reference('security.token_storage'),
            $config['user'],
            $config['credentials'],
        ]));
    }
}
