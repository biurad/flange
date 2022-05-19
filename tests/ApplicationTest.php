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

use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Psr\Http\Message\ServerRequestInterface;
use Rade\DI\Extensions\ConfigExtension;

/**
 * Application test cases.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ApplicationTest extends BaseTestCase
{
    public function testMatchReturnValue(): void
    {
        $app = self::getApplication();

        $returnValue = $app->match('/foo');
        $this->assertInstanceOf(Route::class, $returnValue);

        $returnValue = $app->post('/foo');
        $this->assertInstanceOf(Route::class, $returnValue);

        $returnValue = $app->put('/foo');
        $this->assertInstanceOf(Route::class, $returnValue);

        $returnValue = $app->patch('/foo');
        $this->assertInstanceOf(Route::class, $returnValue);

        $returnValue = $app->delete('/foo');
        $this->assertInstanceOf(Route::class, $returnValue);
    }

    public function testConstructorInjection(): void
    {
        $app = self::getApplication();
        $this->assertArrayNotHasKey('default_locale', $app->parameters);
        $this->assertTrue($app->parameters['debug']);

        $app = self::getApplication();
        $app->loadExtensions([[ConfigExtension::class, [__DIR__]]], ['config' => ['locale' => 'fr', 'debug' => false]]);

        $this->assertEquals('fr', $app->parameters['default_locale']);
        $this->assertFalse($app->parameters['debug']);
        $this->assertEquals(__DIR__, $app->parameters['project_dir']);
    }

    public function testGetRequest(): void
    {
        $request = self::getPSR17Factory()->createServerRequest('GET', '/');

        $app = self::getApplication();
        $app->match('/', ['GET'], fn (ServerRequestInterface $req) => 'Hello ' . $req->getMethod());

        $this->assertEquals('Hello GET', (string) $app->handle($request)->getBody());
    }

    public function testGetRequestOnRightPath(): void
    {
        $request = self::getPSR17Factory()->createServerRequest('GET', '/hello');

        $app = self::getApplication();
        $app->match('/hello/', ['GET'], 'phpinfo')->argument('what', \INFO_GENERAL);

        $this->assertEquals(200, $app->handle($request)->getStatusCode());
    }

    public function testGetRoutesWithNoRoutes(): void
    {
        $app = self::getApplication();

        $routes = $app['http.router']->getCollection()->getRoutes();
        $this->assertCount(0, $routes);
    }

    public function testGetRoutesWithRoutes(): void
    {
        $app = self::getApplication();

        $app->match('/foo', ['GET'], fn () => 'foo');
        $app->match('/bar')->run(fn () => 'bar');

        $routes = $app['http.router']->getCollection()->getRoutes();
        $this->assertCount(2, $routes);
    }

    /**
     * @dataProvider escapeProvider
     */
    public function testEscape(string $expected, string $text): void
    {
        $app = self::getApplication();

        $this->assertEquals($expected, $app->escape()->escapeHtml($text));
    }

    public function escapeProvider(): array
    {
        return [
            ['&lt;', '<'],
            ['&gt;', '>'],
            ['&quot;', '"'],
            ['&#039;', "'"],
            ['abc', 'abc'],
        ];
    }

    public function testControllersAsMethods(): void
    {
        $app = self::getApplication();
        $app->match('/{name}', ['GET'], [Fixtures\FooController::class, 'barAction']);
        $request = self::getPSR17Factory()->createServerRequest('GET', '/Divine');

        $this->assertEquals('Hello Divine', (string) $app->handle($request)->getBody());
    }

    public function testApplicationTypeHintWorks(): void
    {
        $app = self::getApplication();
        $app->match('/{name}', ['GET'], [Fixtures\FooController::class, 'barSpecialAction']);
        $request = self::getPSR17Factory()->createServerRequest('GET', '/Divine');
        $response = $app->handle($request);

        $this->assertEquals('Hello Divine in Biurad\Http\Factory\Psr17Factory', (string) $response->getBody());
    }

    public function testGroupPreservesOrder(): void
    {
        $app = self::getApplication();
        $mounted = new RouteCollection();
        $mounted->get('/b')->bind('second');

        $app->match('/a')->bind('first');
        $app->group('', $mounted);
        $app->match('/c')->bind('third');

        $routes = $routes = $app['http.router']->getCollection()->getRoutes();

        $this->assertEquals(['first', 'third', 'second'], \array_map(fn ($route) => $route->getName(), $routes));
    }
}
