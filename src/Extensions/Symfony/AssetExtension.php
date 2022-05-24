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

namespace Rade\DI\Extensions\Symfony;

use Rade\DI\AbstractContainer;
use Rade\DI\Definition;
use Rade\DI\Definitions\Parameter;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Symfony component asset extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class AssetExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'assets';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('assets configuration')
            ->canBeEnabled()
            ->fixXmlConfig('base_url')
            ->children()
                ->booleanNode('strict_mode')
                    ->info('Throw an exception if an entry is missing from the manifest.json')
                    ->defaultFalse()
                ->end()
                ->scalarNode('version_strategy')->defaultNull()->end()
                ->scalarNode('version')->defaultNull()->end()
                ->scalarNode('secure_context')->defaultNull()->end()
                ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                ->scalarNode('json_manifest_path')->defaultNull()->end()
                ->scalarNode('base_path')->defaultNull()->end()
                ->arrayNode('base_urls')
                    ->requiresAtLeastOneElement()
                    ->beforeNormalization()->castToArray()->end()
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['version_strategy']) && isset($v['version']);
                })
                ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets".')
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['version_strategy']) && isset($v['json_manifest_path']);
                })
                ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".')
            ->end()
            ->validate()
                ->ifTrue(function ($v) {
                    return isset($v['version']) && isset($v['json_manifest_path']);
                })
                ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets".')
            ->end()
            ->fixXmlConfig('package')
            ->children()
                ->arrayNode('packages')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->fixXmlConfig('base_url')
                        ->children()
                            ->booleanNode('strict_mode')
                                ->info('Throw an exception if an entry is missing from the manifest.json')
                                ->defaultFalse()
                            ->end()
                            ->scalarNode('version_strategy')->defaultNull()->end()
                            ->scalarNode('version')
                                ->beforeNormalization()
                                ->ifTrue(function ($v) {
                                    return '' === $v;
                                })
                                ->then(function ($v): void {
                                    return;
                                })
                                ->end()
                            ->end()
                            ->scalarNode('version_format')->defaultNull()->end()
                            ->scalarNode('json_manifest_path')->defaultNull()->end()
                            ->scalarNode('base_path')->defaultNull()->end()
                            ->arrayNode('base_urls')
                                ->requiresAtLeastOneElement()
                                ->beforeNormalization()->castToArray()->end()
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return isset($v['version_strategy']) && isset($v['version']);
                            })
                            ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets" packages.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return isset($v['version_strategy']) && isset($v['json_manifest_path']);
                            })
                            ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return isset($v['version']) && isset($v['json_manifest_path']);
                            })
                            ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(\Symfony\Component\Asset\Package::class)) {
            throw new \LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
        }

        $packages = [];
        $container->parameters['asset.request_context.base_path'] = $configs['base_path'] ?? ($container->parameter('%project_dir%/public'));
        $container->parameters['asset.request_context.secure'] = $configs['secure_context'] ?? (isset($_SERVER['HTTPS']) ? true : false);

        $packagesDef = $container->set('assets.packages', new Definition(Packages::class, [new TaggedLocator('assets.package', 'package')]))->autowire([Packages::class]);
        $container->autowire('assets.context', new Definition(RequestStackContext::class))
            ->args([1 => new Parameter('asset.request_context.base_path'), 2 => new Parameter('asset.request_context.secure')])
            ->public(false);

        if ($configs['version_strategy']) {
            $defaultVersion = $container->has($configs['version_strategy']) ? new Reference($configs['version_strategy']) : new Statement($configs['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($configs['version'], $configs['version_format'], $configs['json_manifest_path'], $configs['strict_mode']);
        }

        foreach ($configs['packages'] as $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $configs['version_format'];
                $version = $package['version'] ?? null;
                $version = $this->createVersion($version, $format, $package['json_manifest_path'], $package['strict_mode']);
            }

            $packages[] = $this->createPackageDefinition('asset.request_context.base_path', $package['base_urls'], $version);
        }

        $packagesDef->args([$this->createPackageDefinition('asset.request_context.base_path', $configs['base_urls'], $defaultVersion), $packages]);
    }

    /**
     * Returns a statement for an asset package.
     */
    private function createPackageDefinition(?string $basePath, array $baseUrls, Statement $version): Statement
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        return new Statement($baseUrls ? UrlPackage::class : PathPackage::class, [$baseUrls ?: new Parameter($basePath, true), $version]);
    }

    private function createVersion(?string $version, ?string $format, ?string $jsonManifestPath, bool $strictMode): Statement
    {
        if (!$version && !$jsonManifestPath) {
            return new Statement(EmptyVersionStrategy::class);
        }

        if (null !== $jsonManifestPath) {
            return new Statement(JsonManifestVersionStrategy::class, [$jsonManifestPath, 2 => $strictMode]);
        }

        // Configuration prevents $version and $jsonManifestPath from being set
        return new Statement(StaticVersionStrategy::class, [$version, $format]);
    }
}
