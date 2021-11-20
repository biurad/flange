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

namespace Rade\Traits;

use Composer\InstalledVersions;
use Laminas\Escaper\Escaper;
use Nette\Utils\FileSystem;
use Rade\DI\Exceptions\ContainerResolutionException;
use Rade\DI\Extensions\ExtensionBuilder;
use Rade\Provider\ConfigServiceProvider;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

trait HelperTrait
{
    /** @var array<string,string> */
    private array $loadedExtensionPaths = [];

    public function isDebug(): bool
    {
        return $this->parameters['debug'];
    }

    /**
     * Determine if the application is running in the console.
     */
    public function isRunningInConsole(): bool
    {
        return \in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * Determine if the application is in vagrant environment.
     */
    public function inVagrantEnvironment(): bool
    {
        return ('/home/vagrant' === \getenv('HOME') || 'VAGRANT' === \getenv('VAGRANT')) && \is_dir('/dev/shm');
    }

    /**
     * The the delegated config loaders instance.
     */
    public function getConfigLoader(): LoaderResolverInterface
    {
        $configLoader = $this->services['config.loader_resolver'] ?? $this->privates['builder.loader_resolver'] ?? null;

        if (null === $configLoader) {
            if ($this->has('config.loader_resolver')) {
                return $this->get('config.loader_resolver');
            }

            if (isset($this->definitions['builder.loader_resolver'])) {
                $configLoader = $this->definitions['builder.loader_resolver'];
                unset($this->definitions['builder.loader_resolver']);

                return $this->privates['builder.loader_resolver'] = $configLoader;
            }

            throw new ContainerResolutionException(\sprintf('Did you forgot to register the "%s" class?', ConfigServiceProvider::class));
        }

        return $configLoader;
    }

    /**
     * Context specific methods for use in secure output escaping.
     *
     * @param string $encoding
     */
    public function escape(string $encoding = null): Escaper
    {
        return new Escaper($encoding);
    }

    /**
     * Mounts a service provider like controller taking in a parameter of application.
     *
     * @param callable(\Rade\DI\AbstractContainer) $controllers
     */
    public function mount(callable $controllers): void
    {
        $controllers($this);
    }

    /**
     * Loads a set of container extensions.
     *
     * Example for extensions:
     * [
     *    PhpExtension::class,
     *    CoreExtension::class => -1,
     *    [ProjectExtension::class, ['%project.dir%']],
     * ]
     *
     * @param array<int,mixed> $extensions
     * @param array<string,mixed> $config the default configuration for all extensions
     */
    public function loadExtensions(array $extensions, array $configs = []): void
    {
        (new ExtensionBuilder($this, $configs))->load($extensions);
    }

    /**
     * Loads a resource.
     *
     * @param mixed $resource the resource can be anything supported by a config loader
     *
     * @throws \Exception If something went wrong
     *
     * @return mixed
     */
    public function load($resource, string $type = null)
    {
        if (\is_string($resource)) {
            $resource = $this->parameter($resource);

            if ('@' === $resource[0]) {
                $resource = $this->getLocation($resource);
            }
        }

        if (false === $loader = $this->getConfigLoader()->resolve($resource, $type)) {
            throw new LoaderLoadException($resource, null, 0, null, $type);
        }

        return $loader->load($resource, $type);
    }

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
     * @throws \InvalidArgumentException    if the file cannot be found or the name is not valid
     * @throws \RuntimeException            if the name contains invalid/unsafe characters
     * @throws ContainerResolutionException if the service provider is not included in path
     *
     * @return string The absolute path of the resource
     */
    public function getLocation(string $path, string $baseDir = 'src')
    {
        if (false !== \strpos($path, '..')) {
            throw new \RuntimeException(\sprintf('File name "%s" contains invalid characters (..).', $path));
        }

        if ('@' === $path[0]) {
            [$bundleName, $path] = \explode('/', \substr($path, 1), 2);

            if (isset($this->loadedExtensionPaths[$bundleName])) {
                return $this->loadedExtensionPaths[$bundleName] . '/' . $path;
            }

            if (null !== $extension = $this->getExtension($bundleName)) {
                $bundleName = \get_class($extension);
            }

            try {
                $ref = new \ReflectionClass($bundleName);
                $directory = \str_replace('\\', '/', \dirname($ref->getFileName()));
            } catch (\ReflectionException $e) {
                throw new ContainerResolutionException(\sprintf('Resource path is not supported for %s', $bundleName), 0, $e);
            }

            if ($pos = \strpos($directory, $baseDir)) {
                $directory = \substr($directory, 0, $pos + \strlen($baseDir));

                if (!\file_exists($directory)) {
                    $directory = \substr_replace($directory, '', $pos, 3);
                }

                return ($this->loadedExtensionPaths[$bundleName] = \substr($directory, 0, $pos + \strlen($baseDir))) . '/' . $path;
            }

            if (\class_exists(InstalledVersions::class)) {
                $rootPath = InstalledVersions::getRootPackage()['install_path'] ?? false;

                if ($rootPath && $rPos = \strpos($rootPath, 'composer')) {
                    $rootPath = \substr($rootPath, 0, $rPos);

                    if (!$pos = \strpos($directory, $rootPath)) {
                        throw new \UnexpectedValueException(sprintf('Looks like package "%s" does not live in composer\'s directory "%s".', InstalledVersions::getRootPackage()['name'], $rootPath));
                    }

                    $parts = \explode('/', \substr($directory, $pos));
                    $directory = InstalledVersions::getInstallPath($parts[1] . '/' . $parts[2]);

                    if (null !== $directory) {
                        return ($this->loadedExtensionPaths[$bundleName] = FileSystem::normalizePath($directory)) . '/' . $path;
                    }
                }
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unable to find file "%s".', $path));
    }
}
