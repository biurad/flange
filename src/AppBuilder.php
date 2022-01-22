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

use Fig\Http\Message\RequestMethodInterface;
use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Rade\DI\Exceptions\ServiceCreationException;
use Symfony\Component\Config\ConfigCache;

/**
 * Create a cacheable application.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class AppBuilder extends DI\ContainerBuilder implements RouterInterface, KernelInterface
{
    use Traits\HelperTrait;

    private int $routeIndex = -1;

    private string $routesId = 'router.collection_a';

    public function __construct(bool $debug = true)
    {
        parent::__construct(Application::class);

        $this->parameters['debug'] = $debug;
        $this->set('http.router', new DI\Definition(Router::class))->autowire([Router::class, RouteMatcherInterface::class]);
    }

    /**
     * @param array<int,mixed> $arguments
     *
     * @return $this
     */
    public function __call(string $name, array $arguments)
    {
        if (!(isset($this->parameters['routes'][$this->routeIndex]) || \method_exists(Route::class, $name))) {
            throw new \BadMethodCallException(\sprintf('Call to undefined method %s::%s()', __CLASS__, $name));
        }

        if (1 === \count($arguments)) {
            $arguments = $arguments[0];
        }

        $this->parameters['routes'][$this->routeIndex][$name] = $arguments;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param DI\Definitions\Reference|DI\Definitions\Statement|DI\Definition ...$middlewares
     */
    public function pipe(object ...$middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof DI\Definitions\Reference) {
                continue;
            }

            $this->autowire('http.middleware.' . \spl_object_id($middleware), $middleware)->public(false);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param DI\Definitions\Reference|DI\Definitions\Statement|DI\Definition ...$middlewares
     */
    public function pipes(string $named, object ...$middlewares): void
    {
        $this->definition('http.router')->bind('pipes', \func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function generateUri(string $routeName, array $parameters = []): DI\Definitions\Statement
    {
        return new Di\Definitions\Statement([new DI\Definitions\Reference('http.router'), 'generateUri'], \func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $pattern, array $methods = Route::DEFAULT_METHODS, $to = null)
    {
        $this->parameters['routes'][++$this->routeIndex] = ['path' => $pattern, 'method' => $methods, 'run' => $to];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_POST], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PUT], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_DELETE], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_OPTIONS], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $to = null)
    {
        return $this->match($pattern, [RequestMethodInterface::METHOD_PATCH], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $prefix)
    {
        $this->set($this->routesId, new DI\Definition(RouteCollection::class, [$prefix]));
        $this->parameters['routes'][++$this->routeIndex] = ['bind' => '@' . $this->routesId];

        ++$this->routesId;

        return $this;
    }

    /**
     * Compiled container and build the application.
     *
     * supported $options config (defaults):
     * - cacheDir => composer's vendor dir, The directory where compiled application class will live in.
     * - shortArraySyntax => true, Controls whether [] or array() syntax should be used for an array.
     * - maxLineLength => 200, Max line of generated code in compiled container class.
     * - maxDefinitions => 500, Max definitions reach before splitting into traits.
     *
     * @param callable            $application write services in here
     * @param array<string,mixed> $options
     *
     * @throws \ReflectionException
     */
    public static function build(callable $application, array $options = []): Application
    {
        $debug = $options['debug'] ?? $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false;
        $defFile = 'load_' . ($containerClass = 'App_' . ($debug ? 'Debug' : '') . 'Container') . '.php';
        $cacheDir = $options['cacheDir'] ?? \dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
        $cache = new ConfigCache($cachePath = $cacheDir . '/' . $containerClass . '_' . \PHP_SAPI . '.php', $debug);

        if (!$cache->isFresh()) {
            $appBuilder = new static($debug);

            if ($debug && \interface_exists(\Tracy\IBarPanel::class)) {
                Debug\Tracy\ContainerPanel::$compilationTime = \microtime(true);
            }

            $application($appBuilder);

            // Default node visitors.
            $appBuilder->addNodeVisitor(new DI\NodeVisitor\MethodsResolver());
            $appBuilder->addNodeVisitor(new DI\NodeVisitor\AutowiringResolver());
            $appBuilder->addNodeVisitor($splitter = new DI\NodeVisitor\DefinitionsSplitter($options['maxDefinitions'] ?? 500, $defFile));

            // Write the compiled application class to path.
            $cache->write($appBuilder->compile($options + \compact('containerClass')), $appBuilder->getResources());

            require $splitter->buildTraits($cacheDir, $appBuilder->isDebug());
        } else {
            require_once $cacheDir . '/' . $defFile;
        }

        require $cachePath;

        return new $containerClass();
    }

    /**
     * {@inheritdoc}
     */
    protected function doAnalyse(array $definitions, bool $onlyDefinitions = false): array
    {
        unset($definitions['config.builder.loader_resolver']);

        if (isset($this->parameters['routes'])) {
            throw new ServiceCreationException(\sprintf('The %s extension needs to be registered before adding routes.', DI\Extensions\RoutingExtension::class));
        }

        return parent::doAnalyse($definitions, $onlyDefinitions);
    }
}
