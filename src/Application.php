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

use Biurad\Http\Response\HtmlResponse;
use Flight\Routing\Exceptions\RouteNotFoundException;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use GuzzleHttp\Exception\BadResponseException;
use Laminas\Escaper\Escaper;
use Laminas\Stratigility\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Rade\API\BootableProviderInterface;
use Rade\API\ControllerProviderInterface;
use Rade\API\EventListenerProviderInterface;
use Rade\DI\Container;
use Rade\DI\ServiceProviderInterface;
use Rade\Event\ExceptionEvent;
use Rade\Event\RequestEvent;
use Rade\Event\ResponseEvent;
use Rade\Event\TerminateEvent;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * The Rade framework core class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Application extends Container implements RequestHandlerInterface
{
    public const VERSION = '2.0.0-DEV';

    public const MASTER_REQUEST = 1;

    public const SUB_REQUEST = 2;

    private bool $booted = false;

    private array $config;

    /**
     * Instantiate a new Application.
     */
    public function __construct(string $rootDir, array $config = [])
    {
        parent::__construct();

        $this->config = $config;
        $this->offsetSet('project_dir', \rtrim($rootDir, '/') . '/');

        $this->register(new Provider\ConfigServiceProvider());
        $this->register(new Provider\CoreServiceProvider());
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function isRunningInConsole(): bool
    {
        return \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * Determine if the application is in vagrant environment.
     *
     * @return bool
     */
    public function isVagrantEnvironment(): bool
    {
        return (\getenv('HOME') === '/home/vagrant' || \getenv('VAGRANT') === 'VAGRANT') && \is_dir('/dev/shm');
    }

    /**
     * Returns the request type the kernel is currently processing.
     *
     * @return int One of Application::MASTER_REQUEST and Application::SUB_REQUEST
     */
    public function getRequestType(): int
    {
        return $this->config['requestType'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(ServiceProviderInterface $provider, array $values = [])
    {
        if ($provider instanceof ConfigurationInterface) {
            $name   = $provider->getName();
            $values = [$name => $values];

            if (isset($this->config[$name])) {
                $values = \array_intersect_key($this->config, [$name => true]);
            }
        }

        return parent::register($provider, $values);
    }

    /**
     * Boots all service providers.
     *
     * This method is automatically called by handle(), but you can use it
     * to boot all service providers when not handling a request.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        foreach ($this->providers as $provider) {
            if ($provider instanceof EventListenerProviderInterface) {
                $provider->subscribe($this, $this['dispatcher']);
            }

            if ($provider instanceof BootableProviderInterface) {
                $provider->boot($this);
            }
        }
    }

    /**
     * Maps a pattern to a callable.
     *
     * You can optionally specify HTTP methods that should be matched.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function match(string $pattern, $to = null, array $methods = ['GET', 'HEAD']): Route
    {
        return $this['routes']->addRoute($pattern, $methods, $to);
    }

    /**
     * Maps a POST request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function post(string $pattern, $to = null): Route
    {
        return $this['routes']->post($pattern, $to);
    }

    /**
     * Maps a PUT request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function put(string $pattern, $to = null): Route
    {
        return $this['routes']->put($pattern, $to);
    }

    /**
     * Maps a DELETE request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function delete(string $pattern, $to = null): Route
    {
        return $this['routes']->delete($pattern, $to);
    }

    /**
     * Maps an OPTIONS request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function options(string $pattern, $to = null): Route
    {
        return $this['routes']->options($pattern, $to);
    }

    /**
     * Maps a PATCH request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed  $to      Callback that returns the response when matched
     *
     * @return Route
     */
    public function patch(string $pattern, $to = null): Route
    {
        return $this['routes']->patch($pattern, $to);
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string                                               $prefix      The route named prefix
     * @param callable|ControllerProviderInterface|RouteCollection $controllers A RouteCollection, a callable, or a ControllerProviderInterface instance
     *
     * @throws \LogicException
     *
     * @return Application
     */
    public function mount($prefix, $controllers): self
    {
        if ($controllers instanceof ControllerProviderInterface) {
            $connectedControllers = $controllers->connect($this);

            if (!$connectedControllers instanceof RouteCollection) {
                throw new \LogicException(
                    \sprintf(
                        'The method "%s::connect" must return a "RouteCollection" instance. Got: "%s"',
                        \get_class($controllers),
                        \is_object($connectedControllers) ? \get_class($connectedControllers) : \gettype($connectedControllers)
                    )
                );
            }

            $controllers = $connectedControllers;
        } elseif (!$controllers instanceof RouteCollection && !\is_callable($controllers)) {
            throw new \LogicException(
                'The "mount" method takes either a "RouteCollection" instance, "ControllerProviderInterface" instance, or a callable.'
            );
        }

        $prefixPath = \trim($prefix, '/') ?: '/';
        $prefixName = '/' !== $prefixPath ? str_replace('/', '_', $prefixPath) . '_' : '';

        $this['routes']->group($prefixName, $controllers)->withPrefix('/' . $prefixPath);

        return $this;
    }

    /**
     * Context specific methods for use in secure output escaping.
     *
     * @param string $encoding
     *
     * @return Escaper
     */
    public function escape($encoding = null): Escaper
    {
        return new Escaper($encoding);
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string   $eventName The event to listen on
     * @param callable $callback  The listener
     * @param int      $priority  The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     */
    public function on($eventName, $callback, $priority = 0): void
    {
        $this['dispatcher']->addListener($eventName, $callback, $priority);
    }

    /**
     * Registers an error handler.
     *
     * Error handlers are simple callables which take a single Exception
     * as an argument. If a controller throws an exception, an error handler
     * can return a specific response.
     *
     * When an exception occurs, all handlers will be called, until one returns
     * something (a string or a Response object), at which point that will be
     * returned to the client.
     *
     * For this reason you should add logging handlers before output handlers.
     *
     * @param mixed $callback Error handler callback, takes an Exception argument
     * @param int   $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to -8)
     */
    public function error($callback, int $priority = -8): void
    {
        $exceptionWrapper = static function (ExceptionEvent $event) use ($callback): void {
            $e        = $event->getThrowable();
            $code     = $e instanceof BadResponseException ? $e->getResponse()->getStatusCode() : 500;
            $response = $callback(...[$event, (string) $code]);

            if ($response instanceof ResponseInterface) {
                $event->setResponse($response);
            }
        };

        $this->on(Events::EXCEPTION, $exceptionWrapper, $priority);
    }

    /**
     * Handles the request and delivers the response.
     *
     * @param ServerRequestInterface |null $request Request to process
     *
     * @return bool|mixed
     */
    public function run(ServerRequestInterface $request = null, bool $catch = true)
    {
        if (!$this->booted) {
            $this->boot();
        }

        if ($this->isRunningInConsole()) {
            return $this['console']->run(new ArgvInput(['no-debug' => !$this['debug']]));
        }

        $request  = $request ?? $this['http.server_request_creator'];
        $response = $this->handle($request, self::MASTER_REQUEST, $catch);

        if ($response instanceof ResponseInterface) {
            $this->terminate($request, $response);

            return $this['http.emitter']->emit($response);
        }
    }

    /**
     * Terminates a request/response cycle.
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        $event = new TerminateEvent($this, $request, $response);
        $this['dispatcher']->dispatch($event, Events::TERMINATE);
    }

    /**
     * Handles a request to convert it to a response.
     *
     * Exceptions are not caught.
     *
     * @param ServerRequestInterface $request
     * @param int                    $type    The type of the request
     *                               (one of self::MASTER_REQUEST or self::SUB_REQUEST)
     * @param bool                   $catch   Whether to catch exceptions or not
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, $type = self::MASTER_REQUEST, bool $catch = true): ResponseInterface
    {
        $this->config['requestType'] = $type;

        // Listen to request made on self::MASTER_REQUEST and self::SUB_REQUEST
        $event = new RequestEvent($this, $request);
        $this['dispatcher']->dispatch($event, Events::REQUEST);

        if ($event->hasResponse()) {
            return $event->getResponse();
        }

        try {
            $response = $this['router']->handle($request);
        } catch (\Throwable $e) {
            if (!$catch || $this->isRunningInConsole()) {
                throw $e;
            }

            if ($e instanceof RouteNotFoundException) {
                $e = $this->handleRouterException($e, $request);

                if ($e instanceof ResponseInterface) {
                    return $e;
                }
            }

            $response = $this->handleThrowable($e, $request);
        }

        return $this->filterResponse($response, $request);
    }

    /**
     * Filters a response object.
     */
    protected function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        $event = new ResponseEvent($this, $request, $response);
        $this['dispatcher']->dispatch($event, Events::RESPONSE);

        return $event->getResponse();
    }

    /**
     * Handle RouteNotFoundException for Flight Routing
     *
     * @param RouteNotFoundException $e
     * @param ServerRequestInterface $request
     *
     * @return RouteNotFoundException|ResponseInterface
     */
    protected function handleRouterException(RouteNotFoundException $e, ServerRequestInterface $request)
    {
        // If base directory, and no route matched
        $path = $request->getUri()->getPath();
        $base = \dirname($request->getServerParams()['SCRIPT_NAME'] ?? '/');

        if (\rtrim($path, '/') . '/' === \rtrim($base, '\/') . '/') {
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
     * @param \Throwable             $e
     * @param ServerRequestInterface $request
     *
     * @throws Exception
     *
     * @return ResponseInterface
     */
    protected function handleThrowable(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        $event = new ExceptionEvent($this, $request, $e);
        $this['dispatcher']->dispatch($event, Events::EXCEPTION);

        // a listener might have replaced the exception
        $e = $event->getThrowable();

        // Incase we have a bad response error and event has no response.
        if ($e instanceof BadResponseException && !$event->hasResponse()) {
            $event->setResponse($e->getResponse());
        }

        if (!$event->hasResponse()) {
            throw $e;
        }

        /** @var \Biurad\Http\Response $response */
        $response = $event->getResponse();

        // the developer asked for a specific status code
        if (!$event->isAllowingCustomResponseCode() && !$response->isClientError() && !$response->isServerError() && !$response->isRedirect()) {
            // ensure that we actually have an error response and keep the HTTP status code and headers
            $response = $response->withStatus(Utils::getStatusCode($e, $response));
        }

        return $response;
    }

    /**
     * The default welcome page for application
     *
     * @return ResponseInterface
     */
    protected function createWelcomeResponse(): ResponseInterface
    {
        $debug = $this['debug'];
        $version = self::VERSION;
        $docVersion = \substr($version, 0, 1) . '.x.x';

        ob_start();
        include __DIR__ . '/Resources/welcome.phtml';

        return new HtmlResponse(ob_get_clean(), 404);
    }
}
