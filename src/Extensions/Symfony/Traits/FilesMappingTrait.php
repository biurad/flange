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

namespace Rade\DI\Extensions\Symfony\Traits;

use Rade\DI\Container;
use Rade\DI\ContainerBuilder;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Finder\Finder;

trait FilesMappingTrait
{
    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

    private function registerMappingFilesFromConfig(Container $container, array $mappedPaths, callable $fileRecorder): void
    {
        foreach ($mappedPaths as $path) {
            if (\is_dir($path = $container->parameter($path))) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);

                if ($container instanceof ContainerBuilder) {
                    $container->addResource(new DirectoryResource($path, '/^$/'));
                }
            } elseif (\file_exists($path)) {
                if (!\preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(\sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(\sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }
}
