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

use Flight\Routing\Interfaces\RouteMatcherInterface;
use Flight\Routing\Route;
use Flight\Routing\RouteCollection;
use Flight\Routing\Router;
use Symfony\Component\Config\ConfigCache;

/**
 * Create a cacheable application.
 *
 @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class AppBuilder extends DI\ContainerBuilder implements RouterInterface
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
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', __CLASS__, $name));
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
     * @param Reference|Statement|callable ...$middlewares
     */
    public function pipe(object ...$middlewares): void
    {
        $this->definition('http.router')->bind('pipe', \func_get_args());
    }

    /**
     * {@inheritdoc}
     *
     * @param Reference|Statement|callable ...$middlewares
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
        return $this->match($pattern, [Router::METHOD_POST], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, $to = null)
    {
        return $this->match($pattern, [Router::METHOD_PUT], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, $to = null)
    {
        return $this->match($pattern, [Router::METHOD_DELETE], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, $to = null)
    {
        return $this->match($pattern, [Router::METHOD_OPTIONS], $to);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, $to = null)
    {
        return $this->match($pattern, [Router::METHOD_PATCH], $to);
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
     * - compiled_file => sys_get_temp_dir(), The directory where compiled application class will live in.
     * - strictAutowiring => true, Resolvable services which are not typed, will be resolved if false.
     * - shortArraySyntax => true, Controls whether [] or array() syntax should be used for an array.
     * - containerClass => CompiledApplication, The class name of the compiled application.
     *
     * @param callable            $application write services in here
     * @param array<string,mixed> $options
     */
    public static function build(callable $application, array $options = []): Application
    {
        $cache = new ConfigCache($options['compiled_file'] ?? (\sys_get_temp_dir() . '/rade_container.php'), $debug = $options['debug'] ?? false);

        if (!$cache->isFresh()) {
            $appBuilder = new static($debug);

            if (isset($options['strictAutowiring'])) {
                $appBuilder->getResolver()->setStrictAutowiring($options['strictAutowiring']);
            }

            $application($appBuilder);

            // Default node visitors.
            $appBuilder->addNodeVisitor(new DI\NodeVisitor\AutowiringResolver());

            // Write the compiled application class to path.
            $cache->write($appBuilder->compile($options += ['containerClass' => 'CompiledApplication']), $appBuilder->getResources());
        }

        include $cache->getPath();

        return new $options['containerClass']();
    }

    /**
     * {@inheritdoc}
     */
    protected function doAnalyse(array $definitions, bool $onlyDefinitions = false): array
    {
        unset($this->parameters['routes'], $definitions['builder.loader_resolver']);

        return parent::doAnalyse($definitions, $onlyDefinitions);
    }
}
