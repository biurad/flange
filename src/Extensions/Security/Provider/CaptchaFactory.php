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

namespace Flange\Extensions\Security\Provider;

use Biurad\Security\Authenticator\CaptchaAuthenticator;
use Rade\DI\Container;
use Rade\DI\Definition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

class CaptchaFactory extends AbstractFactory
{
    public function getKey(): string
    {
        return 'captcha';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        $node
            ->beforeNormalization()
                ->ifString()->then(fn ($v) => ['recaptcha_secret' => $v])
            ->end()
            ->children()
                ->scalarNode('recaptcha_secret')->defaultNull()->end()
                ->scalarNode('hcaptcha_secret')->defaultNull()->end()
            ->end()
        ;
    }

    public function create(Container $container, string $id, array $config): void
    {
        $container->autowire($id, new Definition(CaptchaAuthenticator::class, [$config['recaptcha_secret'], $config['hcaptcha_secret']]));
    }
}
