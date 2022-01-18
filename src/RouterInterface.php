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

use Flight\Routing\{Route, RouteCollection};

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
     * @return GeneratedUri
     */
    public function generateUri(string $routeName, array $parameters = []);

    /**
     * Maps a pattern to a callable.
     *
     * You can optionally specify HTTP methods that should be matched.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function match(string $pattern, array $methods = Route::DEFAULT_METHODS, $to = null);

    /**
     * Maps a POST request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function post(string $pattern, $to = null);

    /**
     * Maps a PUT request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function put(string $pattern, $to = null);

    /**
     * Maps a DELETE request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function delete(string $pattern, $to = null);

    /**
     * Maps an OPTIONS request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function options(string $pattern, $to = null);

    /**
     * Maps a PATCH request to a callable.
     *
     * @param mixed $to Callback that returns the response when matched
     *
     * @return Route
     */
    public function patch(string $pattern, $to = null);

    /**
     * Mount route collection into router.
     *
     * @param string $prefix The route named prefix
     *
     * @return RouteCollection
     */
    public function group(string $prefix);
}