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

/**
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
interface KernelInterface
{
    /**
     * Checks if application is in debug mode.
     */
    public function isDebug(): bool;

    /**
     * Determine if the application is running in the console.
     */
    public function isRunningInConsole(): bool;

    /**
     * Loads a resource.
     *
     * @param mixed $resource the resource can be anything supported by a config loader
     *
     * @return mixed
     *
     * @throws \Exception If something went wrong
     */
    public function load($resource, string $type = null);

    /**
     * Returns the file path for a given service extension or class name resource.
     *
     * A Resource can be a file or a directory. The resource name must follow the following pattern:
     * "@CoreExtension/path/to/a/file.something"
     *
     * where CoreExtension is the name of the service extension or class,
     * and the remaining part is the relative path to a file or directory.
     *
     * We recommend using composer v2, as this method depends on it or use $baseDir parameter.
     *
     * @param string $name
     *
     * @return string The absolute path of the resource
     *
     * @throws \InvalidArgumentException    if the file cannot be found or the name is not valid
     * @throws \RuntimeException            if the name contains invalid/unsafe characters
     * @throws ContainerResolutionException if the service provider is not included in path
     */
    public function getLocation(string $path, string $baseDir = 'src');

    /**
     * Mounts a service provider like controller taking in a parameter of application.
     *
     * @param callable(\Rade\DI\Container) $controllers
     */
    public function mount(callable $controllers): void;
}
