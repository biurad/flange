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

namespace Rade\DI\Extensions\CycleORM;

use Rade\DI\Container;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\DependenciesInterface;
use Rade\DI\Extensions\ExtensionInterface;

/**
 * Cycle ORM and Database Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class CycleExtension implements AliasedInterface, DependenciesInterface, ExtensionInterface
{
    public const CONFIG_CALL = 'cycle';

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
        return [DatabaseExtension::class, ORMExtension::class];
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
    }
}
