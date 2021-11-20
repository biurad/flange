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

namespace Rade;

use Biurad\Http\{Factory\NyholmPsr7Factory, Interfaces\Psr17Interface, Response\HtmlResponse};
use Flight\Routing\{Exceptions\RouteNotFoundException, Route, RouteCollection, Router};
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Laminas\Stratigility\Middleware\{CallableMiddlewareDecorator, RequestHandlerMiddleware};
use Laminas\{HttpHandlerRunner\Emitter\SapiStreamEmitter, Stratigility\Utils};
use Psr\EventDispatcher\EventDispatcherInterface;
use Rade\DI\Definitions\{Reference, Statement};
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * The Rade framework core class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Application extends DI\Container implements RouterInterface
{
    use Traits\HelperTrait;

    public const VERSION = '2.0.0-DEV';

    /**
     * Instantiate a new Application.
     */
    public function __construct(Psr17Interface $psr17Factory = null, EventDispatcherInterface $dispatcher = null, bool $debug = false)
    {
        parent::__construct();

        if (empty($this->methodsMap)) {
            $this->definitions['http.router'] = Router::withCollection();
            $this->definitions['psr17.factory'] = $psr17Factory = ($psr17Factory ?? new NyholmPsr7Factory());
            $this->definitions['events.dispatcher'] = $dispatcher = ($dispatcher ?? new Handler\EventHandler());

            $this->types(
                [
                    'http.router' => [Router::class, RouteMatcherInterface::class],
                    'psr17.factory' => DI\Resolvers\Resolver::autowireService($psr17Factory),
                    'events.dispatcher' => DI\Resolvers\Resolver::autowireService($dispatcher),
                ]
            );
        }

        $this->parameters['debug'] ??= $debug;
    }

    public function strictAutowiring(bool $boolean = true): void
    {
        $this->resolver->setStrictAutowiring($boolean);
    }

    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->services['events.dispatcher'] ?? $this->get('events.dispatcher');
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface|RequestHandlerInterface|Reference|Statement|callable ...$middlewares
     */
    public function pipe(object ...$middlewares): void
    {
        $this->get('http.router')->pipe(...$this->resolveMiddlewares($middlewares));
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface|RequestHandlerInterface|Reference|Statement|callable ...$middlewares
     */
    public function pipes(string $named, object ...$middlewares): void
    {
        $this->get('http.router')->pipes($named, ...$this->resolveMiddlewares($middlewares));
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        return $this->get('http.router')->generateUri($routeName, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $pattern, array $methods = Route::DEFAULT_METHODS, $to = null): Route
    {
        return ($this->services['http.router'] ?? $this->get('http.router'))->getCollection()->addRoute($pattern, $methods, $to)->getRoute();
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [Router::METHOD_POST], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [Router::METHOD_PUT], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [Router::METHOD_DELETE], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [Router::METHOD_OPTIONS], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [Router::METHOD_PATCH], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $prefix, $collection = null): RouteCollection
    {
        return $this->get('http.router')->getCollection()->group($prefix, $collection);
    }

    /**
     * Handles the request and delivers the response.
     *
     * @param ServerRequestInterface|null $request Request to process
     *
     * @return int|bool
     */
    public function run(ServerRequestInterface $request = null, bool $catch = true)
    {
        if ($this->isRunningInConsole()) {
            $this->get(ConsoleApplication::class)->run();
        }

        if (null === $request) {
            $request = $this->get('psr17.factory')->fromGlobalRequest();
        }

        if (!$this->has(RequestHandlerInterface::class)) {
            $this->definitions[RequestHandlerInterface::class] = new Handler\RouteHandler($this);
        }

        return (new SapiStreamEmitter())->emit($this->handle($request, $catch));
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param bool $catch Whether to catch exceptions or not
     */
    public function handle(ServerRequestInterface $request, bool $catch = true): ResponseInterface
    {
        try {
            $this->getDispatcher()->dispatch($event = new Event\RequestEvent($this, $request));

            if ($event->hasResponse()) {
                return $event->getResponse();
            }

            $request = $event->getRequest();
            $response = $this->get('http.router')->process($request, $this->get(RequestHandlerInterface::class));

            $this->getDispatcher()->dispatch($event = new Event\ResponseEvent($this, $request, $response));
        } catch (\Throwable $e) {
            if (!$catch || $this->isRunningInConsole()) {
                throw $e;
            }

            return $this->handleThrowable($e, $request);
        } finally {
            $this->getDispatcher()->dispatch(new Event\TerminateEvent($this, $request));
        }

        return $event->getResponse();
    }

    /**
     * Handle RouteNotFoundException for Flight Routing.
     *
     * @return RouteNotFoundException|ResponseInterface
     */
    protected function handleRouterException(RouteNotFoundException $e, ServerRequestInterface $request)
    {
        if (empty($pathInfo = $request->getServerParams()['PATH_INFO'] ?? '')) {
            $pathInfo = $request->getUri()->getPath();
        }

        if ('/' === $pathInfo) {
            return $this->createWelcomeResponse();
        }

        $message = $e->getMessage();

        if ('' !== $referer = $request->getHeaderLine('referer')) {
            $message .= \sprintf(' (from "%s")', $referer);
        }

        return new RouteNotFoundException($message, 404);
    }

    /**
     * Handles a throwable by trying to convert it to a Response.
     *
     * @throws \Throwable
     */
    protected function handleThrowable(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $this->getDispatcher()->dispatch($event = new Event\ExceptionEvent($this, $request, $e));

        // a listener might have replaced the exception
        $e = $event->getThrowable();

        if (null === $response = $event->getResponse()) {
            if ($e instanceof RouteNotFoundException) {
                $e = $this->handleRouterException($e, $request);

                if ($e instanceof ResponseInterface) {
                    return $e;
                }
            }

            throw $e;
        }

        // ensure that we actually have an error response and keep the HTTP status code and headers
        if (!$event->isAllowingCustomResponseCode()) {
            $response = $response->withStatus(Utils::getStatusCode($e, $response));
        }

        return $response;
    }

    /**
     * The default welcome page for application.
     */
    protected function createWelcomeResponse(): ResponseInterface
    {
        $debug = $this->parameters['debug'];
        $version = self::VERSION;
        $docVersion = $version[0] . '.x.x';

        \ob_start();

        include __DIR__ . '/Resources/welcome.phtml';

        return new HtmlResponse((string) \ob_get_clean(), 404);
    }

    /**
     * Resolve Middlewares.
     *
     * @param array<int,MiddlewareInterface|RequestHandlerInterface|Reference|Statement|callable> $middlewares
     *
     * @return array<int,MiddlewareInterface>
     */
    protected function resolveMiddlewares(array $middlewares): array
    {
        foreach ($middlewares as $offset => $middleware) {
            if ($middleware instanceof RequestHandlerInterface) {
                $middlewares[$offset] = new RequestHandlerMiddleware($middleware);
            } elseif ($middleware instanceof Statement) {
                $middlewares[$offset] = $this->resolver->resolve($middleware->getValue(), $middleware->getArguments());
            } elseif ($middleware instanceof Reference) {
                $middlewares[$offset] = $this->get((string) $middleware);
            } elseif (\is_callable($middleware)) {
                $middlewares[$offset] = new CallableMiddlewareDecorator($middleware);
            }
        }

        return $middlewares;
    }
}
