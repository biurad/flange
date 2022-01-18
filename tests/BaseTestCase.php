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

namespace Rade\Tests;

use Biurad\Http\Factory\Psr17Factory;
use Biurad\Http\Interfaces\Psr17Interface;
use PHPUnit\Framework\TestCase;
use Rade\Application;

/**
 * Application Base TestCase.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class BaseTestCase extends TestCase
{
    public static function getApplication(bool $debug = true): Application
    {
        return new Application(null, null, $debug);
    }

    public static function getPSR17Factory(): Psr17Interface
    {
        return new Psr17Factory();
    }
}
