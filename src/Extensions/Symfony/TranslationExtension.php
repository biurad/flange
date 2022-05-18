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
use Rade\DI\ContainerBuilder;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\KernelInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Form;
use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProviderFactory;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProviderFactory;
use Symfony\Component\Translation\Command\TranslationPullCommand;
use Symfony\Component\Translation\Command\TranslationPushCommand;
use Symfony\Component\Translation\Dumper\CsvFileDumper;
use Symfony\Component\Translation\Dumper\IcuResFileDumper;
use Symfony\Component\Translation\Dumper\IniFileDumper;
use Symfony\Component\Translation\Dumper\JsonFileDumper;
use Symfony\Component\Translation\Dumper\MoFileDumper;
use Symfony\Component\Translation\Dumper\PhpFileDumper;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Dumper\QtFileDumper;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Dumper\YamlFileDumper;
use Symfony\Component\Translation\Extractor\ChainExtractor;
use Symfony\Component\Translation\Extractor\PhpExtractor;
use Symfony\Component\Translation\Loader\CsvFileLoader;
use Symfony\Component\Translation\Loader\IcuDatFileLoader;
use Symfony\Component\Translation\Loader\IcuResFileLoader;
use Symfony\Component\Translation\Loader\IniFileLoader;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Loader\PoFileLoader;
use Symfony\Component\Translation\Loader\QtFileLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Provider\NullProviderFactory;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Provider\TranslationProviderCollectionFactory;
use Symfony\Component\Translation\PseudoLocalizationTranslator;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Validator\Validation;

use function Rade\DI\Loader\service;

/**
 * Symfony component translation extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class TranslationExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'translator';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('translator configuration')
            ->canBeEnabled()
            ->fixXmlConfig('fallback')
            ->fixXmlConfig('path')
            ->fixXmlConfig('provider')
            ->children()
                ->arrayNode('fallbacks')
                    ->info('Defaults to the value of "default_locale".')
                    ->beforeNormalization()->ifString()->then(function ($v) {
                        return [$v];
                    })->end()
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('logging')->defaultValue(false)->end()
                ->scalarNode('formatter')->defaultValue('translator.formatter.default')->end()
                ->scalarNode('cache_dir')->defaultValue('%project.cache_dir%/translations')->end()
                ->scalarNode('default_path')
                    ->info('The default path used to load translations')
                    ->defaultValue('%project_dir%/translations')
                ->end()
                ->arrayNode('paths')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('pseudo_localization')
                    ->canBeEnabled()
                    ->fixXmlConfig('localizable_html_attribute')
                    ->children()
                        ->booleanNode('accents')->defaultTrue()->end()
                        ->floatNode('expansion_factor')
                            ->min(1.0)
                            ->defaultValue(1.0)
                        ->end()
                        ->booleanNode('brackets')->defaultTrue()->end()
                        ->booleanNode('parse_html')->defaultFalse()->end()
                        ->arrayNode('localizable_html_attributes')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('providers')
                    ->info('Translation providers you can read/write your translations from')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->fixXmlConfig('domain')
                        ->fixXmlConfig('locale')
                        ->children()
                            ->scalarNode('dsn')->end()
                            ->arrayNode('domains')
                                ->prototype('scalar')->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('locales')
                                ->prototype('scalar')->end()
                                ->defaultValue([])
                                ->info('If not set, all locales listed under framework.enabled_locales are used.')
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
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

        if (!\class_exists(Translator::class)) {
            throw new \LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
        }

        $container->parameters['translator.logging'] = $configs['logging'];
        $container->parameters['translator.default_path'] = $configs['default_path'];
        $locales = $container->parameters['enabled_locales'] ?? ['en'];

        if ($configs['providers']) {
            foreach ($configs['providers'] as $provider) {
                if ($provider['locales']) {
                    $locales = \array_merge($locales, $provider['locales']);
                }
            }
        }

        $definitions = [
            'translation.loader.php' => service(PhpFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'php']),
            'translation.loader.yml' => service(YamlFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'yaml', 'legacy-alias' => 'yml']),
            'translation.loader.xliff' => service(XliffFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'xlf', 'legacy-alias' => 'xliff']),
            'translation.loader.po' => service(PoFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'po']),
            'translation.loader.mo' => service(MoFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'mo']),
            'translation.loader.qt' => service(QtFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'ts']),
            'translation.loader.csv' => service(CsvFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'csv']),
            'translation.loader.res' => service(IcuResFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'res']),
            'translation.loader.dat' => service(IcuDatFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'dat']),
            'translation.loader.ini' => service(IniFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'ini']),
            'translation.loader.json' => service(JsonFileLoader::class)->public(false)->tag('translation.loader', ['alias' => 'json']),
            'translation.extractor.php' => service(PhpExtractor::class)->public(false)->tag('translation.extractor', ['alias' => 'php']),
            'translation.reader' => service(TranslationReader::class)->autowire(),
            'translation.extractor' => service(ChainExtractor::class)->autowire(),
            'translation.writer' => service(TranslationWriter::class)->autowire()
                ->bind('addDumper', ['php', new Statement(PhpFileDumper::class)])
                ->bind('addDumper', ['xlf', new Statement(XliffFileDumper::class)])
                ->bind('addDumper', ['po', new Statement(PoFileDumper::class)])
                ->bind('addDumper', ['mo', new Statement(MoFileDumper::class)])
                ->bind('addDumper', ['yml', new Statement(YamlFileDumper::class)])
                ->bind('addDumper', ['yaml', new Statement(YamlFileDumper::class, ['yaml'])])
                ->bind('addDumper', ['ts', new Statement(QtFileDumper::class)])
                ->bind('addDumper', ['csv', new Statement(CsvFileDumper::class)])
                ->bind('addDumper', ['ini', new Statement(IniFileDumper::class)])
                ->bind('addDumper', ['json', new Statement(JsonFileDumper::class)])
                ->bind('addDumper', ['res', new Statement(IcuResFileDumper::class)]),
            'translator.default' => $translator = service(Translator::class, ['%default_locale%', 2 => $configs['cache_dir'], 3 => '%debug%'])
                ->binds([
                    'setFallbackLocales' => [$configs['fallbacks'] ?: ['%default_locale%']],
                    'setConfigCacheFactory' => [new Reference('config_cache_factory')],
                ]),
            'translation.provider_collection_factory' => service(TranslationProviderCollectionFactory::class, [new TaggedLocator('translation.provider_factory'), \array_unique($locales)])->public(false),
            'translation.provider_collection' => service([new Reference('translation.provider_collection_factory'), 'fromConfig'], [$configs['providers']])->autowire([TranslationProviderCollection::class]),
            'translation.provider_factory.null' => service(NullProviderFactory::class)->public(false)->tag('translation.provider_factory'),
        ];

        // Discover translation directories
        $dirs = [];
        $transPaths = [];
        $nonExistingDirs = [];
        $defaultDir = $container->parameter($configs['default_path']);

        if ($container->hasExtension(ValidatorExtension::class)) {
            $r = new \ReflectionClass(Validation::class);
            $dirs[] = $transPaths[] = \dirname($r->getFileName()) . '/Resources/translations';
            unset($r);
        }

        if ($container->hasExtension(FormExtension::class)) {
            $r = new \ReflectionClass(Form::class);
            $dirs[] = $transPaths[] = \dirname($r->getFileName()) . '/Resources/translations';
            unset($r);
        }

        if ($container instanceof KernelInterface) {
            foreach ($container->getExtensions() as $extension) {
                try {
                    $configDir = $container->getLocation('@' . \get_class($extension) . '/');
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (\file_exists($dir = $configDir . 'Resources/translations') || \file_exists($dir = $configDir . 'translations')) {
                    $dirs[] = $dir;
                } else {
                    $nonExistingDirs[] = $dir;
                }
            }
        }

        foreach ($configs['paths'] as $dir) {
            if (\file_exists($dir = $container->parameter($dir))) {
                if ($container instanceof ContainerBuilder) {
                    $container->addResource(new FileExistenceResource($dir));
                }
                $dirs[] = $transPaths[] = $dir;
            } else {
                throw new \UnexpectedValueException(\sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }
        }

        if (null === $defaultDir) {
            // allow null
        } elseif (\file_exists($defaultDir)) {
            if ($container instanceof ContainerBuilder) {
                $container->addResource(new FileExistenceResource($defaultDir));
            }
            $dirs[] = $defaultDir;
        } else {
            $nonExistingDirs[] = $defaultDir;
        }

        // Register translation resources
        if ($dirs = \array_unique($dirs)) {
            foreach ($dirs as $dir) {
                $finder = Finder::create()
                    ->followLinks()
                    ->files()
                    ->filter(function (\SplFileInfo $file) {
                        return 2 <= \substr_count($file->getBasename(), '.') && \preg_match('/\.\w+$/', $file->getBasename());
                    })
                    ->in($dir)
                    ->sortByName();

                foreach ($finder as $file) {
                    // filename is domain.locale.format
                    $fileNameParts = \explode('.', $file->getBasename());
                    $format = \array_pop($fileNameParts);
                    $locale = \array_pop($fileNameParts);
                    $domain = \implode('.', $fileNameParts);

                    $translator->bind('addResource', [$format, (string) $file, $locale, $domain]);
                }
            }

            $projectDir = $container->parameters['project_dir'];
            $scannedDirectories = \array_merge($dirs, \array_unique($nonExistingDirs));
            $translator->arg(4, [
                'scanned_directories' => \array_map(static function (string $dir) use ($projectDir): string {
                    return \str_starts_with($dir, $projectDir . '/') ? \substr($dir, 1 + \strlen($projectDir)) : $dir;
                }, $scannedDirectories),
            ]);
        }

        if ($configs['pseudo_localization']['enabled']) {
            $options = $configs['pseudo_localization'];
            unset($options['enabled']);

            $definitions[$translatorId = 'translator.pseudo'] = service(PseudoLocalizationTranslator::class, [new Reference('translator'), $options])->autowire();
        } else {
            $translator->autowire();
        }

        if ($container->hasExtension(HttpClientExtension::class)) {
            $providerArgs = [
                new Reference('http_client'),
                new Reference('?logger'),
                '%default_locale%',
                $xliff = new Reference('translation.loader.xliff'),
            ];

            if (\class_exists(CrowdinProviderFactory::class)) {
                $definitions['translation.provider_factory.crowdin'] = service(CrowdinProviderFactory::class, $providerArgs + [4 => $xliff])->public(false)->tag('translation.provider_factory');
            }

            if (\class_exists(LocoProviderFactory::class)) {
                $definitions['translation.provider_factory.loco'] = service(LocoProviderFactory::class, $providerArgs + [new Reference($translatorId ?? 'translator.default')])->public(false)->tag('translation.provider_factory');
            }

            if (\class_exists(LokaliseProviderFactory::class)) {
                $definitions['translation.provider_factory.lokalise'] = service(LokaliseProviderFactory::class, $providerArgs)->public(false)->tag('translation.provider_factory');
            }
        }

        if ($container->has('console')) {
            $definitions += [
                'console.command.translation_pull' => service(TranslationPullCommand::class, [
                    new Reference('translation.provider_collection'),
                    new Reference('translation.writer'),
                    new Reference('translation.reader'),
                    '%default_locale%',
                    $transPaths,
                    $locales,
                ])->tag('console.command', ['command' => 'translation:pull']),
                'console.command.translation_push' => service(TranslationPushCommand::class, [
                    new Reference('translation.provider_collection'),
                    new Reference('translation.reader'),
                    $transPaths,
                    $locales,
                ])->tag('console.command', ['command' => 'translation:push']),
            ];
        }

        $container->multiple($definitions);
        $container->alias('translator', $translatorId ?? 'translator.default'); // Use the "real" translator instead of the identity default
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        if (!$container->has('translator')) {
            return;
        }

        $loaders = [];
        $loaderRefs = [];
        $writer = $container->definition('translation.writer');
        $reader = $container->definition('translation.reader');
        $extractor = $container->definition('translation.extractor');
        $translator = $container->definition('translator.default');

        foreach ($container->tagged('translation.dumper') as $id => $attributes) {
            $writer->bind('addDumper', [$attributes['alias'], new Reference($id)]);
        }

        foreach ($container->tagged('translation.extractor') as $id => $attributes) {
            if (!isset($attributes['alias'])) {
                throw new \RuntimeException(\sprintf('The alias for the tag "translation.extractor" of service "%s" must be set.', $id));
            }

            $extractor->bind('addExtractor', [$attributes['alias'], new Reference($id)]);
        }

        foreach ($container->tagged('translation.loader') as $id => $attributes) {
            $loaderRefs[$id] = new Reference($id);
            $loaders[$id][] = $attributes['alias'];

            if (isset($attributes['legacy-alias'])) {
                $loaders[$id][] = $attributes['legacy-alias'];
            }
        }

        foreach ($loaders as $id => $formats) {
            foreach ($formats as $format) {
                $reader->bind('addLoader', [$format, $loaderRefs[$id]]);
                $translator->bind('addLoader', [$format, $loaderRefs[$id]]);
            }
        }
    }
}
