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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rade\Application;
use Rade\Event\ControllerEvent;
use Rade\Event\RequestEvent;
use Rade\Events;
use Symfony\Contracts\EventDispatcher\Event;

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
        $locale = 'en';
        $debug = false;

        $app = self::getApplication();
        $this->assertEquals($locale, $app['locale']);
        $this->assertEquals($debug, $app['debug']);

        $config = ['locale' => 'fr', 'debug' => true];
        $app = self::getApplication(__DIR__, \compact('config'));
        $this->assertNotEquals($locale, $app['locale']);
        $this->assertNotEquals($debug, $app['debug']);
    }

    public function testGetRequest(): void
    {
        $request = self::getPSR17Factory()->createServerRequest('GET', '/');

        $app = self::getApplication();
        $app->match('/', fn (ServerRequestInterface $req, ResponseInterface $res) => $req->getMethod() . $res->getStatusCode());

        $this->assertEquals('GET200', (string) $app->handle($request)->getBody());
    }

    public function testGetRequestOnRightPath(): void
    {
        $request = self::getPSR17Factory()->createServerRequest('GET', '/hello');

        $app = self::getApplication();
        $app->match('/hello/', 'phpinfo')->argument('what', INFO_GENERAL);

        $this->assertEquals(302, $app->handle($request)->getStatusCode());
    }

    public function testGetRoutesWithNoRoutes(): void
    {
        $app = self::getApplication();

        $routes = $app['routes'];
        $this->assertInstanceOf('Flight\Routing\RouteCollection', $routes);
        $this->assertCount(0, $routes);
    }

    public function testGetRoutesWithRoutes(): void
    {
        $app = self::getApplication();

        $routes = $app['routes'];
        $this->assertCount(0, $routes);

        $app->match('/foo', fn () => 'foo');
        $app->match('/bar')->run(fn () => 'bar');

        $this->assertInstanceOf('Flight\Routing\RouteCollection', $routes);
        $this->assertCount(2, $routes);
    }

    public function testOn(): void
    {
        $app = self::getApplication();
        $app['pass'] = false;

        $app->on('test', function (Event $e) use ($app) {
            $app['pass'] = true;
        });

        $app['dispatcher']->dispatch(new Event(), 'test');

        $this->assertTrue($app['pass']);
    }

    /**
     * @dataProvider provideCoreControllerData
     */
    public function testOnCoreController(string $path, string $expected, string $body, string $name): void
    {
        $app = self::getApplication();

        // Convert a variable, by controller event
        $app->on(Events::CONTROLLER, function (ControllerEvent $event) {
            if ('foo_1' === $event->getName()) {
                $foo = $event->getArguments()['foo'];
                $event->setArgument('foo', new \ArrayObject(['foo' => $foo]));
            } elseif ('foo_2' === $event->getName()) {
                $foo = $event->getArguments()['foo'] . $event->getArguments()['bar'];

                $event->setArgument('foo', new \ArrayObject(['foo' => $foo]));
            }
        });

        $app->match($path, fn (\ArrayObject $foo) => $foo['foo'])->bind($name);
        $request = self::getPSR17Factory()->createServerRequest('GET', $expected);
        $response = $app->handle($request);

        $this->assertEquals($body, (string) $response->getBody());
    }

    public function provideCoreControllerData(): array
    {
        return [
            ['/foo/{foo}', '/foo/bar', 'bar', 'foo_1'],
            ['/foo/{foo}/{bar}', '/foo/foo/bar', 'foobar', 'foo_2']
        ];
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
            ["&#039;", "'"],
            ['abc', 'abc'],
        ];
    }

    public function testControllersAsMethods(): void
    {
        $app = self::getApplication();
        $app->match('/{name}', 'Rade\Tests\Fixtures\FooController:barAction');
        $request = self::getPSR17Factory()->createServerRequest('GET', '/Divine');

        $this->assertEquals('Hello Divine', (string) $app->handle($request)->getBody());
    }

    public function testApplicationTypeHintWorks(): void
    {
        $app = self::getApplication();
        $app->match('/{name}', 'Rade\Tests\Fixtures\FooController@barSpecialAction');
        $request = self::getPSR17Factory()->createServerRequest('GET', '/Divine');
        $response = $app->handle($request);

        $this->assertEquals('Hello Divine in DivineNii\Invoker\CallableResolver', (string) $response->getBody());
    }

    public function testSubRequest(): void
    {
        $app = self::getApplication();
        $type = $app::MASTER_REQUEST;
        $requestCount = 2;

        $app->on(Events::REQUEST, function (RequestEvent $event) use (&$type, &$requestCount) {
            if (!$event->isMasterRequest()) {
                $type = Application::SUB_REQUEST;
            }

            $requestCount--;
        });

        $app->match('/sub', fn () => 'foo');
        $app->match('/', function (ServerRequestFactoryInterface $requestFactory, $container) {
            return $container->handle($requestFactory->createServerRequest('GET', '/sub'), $container::SUB_REQUEST);
        });

        $request = self::getPSR17Factory()->createServerRequest('GET', '/');
        $this->assertEquals('foo', (string) $app->handle($request)->getBody());
        $this->assertEquals(Application::SUB_REQUEST, $type);
        $this->assertEquals(0, $requestCount);
    }

    public function testMountShouldReturnSelf()
    {
        $app = self::getApplication();
        $mounted = new RouteCollection(false);
        $mounted->get('/{name}', fn ($name) => $name);

        $this->assertSame($app, $app->mount('/hello', $mounted));
    }

    public function testMountPreservesOrder(): void
    {
        $app = self::getApplication();
        $mounted = new RouteCollection(false);
        $mounted->get('/mounted')->bind('second');

        $app->match('/before')->bind('first');
        $app->mount('/', $mounted);
        $app->match('/after')->bind('third');

        $routes = iterator_to_array($app['routes']);

        $this->assertEquals(['first', 'second', 'third'], array_map(fn ($route) => $route->getName(), $routes));
    }

    public function testMountNullException(): void
    {
        $app = self::getApplication();

        $this->expectExceptionMessage(
            'The "mount" method takes either a "RouteCollection" instance, "ControllerProviderInterface" instance, or a callable.'
        );
        $this->expectException('LogicException');
        $app->mount('/exception', null);
    }

    public function testMountWrongConnectReturnValueException(): void
    {
        $app = self::getApplication();

        $this->expectExceptionMessage(
            'The method "Rade\Tests\Fixtures\IncorrectControllerCollection::connect" must return a "RouteCollection" instance. Got: "NULL"'
        );
        $this->expectException('LogicException');
        $app->mount('/exception', new Fixtures\IncorrectControllerCollection());
    }

    public function testMountCallable(): void
    {
        $app = self::getApplication();
        $app->mount('/prefix', function (RouteCollection $coll) {
            $coll->get('/path');
        });

        $this->assertCount(1, $app['routes']);
    }
}
