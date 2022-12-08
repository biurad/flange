<?php declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange;

use Biurad\Http\Factory\Psr17Factory;
use Biurad\Http\Interfaces\Psr17Interface;
use Biurad\Http\{Request, Response, Response\HtmlResponse};
use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Interfaces\{RouteMatcherInterface, UrlGeneratorInterface};
use Flight\Routing\{Exceptions\RouteNotFoundException, RouteCollection, RouteUri, Router};
use Laminas\{HttpHandlerRunner\Emitter\SapiStreamEmitter, Stratigility\Utils};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Rade\DI;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\HttpFoundation\RequestStack;

use function Rade\DI\Loader\service;

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

        if (!isset($this->types[Router::class])) {
            $this->definitions = [
                'request_stack' => service($this->services['request_stack'] = new RequestStack())->typed(RequestStack::class)->setContainer($this, 'request_stack'),
                'http.router' => service($this->services['http.router'] = new Router())->typed(Router::class, RouteMatcherInterface::class, UrlGeneratorInterface::class)->setContainer($this, 'http.router'),
                'psr17.factory' => service($this->services['psr17.factory'] = $psr17Factory ?? new Psr17Factory())->typed()->setContainer($this, 'psr17.factory'),
                RequestHandlerInterface::class => service($this->services[RequestHandlerInterface::class] = new Handler\RouteHandler($this))->setContainer($this, RequestHandlerInterface::class),
            ];

            if (null !== $dispatcher) {
                $this->autowire('events.dispatcher', $this->services['events.dispatcher'] = $dispatcher);
            }
        }
    }

    /**
     * If true, exception will be thrown on resolvable services with are not typed.
     */
    public function strictAutowiring(bool $boolean = true): void
    {
        $this->getResolver()->setStrictAutowiring($boolean);
    }

    public function getDispatcher(): ?EventDispatcherInterface
    {
        return $this->get('events.dispatcher', DI\Container::NULL_ON_INVALID_SERVICE);
    }

    public function getRouter(): Router
    {
        return $this->get('http.router');
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface ...$middlewares
     */
    public function pipe(object ...$middlewares): void
    {
        $this->getRouter()->pipe(...$middlewares);
    }

    /**
     * {@inheritdoc}
     *
     * @param MiddlewareInterface ...$middlewares
     */
    public function pipes(string $named, object ...$middlewares): void
    {
        $this->getRouter()->pipes($named, ...$middlewares);
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): RouteUri
    {
        return $this->getRouter()->generateUri($routeName, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $pattern, array $methods = ['GET'], mixed $to = null)
    {
        return $this->getRouter()->getCollection()->add($pattern, $methods, $to);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, mixed $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_POST], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, mixed $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PUT], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, mixed $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_DELETE], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, mixed $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_OPTIONS], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, mixed $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PATCH], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $prefix, callable|RouteCollection|null $collection = null): RouteCollection
    {
        return $this->getRouter()->getCollection()->group($prefix, $collection);
    }

    /**
     * Handles the request and delivers the response.
     *
     * @return int Exit status 0 on success, any other number on failure (e.g. 1)
     *
     * @throws \Throwable
     */
    public function run(ServerRequestInterface $request = null, bool $catch = true): int
    {
        if (!$this->isRunningInConsole()) {
            $response = $this->handle($request ?? $this->get('psr17.factory')->fromGlobalRequest(), $catch);

            if ($response instanceof Response) {
                $code = $response->getResponse()->send()->getStatusCode();

                return $code >= 200 && $code < 400 ? 0 : 1;
            }

            if (!\class_exists(SapiStreamEmitter::class)) {
                throw new \RuntimeException('You must install the laminas/laminas-httphandlerrunner package to emit a response.');
            }

            return (new SapiStreamEmitter())->emit($response) ? 0 : 1;
        }

        return $this->get(ConsoleApplication::class)->run();
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
            $event = $this->getDispatcher()?->dispatch(new Event\RequestEvent($this, $request));

            if (null !== $event) {
                $request = $event->getRequest();
            }

            if ($request instanceof Request) {
                $this->get('request_stack')->push($request->getRequest());
            }

            $response = $event?->getResponse() ?? $this->getRouter()->process($request, $this->get(RequestHandlerInterface::class));

            if ($request instanceof Request) {
                $request = $request->withRequest($this->get('request_stack')->getCurrentRequest());
            }

            $event = $this->getDispatcher()?->dispatch(new Event\ResponseEvent($this, $request, $response));
        } catch (\Throwable $e) {
            if (!$catch || $this->isRunningInConsole()) {
                throw $e;
            }

            return $this->handleThrowable($e, $request);
        } finally {
            $this->getDispatcher()?->dispatch(new Event\TerminateEvent($this, $request));
        }

        return $event?->getResponse() ?? $response;
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

        $event = $this->getDispatcher()?->dispatch(new Event\ExceptionEvent($this, $request, $e));

        // a listener might have replaced the exception
        if (null !== $event) {
            $e = $event->getThrowable();
        }

        if (null === $response = $event?->getResponse()) {
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
        $docVersion = $version[0].'.x.x';

        \ob_start();

        include __DIR__.'/Resources/welcome.phtml';

        return new HtmlResponse((string) \ob_get_clean(), 404);
    }
}
