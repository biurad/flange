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

namespace Flange\Extensions;

use Biurad\UI\Storage\ArrayStorage;
use Biurad\UI\Storage\FilesystemStorage;
use Biurad\UI\Template;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Biurad UI Template Extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class TemplateExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'templating';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->canBeEnabled()
            ->fixXmlConfig('namespace')
            ->fixXmlConfig('render')
            ->fixXmlConfig('global')
            ->children()
                ->scalarNode('cache_dir')->defaultNull()->end()
                ->scalarNode('storage_id')->defaultNull()->end()
                ->arrayNode('paths')
                    ->beforeNormalization()->ifString()->then(fn ($v) => [$v])->end()
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('namespaces')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('renders')
                    ->normalizeKeys(false)
                    ->defaultValue([])
                    ->beforeNormalization()
                        ->ifString()->then(fn ($v) => [$v])
                        ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                        ->thenInvalid('Expected renders config to be an associate array of string keys mapping to string or list string values.')
                        ->always(function (array $value) {
                            $values = [];

                            foreach ($value as $key => $val) {
                                if (\is_array($val)) {
                                    $key = \key($val);
                                    $val = \current($val);
                                }

                                $values[$key] = $val;
                            }

                            return $values;
                        })
                    ->end()
                    ->prototype('variable')->end()
                ->end()
                ->arrayNode('globals')
                    ->normalizeKeys(false)
                    ->defaultValue([])
                    ->beforeNormalization()
                        ->ifTrue(fn ($v) => !\is_array($v) || \array_is_list($v))
                        ->thenInvalid('Expected arguments values to be an associate array of string keys mapping to mixed values.')
                    ->end()
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(Template::class)) {
            throw new \LogicException('Templating support cannot be enabled as the Templating UI library is not installed. Try running "composer require biurad/templating".');
        }

        $template = $container->autowire('templating', new Definition(Template::class));
        $storageId = $container->parameter($configs['storage_id'] ?? '');
        $renders = [];

        if (\file_exists($storageId)) {
            $storage = new Statement(FilesystemStorage::class, [[$storageId, ...$configs['paths']]]);
        } elseif ($container->has($storageId)) {
            $storage = new Reference($storageId);
        } elseif ('' === $storageId && !empty($configs['paths'])) {
            $storage = new Statement(FilesystemStorage::class, [$configs['paths']]);
        }

        $template->arg(0, $storage ?? new Statement(ArrayStorage::class));
        $template->bind('$globals', $configs['globals'] ?? []);

        if (isset($configs['cache_dir'])) {
            $template->arg(1, $configs['cache_dir']);
        }

        foreach ($configs['namespaces'] ?? [] as $nsK => $nsV) {
            $template->bind('addNamespace', [$nsK, $nsV]);
        }

        foreach ($configs['renders'] ?? [] as $render => $extensions) {
            if (\is_numeric($render)) {
                $renders[] = $container->has($extensions) ? new Reference($extensions) : new Statement($extensions);

                continue;
            }

            if ($container->has($render)) {
                throw new ServiceCreationException(\sprintf('The service "%s" is already defined.', $render));
            }

            $renders[] = new Statement($render, \compact('extensions'));
        }

        $template->bind('addRender', [$renders]);
    }
}
