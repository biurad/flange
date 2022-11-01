<?php

declare(strict_types=1);

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

use Flight\Routing\{RouteCollection, RouteUri};

/**
 * Flight Routing support methods proxied into application class.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface RouterInterface
{
    /**
     * Attach middleware to the pipeline.
     */
    public function pipe(object ...$middlewares): void;

    /**
     * Attach a list of grouped middlewares to the pipeline.
     */
    public function pipes(string $named, object ...$middlewares): void;

    /**
     * Generate a URI from the named route.
     *
     * @return \Flight\Routing\Generator\GeneratedUri|RouteUri
     */
    public function generateUri(string $routeName, array $parameters = []);

    /**
     * Maps a pattern to a callable.
     *
     * You can optionally specify HTTP methods that should be matched.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function match(string $pattern, array $methods = ['GET'], mixed $to = null);

    /**
     * Maps a POST request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function post(string $pattern, mixed $to = null);

    /**
     * Maps a PUT request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function put(string $pattern, mixed $to = null);

    /**
     * Maps a DELETE request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function delete(string $pattern, mixed $to = null);

    /**
     * Maps an OPTIONS request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function options(string $pattern, mixed $to = null);

    /**
     * Maps a PATCH request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Flight\Routing\Route|RouteCollection
     */
    public function patch(string $pattern, mixed $to = null);

    /**
     * Mount route collection into router.
     *
     * @param string $prefix The route named prefixCollection
     */
    public function group(string $prefix, callable|RouteCollection|null $collection = null): RouteCollection;
}
