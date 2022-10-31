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
use Flight\Routing\Interfaces\UrlGeneratorInterface;
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

    public function __construct(bool $debug = true)
    {
        parent::__construct(Application::class);

        $this->parameters['debug'] = $debug;
        $this->set('http.router', new DI\Definition(Router::class))->typed(Router::class, RouteMatcherInterface::class, UrlGeneratorInterface::class);
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

            $this->set('http.middleware.' . \spl_object_id($middleware), $middleware)->public(false)->tag('router.middleware');
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
        return new DI\Definitions\Statement([new DI\Definitions\Reference('http.router'), 'generateUri'], \func_get_args());
    }

    /**
     * {@inheritdoc}
     */
    public function match(string $pattern, array $methods = ['GET'], mixed $to = null)
    {
        $routes = $this->parameters['routes'] ??= new RouteCollection();

        if (!\class_exists($r = 'Flight\Routing\Route')) {
            return $routes->add($pattern, $methods, $to);
        }

        return $routes->add(new $r($pattern, $methods, $to), false)->getRoute();
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
        if (\is_callable($collection)) {
            throw new ServiceCreationException('Route grouping using callable is supported since application is compilable.');
        }
        $routes = $this->parameters['routes'] ??= new RouteCollection();

        return $routes->group($prefix, $collection);
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
        $a = $options['cacheDir'] ?? \dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
        $b = 'App_' . \substr(\md5($a), 0, 10) . '.php';

        if (!($cache = new ConfigCache($a . '/' . $b, $debug = $options['debug'] ?? false))->isFresh()) {
            re_cache:
            if ($debug && \interface_exists(\Tracy\IBarPanel::class)) {
                Debug\Tracy\ContainerPanel::$compilationTime = \microtime(true);
            }

            $application($container = new static($debug));
            $requiredPaths = $container->parameters['project.require_paths'] ?? []; // Autoload require hot paths ...
            $containerClass = 'App_' . ($debug ? 'Debug' : '') . 'Container';

            $container->addNodeVisitor(new DI\NodeVisitor\MethodsResolver());
            $container->addNodeVisitor(new DI\NodeVisitor\AutowiringResolver());
            $container->addNodeVisitor($splitter = new DI\NodeVisitor\DefinitionsSplitter($options['maxDefinitions'] ?? 500, $b));

            $cache->write('', $container->getResources());
            \file_put_contents($a . '/' . $containerClass .'_' . \PHP_SAPI . \PHP_OS . '.php', $container->compile($options + compact('containerClass')));
            \file_put_contents(
                $splitter->buildTraits($a, $requiredPaths),
                \sprintf("\nrequire '%s/%s_'.\PHP_SAPI.\PHP_OS.'.php';\n\nreturn new \%2\$s();\n", $a, $containerClass),
                \FILE_APPEND | \LOCK_EX
            );
        }

        try {
            $container = include $cache->getPath();

            if (!$container instanceof Application) {
                goto re_cache;
            }

            return $container;
        } catch (\Error) {
            goto re_cache;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function compile(array $options = [])
    {
        unset($this->parameters['config.builder.loader_resolver'], $this->parameters['project.require_paths']);

        if (isset($this->parameters['routes'])) {
            throw new ServiceCreationException(\sprintf('The %s extension needs to be registered before adding routes.', DI\Extensions\RoutingExtension::class));
        }

        return parent::compile($options);
    }
}
