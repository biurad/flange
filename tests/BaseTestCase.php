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

use Biurad\Http\Factories\GuzzleHttpPsr7Factory;
use Biurad\Http\Interfaces\Psr17Interface;
use PHPUnit\Framework\TestCase;
use Rade\Application;
use Rade\Provider\HttpGalaxyServiceProvider;
use Rade\Provider\RoutingServiceProvider;

/**
 * Application Base TestCase
 * 
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class BaseTestCase extends TestCase
{
    public static function getApplication(string $rootDir = __DIR__, array $config = []): Application
    {
        $app = new Application($rootDir, $config);

        //Let's use default routing and http service.
        $app->register(new HttpGalaxyServiceProvider());
        $app->register(new RoutingServiceProvider());

        return $app;
    }

    public static function getPSR17Factory(): Psr17Interface
    {
        return new GuzzleHttpPsr7Factory();
    }
}
