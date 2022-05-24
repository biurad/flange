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

    public function __construct(bool $debug = true)
    {
        parent::__construct(Application::class);

        $this->parameters['debug'] = $debug;
        $this->set('http.router', new DI\Definition(Router::class))->autowire([Router::class, RouteMatcherInterface::class, UrlGeneratorInterface::class]);
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
    public function match(string $pattern, array $methods = Route::DEFAULT_METHODS, $to = null)
    {
        $routes = $this->parameters['routes'] ??= new RouteCollection();

        return $routes->add(new Route($pattern, $methods, $to), false)->getRoute();
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
        $routes = $this->parameters['routes'] ??= new RouteCollection();

        return $routes->group($prefix);
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
        $containerClass = 'App_' . (($debug = $options['debug'] ?? false) ? 'Debug' : '') . 'Container';
        $a = 'load_' . $containerClass . ($hashFile = '_' . \PHP_SAPI . \PHP_OS . '.php');
        $b = $options['cacheDir'] ?? \dirname((new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2);
        $errorLevel = \error_reporting(\E_ALL ^ \E_WARNING); //ignore "include" failures - don't use "@" to prevent silencing fatal errors

        try {
            if (
                \is_object($c = include $b . '/' . $a) &&
                (!$debug || ($cache = new ConfigCache($b . '/' . $containerClass . $hashFile, $debug))->isFresh())
            ) {
                \error_reporting($errorLevel);

                return $c;
            }
        } catch (\Throwable $e) {
            $c = null;
        }
        \error_reporting($errorLevel); // restore error reporting

        if ($debug && \interface_exists(\Tracy\IBarPanel::class)) {
            Debug\Tracy\ContainerPanel::$compilationTime = \microtime(true);
        }

        $application($container = new static($debug));
        $requiredPaths = $container->parameters['project.require_paths'] ?? []; // Autoload require hot paths ...

        $container->addNodeVisitor(new DI\NodeVisitor\MethodsResolver());
        $container->addNodeVisitor(new DI\NodeVisitor\AutowiringResolver());
        $container->addNodeVisitor($splitter = new DI\NodeVisitor\DefinitionsSplitter($options['maxDefinitions'] ?? 500, $a));

        if (!isset($cache)) {
            $cache = new ConfigCache($b . '/' . $containerClass . $hashFile, $debug); // ... or create a new cache
        }

        $cache->write($container->compile($options + \compact('containerClass')), $container->getResources());
        \file_put_contents(
            $initialize = $splitter->buildTraits($b, $debug, $requiredPaths),
            \sprintf("require '%s';\n\nreturn new \%s();\n", $cache->getPath(), $containerClass),
            \FILE_APPEND | \LOCK_EX
        );

        return $c ?: require $initialize;
    }

    /**
     * {@inheritdoc}
     */
    public function compile(array $options = [])
    {
        $this->parameters['project.compiled_container_class'] = $options['containerClass'];
        unset($this->definitions['config.builder.loader_resolver'], $this->parameters['project.require_paths']);

        if (isset($this->parameters['routes'])) {
            throw new ServiceCreationException(\sprintf('The %s extension needs to be registered before adding routes.', DI\Extensions\RoutingExtension::class));
        }

        return parent::compile($options);
    }
}
