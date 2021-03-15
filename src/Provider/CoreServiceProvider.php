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

namespace Rade\Provider;

use Biurad\Events\LazyEventDispatcher;
use DivineNii\Invoker\ArgumentResolver\DefaultValueResolver;
use DivineNii\Invoker\ArgumentResolver\NamedValueResolver;
use DivineNii\Invoker\ArgumentResolver\TypeHintValueResolver;
use DivineNii\Invoker\CallableResolver;
use DivineNii\Invoker\Invoker;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;

/**
 * Rade core Provider
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CoreServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'framework';
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $app): void
    {
        $app['argument_value_resolvers'] = static function (Container $app): array {
            return [
                [new NamedValueResolver($app)],
                [new TypeHintValueResolver($app)],
                [new DefaultValueResolver()],
            ];
        };
        $app['callback_resolver'] = static function (Container $app): Invoker {
            $argumentResolvers = $app['argument_value_resolvers'];

            \usort($argumentResolvers, static function ($a, $b): int {
                return key($a) <=> key($b);
            });

            return new Invoker(array_map(static fn ($value) => current($value), $argumentResolvers), $app);
        };
        $app['arguments_resolver'] = $app['callback_resolver']->getArgumentResolver();
        $app['callable_resolver'] = new CallableResolver($app);
        $app['dispatcher'] = $app->lazy(LazyEventDispatcher::class);
    }
}
