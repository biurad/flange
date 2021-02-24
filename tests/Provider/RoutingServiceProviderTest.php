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

use Flight\Routing\Interfaces\RouterInterface;
use Flight\Routing\RouteCollection;
use Laminas\Stratigility\Middleware\OriginalMessages;
use Rade\DI\Container;
use Rade\Provider\RoutingServiceProvider;

class RoutingServiceProviderTest extends BaseTestCase
{
    public function testRegister(): void
    {
        $app = self::getApplication();

        $app->match('/hello/{name}', fn ($name) => $name)->bind('hello');
        $app->match('/', fn () => 'none');

        $request = self::getPSR17Factory()->createServerRequest('GET', '/');
        $app->handle($request);

        $this->assertInstanceOf(RouterInterface::class, $app['router']);
    }

    public function testBoot(): void
    {
        $app = self::getApplication();
        $called = 0;

        $app->tag(OriginalMessages::class, [RoutingServiceProvider::TAG_MIDDLEWARE]);
        $app['original_middleware'] = function () use (&$called): OriginalMessages {
            $called++;

            return new OriginalMessages();
        };

        $app->match('/', fn () => 'none');

        $request = self::getPSR17Factory()->createServerRequest('GET', '/');
        $app->boot($request);

        $this->assertEquals(1, $called);
    }

    public function testUrlGeneration(): void
    {
        $app = self::getApplication();

        $app->match('/hello/{name}', fn ($name) => $name)->bind('hello');
        $app->match('/', fn () => (string) $app['router']->generateUri('hello', ['name' => 'john']));

        $request = self::getPSR17Factory()->createServerRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertEquals('./hello/john', (string) $response->getBody());
    }

    public function testAbsoluteUrlGeneration(): void
    {
        $app = self::getApplication();

        $app->match('//localhost:81/hello/{name}', fn ($name) => $name)->bind('hello');
        $app->match('/', fn () => (string) $app['router']->generateUri('hello', ['name' => 'john']));

        $request = self::getPSR17Factory()->createServerRequest('GET', 'https://localhost:81/');
        $response = $app->handle($request);

        $this->assertEquals('http://localhost:81/hello/john', (string) $response->getBody());
    }

    public function testUrlGenerationWithHttp(): void
    {
        $app = self::getApplication();

        $app->match('localhost/insecure', function () {})->bind('insecure_page')->scheme('http');
        $app->match('/', fn () => (string) $app['router']->generateUri('insecure_page'));

        $request = self::getPSR17Factory()->createServerRequest('GET', 'https://localhost/');
        $response = $app->handle($request);

        $this->assertEquals('http://localhost/insecure', (string) $response->getBody());
    }

    public function testUrlGenerationWithHttps()
    {
        $app = self::getApplication();

        $app->match('https://localhost/secure', fn () => 'none')->bind('secure_page');
        $app->match('/', fn () => (string) $app['router']->generateUri('secure_page'));

        $request = self::getPSR17Factory()->createServerRequest('GET', 'http://localhost/');
        $response = $app->handle($request);

        $this->assertEquals('https://localhost/secure', (string) $response->getBody());
    }

    public function testRoutesFactory(): void
    {
        $app = new Container();
        $app['http_factory'] = self::getPSR17Factory();
        $app->register(new RoutingServiceProvider(), ['routing' => []]);

        $coll = $app['routes_factory'];
        $coll->group('blog', function ($blog) {
            $this->assertInstanceOf(RouteCollection::class, $blog);
        });
    }
}
