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

namespace Flange\Traits;

use Composer\InstalledVersions;
use Laminas\Escaper\Escaper;
use Nette\Utils\FileSystem;
use Rade\DI\Exceptions\ContainerResolutionException;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\DI\Extensions\ExtensionBuilder;
use Symfony\Component\Config\Exception\LoaderLoadException;
use Symfony\Component\Config\Loader\LoaderResolverInterface;

trait HelperTrait
{
    private array $loadedExtensionPaths = [], $loadedModules = [], $moduleExtensions = [];

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
     * The delegated config loaders instance.
     */
    public function getConfigLoader(): LoaderResolverInterface
    {
        return $this->parameters['config.builder.loader_resolver'] ?? $this->get('config.loader_resolver');
    }

    /**
     * Context specific methods for use in secure output escaping.
     */
    public function escape(string $encoding = null): Escaper
    {
        return new Escaper($encoding);
    }

    /**
     * Mounts a service provider like controller taking in a parameter of application.
     *
     * @param callable(\Rade\DI\Container) $controllers
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
     * @param array<int,mixed>    $extensions
     * @param array<string,mixed> $configs    the default configuration for all extensions
     * @param string|null         $outputDir  Enable Generating ConfigBuilders to help create valid config
     */
    public function loadExtensions(array $extensions, array $configs = [], string $outputDir = null): void
    {
        $builder = new ExtensionBuilder($this, $configs);

        if (null !== $outputDir) {
            $builder->setConfigBuilderGenerator($outputDir);
        }
        $builder->load($extensions + $this->moduleExtensions);
    }

    /**
     * Loads a set of modules from module directory.
     *
     * This method should be called before the loadExtensions method.
     */
    public function loadModules(string $moduleDir, string $prefix = null, string $configName = 'config'): void
    {
        // Get list modules available in application
        foreach (\scandir($moduleDir) as $directory) {
            if ('.' === $directory || '..' === $directory) {
                continue;
            }

            // Check if file parsed is a directory (module need to be a directory)
            if (!\is_dir($directoryPath = \rtrim($moduleDir, '\/').'/'.$prefix.$directory.'/')) {
                continue;
            }

            // Load module configuration file
            if (!\file_exists($configFile = $directoryPath.$configName.'.json')) {
                continue;
            }

            // Load module
            $moduleLoad = new \Flange\Module($directoryPath, \json_decode(\file_get_contents($configFile), true) ?? []);

            if (!\array_key_exists($directory, $this->loadedExtensionPaths)) {
                $this->loadedExtensionPaths[$directory] = $directoryPath;
            }

            if (!$moduleLoad->isEnabled()) {
                continue;
            }

            if (!empty($extensions = $moduleLoad->getExtensions())) {
                $this->moduleExtensions = \array_merge($this->moduleExtensions, $extensions);
            }

            $this->loadedModules[$directory] = $moduleLoad;
        }
    }

    /**
     * Loads a resource.
     *
     * @param mixed $resource the resource can be anything supported by a config loader
     *
     * @return mixed
     *
     * @throws \Exception If something went wrong
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
     * @return string The absolute path of the resource
     *
     * @throws \InvalidArgumentException    if the file cannot be found or the name is not valid
     * @throws \RuntimeException            if the name contains invalid/unsafe characters
     * @throws ContainerResolutionException if the service provider is not included in path
     */
    public function getLocation(string $path, string $baseDir = 'src')
    {
        if (\str_contains($path, '..')) {
            throw new \RuntimeException(\sprintf('File name "%s" contains invalid characters (..).', $path));
        }

        if ('@' === $path[0]) {
            [$bundleName, $path] = \explode('/', \substr($path, 1), 2);

            if (isset($this->loadedExtensionPaths[$bundleName])) {
                return $this->loadedExtensionPaths[$bundleName].'/'.$path;
            }

            if (null !== $extension = $this->getExtension($bundleName)) {
                $bundleName = $extension::class;
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

                return ($this->loadedExtensionPaths[$bundleName] = \substr($directory, 0, $pos + \strlen($baseDir))).'/'.$path;
            }

            if (\class_exists(InstalledVersions::class)) {
                $rootPath = InstalledVersions::getRootPackage()['install_path'] ?? false;

                if ($rootPath && $rPos = \strpos($rootPath, 'composer')) {
                    $rootPath = \substr($rootPath, 0, $rPos);

                    if (!$pos = \strpos($directory, $rootPath)) {
                        throw new \UnexpectedValueException(\sprintf('Looks like package "%s" does not live in composer\'s directory "%s".', InstalledVersions::getRootPackage()['name'], $rootPath));
                    }

                    $parts = \explode('/', \substr($directory, $pos));
                    $directory = InstalledVersions::getInstallPath($parts[1].'/'.$parts[2]);

                    if (null !== $directory) {
                        return ($this->loadedExtensionPaths[$bundleName] = FileSystem::normalizePath($directory)).'/'.$path;
                    }
                }
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unable to find file "%s".', $path));
    }

    /**
     * Load up a module(s) (A.K.A plugin).
     *
     * @return \Rade\Module|array<string,\Rade\Module>
     */
    public function getModule(string $directory = null)
    {
        if (null === $directory) {
            return $this->loadedModules;
        }

        if (!isset($this->loadedModules[$directory])) {
            throw new ServiceCreationException(\sprintf('Failed to load module %s, from modules root path.', $directory));
        }

        return $this->loadedModules[$directory];
    }
}
