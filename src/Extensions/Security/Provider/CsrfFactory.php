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

use Biurad\Security\Authenticator\CsrfTokenAuthenticator;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

/**
 * The CSRF authenticator factory.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CsrfFactory extends AbstractFactory
{
    public function getKey(): string
    {
        return 'csrf';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
                ->ifString()
                ->then(fn ($v) => ['token_id' => $v])
            ->end()
            ->children()
                ->scalarNode('token_id')->defaultValue('authenticate')->end()
                ->booleanNode('parameter')->defaultValue('_csrf_token')->end()
            ->end()
        ;
    }

    public function create(Container $container, string $id, array $config): void
    {
        if (!$container->has($csrf = 'http.csrf.token_manager')) {
            throw new \RuntimeException('CSRF token manager is not defined.');
        }

        $container->autowire($id, new Definition(CsrfTokenAuthenticator::class, [new Reference($csrf), $config['token_id'], $config['parameter']]));
    }
}
