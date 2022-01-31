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

namespace Rade\DI\Extensions\Symfony;

use Rade\DI\AbstractContainer;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Rade\DI\Services\DependenciesInterface;

/**
 * Symfony's framework bundle extension.
 */
class FrameworkExtension implements AliasedInterface, DependenciesInterface, ExtensionInterface
{
    public const CONFIG_CALL = 'symfony';

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return self::CONFIG_CALL;
    }

    public function dependOnConfigKey(): string
    {
        return self::CONFIG_CALL;
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [
            EventDispatcherExtension::class,
            CacheExtension::class,
            HttpClientExtension::class,
            AssetExtension::class,
            LockExtension::class,
            RateLimiterExtension::class,
            PropertyInfoExtension::class,
            PropertyAccessExtension::class,
            TranslationExtension::class,
            ValidatorExtension::class,
            FormExtension::class,
            SerializerExtension::class,
            MailerExtension::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
    }
}
