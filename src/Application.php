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

use Biurad\Http\{Request, Response, Response\HtmlResponse};
use Biurad\Http\Factory\Psr17Factory;
use Biurad\Http\Interfaces\Psr17Interface;
use Flight\Routing\{Exceptions\RouteNotFoundException, Route, RouteCollection, Router};
use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Generator\GeneratedUri;
use Flight\Routing\Interfaces\{RouteMatcherInterface, UrlGeneratorInterface};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Laminas\Stratigility\Middleware\{CallableMiddlewareDecorator, RequestHandlerMiddleware};
use Laminas\{HttpHandlerRunner\Emitter\SapiStreamEmitter, Stratigility\Utils};
use Psr\EventDispatcher\EventDispatcherInterface;
use Rade\DI\Definitions\{Reference, Statement};
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The Rade framework core class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Application extends DI\Container implements RouterInterface, KernelInterface
{
    use Traits\HelperTrait;

    public const VERSION = '2.0.0-DEV';

    /**
     * Instantiate a new Application.
     */
    public function __construct(Psr17Interface $psr17Factory = null, EventDispatcherInterface $dispatcher = null, bool $debug = false)
    {
        parent::__construct();
        $this->parameters['debug'] ??= $debug;

        if (!isset($this->parameters['project.compiled_container_class'])) {
            $this->definitions = [
                'http.router' => $this->services['http.router'] = new Router(),
                'request_stack' => $this->services['request_stack'] = new RequestStack(),
                'psr17.factory' => $this->services['psr17.factory'] = $psr17Factory = $psr17Factory ?? new Psr17Factory(),
                'events.dispatcher' => $this->services['events.dispatcher'] = $dispatcher = $dispatcher ?? new Handler\EventHandler(),
            ];
            $this->types += [
                RequestStack::class => ['request_stack'],
                Router::class => ['http.router'],
                RouteMatcherInterface::class => ['http.router'],
                UrlGeneratorInterface::class => ['http.router'],
            ];
            $this->types(['psr17.factory' => DI\Resolver::autowireService($psr17Factory), 'events.dispatcher' => DI\Resolver::autowireService($dispatcher)]);
        }
    }

    /**
     * If true, exception will be thrown on resolvable services with are not typed.
     */
    public function strictAutowiring(bool $boolean = true): void
    {
        $this->resolver->setStrictAutowiring($boolean);
    }

    public function getDispatcher(): EventDispatcherInterface
    {
        return $this->services['events.dispatcher'] ?? $this->get('events.dispatcher');
    }

    public function getRouter(): Router
    {
        return $this->services['http.router'] ?? $this->get('http.router');
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface|RequestHandlerInterface|Reference|Statement|callable ...$middlewares
     */
    public function pipe(object ...$middlewares): void
    {
        $this->getRouter()->pipe(...$this->resolveMiddlewares($middlewares));
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface|RequestHandlerInterface|Reference|Statement|callable ...$middlewares
     */
    public function pipes(string $named, object ...$middlewares): void
    {
        $this->getRouter()->pipes($named, ...$this->resolveMiddlewares($middlewares));
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): GeneratedUri
    {
        return $this->getRouter()->generateUri($routeName, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $pattern, array $methods = Route::DEFAULT_METHODS, $to = null): Route
    {
        return $this->getRouter()->getCollection()->add(new Route($pattern, $methods, $to), false)->getRoute();
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_POST], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PUT], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_DELETE], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_OPTIONS], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $to = null): Route
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PATCH], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $prefix, $collection = null): RouteCollection
    {
        return $this->getRouter()->getCollection()->group($prefix, $collection);
    }

    /**
     * Handles the request and delivers the response.
     *
     * @param ServerRequestInterface|null $request Request to process
     *
     * @throws \Throwable
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

        $response = $this->handle($request, $catch);

        if ($response instanceof Response) {
            $response->getResponse()->send();

            return true;
        }

        if (!\class_exists(SapiStreamEmitter::class)) {
            throw new \RuntimeException(\sprintf('Unable to emit response onto the browser. Try running "composer require laminas/laminas-httphandlerrunner".'));
        }

        return (new SapiStreamEmitter())->emit($response);
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
        if (!$this->has(RequestHandlerInterface::class)) {
            $this->definitions[RequestHandlerInterface::class] = $this->services[RequestHandlerInterface::class] = new Handler\RouteHandler($this);
        }

        try {
            $response = $this->getRouter()->process($request, $this->get(RequestHandlerInterface::class));

            if ($request instanceof Request) {
                $request = $request->withRequest($this->get('request_stack')->getMainRequest());
            }

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
        if ($request instanceof Request) {
            $this->get('request_stack')->push($request->getRequest());
        }

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
