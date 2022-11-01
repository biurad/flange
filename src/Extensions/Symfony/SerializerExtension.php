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

namespace Flange\Extensions\Symfony;

use Flange\KernelInterface;
use Rade\DI\Container;
use Rade\DI\Definitions\Parameter;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Extensions\RequiredPackagesInterface;

use function Rade\DI\Loader\service;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\CacheClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\MimeMessageNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Symfony component serializer extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class SerializerExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface, RequiredPackagesInterface
{
    use Traits\FilesMappingTrait;

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'serializer';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('serializer configuration')
            ->canBeEnabled()
            ->children()
                ->booleanNode('enable_annotations')->defaultFalse()->end()
                ->scalarNode('name_converter')->end()
                ->scalarNode('circular_reference_handler')->end()
                ->scalarNode('max_depth_handler')->end()
                ->arrayNode('mapping')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('path')
                    ->children()
                        ->arrayNode('paths')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('default_context')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->defaultValue([])
                    ->prototype('variable')->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredPackages(): array
    {
        return [
            Serializer::class => 'symfony/serializer',
            PropertyAccess::class => 'symfony/property-access',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function register(Container $container, array $configs = []): void
    {
        if (!$configs['enabled']) {
            return;
        }

        $serializerLoaders = [];
        $definitions = [
            'serializer' => service(Serializer::class, [new TaggedLocator('serializer.normalizer'), new TaggedLocator('serializer.encoder')])->typed(),
            ($cM = 'serializer.mapping.class_metadata_factory') => service(ClassMetadataFactory::class, [new Reference('serializer.mapping.chain_loader')])->public(false),
            'serializer.normalizer.constraint_violation_list' => service(ConstraintViolationListNormalizer::class, [1 => new Reference('serializer.name_converter.metadata_aware')])->public(false)->tag('serializer.normalizer', ['priority' => -915]),
            'serializer.normalizer.mime_message' => service(MimeMessageNormalizer::class, [new Reference('serializer.normalizer.property')])->public(false)->tag('serializer.normalizer', ['priority' => -915]),
            'serializer.normalizer.datetimezone' => service(DateTimeZoneNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -915]),
            'serializer.normalizer.dateinterval' => service(DateIntervalNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -915]),
            'serializer.normalizer.datetime' => service(DateTimeNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -910]),
            'serializer.normalizer.data_uri' => service(DataUriNormalizer::class, [new Reference('?mime_types')])->public(false)->tag('serializer.normalizer', ['priority' => -920]),
            'serializer.normalizer.json_serializable' => service(JsonSerializableNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -900]),
            'serializer.normalizer.problem' => service(ProblemNormalizer::class, [new Parameter('debug')])->public(false)->tag('serializer.normalizer', ['priority' => -890]),
            'serializer.denormalizer.unwrapping' => service(UnwrappingDenormalizer::class, [new Reference('?property_accessor')])->public(false)->tag('serializer.normalizer', ['priority' => 1000]),
            'serializer.normalizer.uid' => service(UidNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -890]),
            'serializer.denormalizer.array' => service(ArrayDenormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -990]),
            'serializer.mapping.chain_loader' => $chainLoader = service(LoaderChain::class, [[]]),
            'serializer.encoder.xml' => service(XmlEncoder::class)->public(false)->tag('serializer.encoder'),
            'serializer.encoder.json' => service(JsonEncoder::class)->public(false)->tag('serializer.encoder'),
            'serializer.encoder.csv' => service(CsvEncoder::class)->public(false)->tag('serializer.encoder'),
        ];

        if (!empty($container->getExtensionConfig(CacheExtension::class, $container->hasExtension(FrameworkExtension::class) ? 'symfony' : null))) {
            $definitions[$cM = 'serializer.mapping.cache_class_metadata_factory'] = service(CacheClassMetadataFactory::class, [new Reference('serializer.mapping.class_metadata_factory'), new Reference('cache.system')])->public(false);
        }

        if (\class_exists(Yaml::class)) {
            $definitions['serializer.encoder.yaml'] = service(YamlEncoder::class)->public(false)->tag('serializer.encoder');
        }

        if ($container->hasExtension(FormExtension::class)) {
            $definitions['serializer.normalizer.form_error'] = service(FormErrorNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -915]);
        }

        $definitions += [
            'property_info.serializer_extractor' => service(SerializerExtractor::class, [new Reference($cM)])->public(false)->tag('property_info.list_extractor', ['priority' => -999]),
            'serializer.mapping.class_discriminator_resolver' => service(ClassDiscriminatorFromClassMetadata::class, [new Reference($cM)])->public(false),
            'serializer.name_converter.metadata_aware' => $nameConverter = service(MetadataAwareNameConverter::class, [new Reference($cM), new Statement(CamelCaseToSnakeCaseNameConverter::class)])->typed()->public(false),
            'serializer.normalizer.object' => $sno = service(
                ObjectNormalizer::class,
                [
                    new Reference($cM),
                    new Reference('serializer.name_converter.metadata_aware'),
                    new Reference('?property_accessor'),
                    new Reference('?property_info'),
                    new Reference('serializer.mapping.class_discriminator_resolver'),
                ]
            )->public(false)->tag('serializer.normalizer', ['priority' => 1000]),
            'serializer.normalizer.property' => service(
                PropertyNormalizer::class,
                [
                    new Reference($cM),
                    new Reference('serializer.name_converter.metadata_aware'),
                    new Reference('?property_info'),
                    new Reference('serializer.mapping.class_discriminator_resolver'),
                ]
            )->public(false)->tag('serializer.normalizer'),
        ];

        if (\interface_exists(\BackedEnum::class)) {
            $definitions['serializer.normalizer.backed_enum'] = service(BackedEnumNormalizer::class)->public(false)->tag('serializer.normalizer', ['priority' => -915]);
        }

        if (isset($configs['enable_annotations']) && $configs['enable_annotations']) {
            $serializerLoaders[] = new Statement(AnnotationLoader::class);
        }

        $fileRecorder = function ($extension, $path) use (&$serializerLoaders): void {
            $serializerLoaders[] = new Statement(\in_array($extension, ['yaml', 'yml'], true) ? YamlFileLoader::class : XmlFileLoader::class, [$path]);
        };

        if ($container instanceof KernelInterface) {
            foreach ($container->getExtensions() as $extension) {
                try {
                    $configDir = $container->getLocation('@'.$extension::class.'/');
                    $configDir = \is_dir($configDir.'Resources/config') ? $configDir.'Resources/config' : $configDir.'/config';
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (\file_exists($file = $configDir.'/serialization.xml')) {
                    $fileRecorder('xml', $file);
                }

                if (
                    \file_exists($file = $configDir.'/serialization.yaml') ||
                    \file_exists($file = $configDir.'/serialization.yml')
                ) {
                    $fileRecorder('yml', $file);
                }

                if (\file_exists($dir = $configDir.'/serialization')) {
                    $this->registerMappingFilesFromDir($dir, $fileRecorder);
                }
            }
        }

        $this->registerMappingFilesFromConfig($container, $configs['mapping']['paths'], $fileRecorder);
        $chainLoader->arg(0, $serializerLoaders);

        if (isset($configs['name_converter']) && $configs['name_converter']) {
            $nameConverter->arg(1, new Reference($configs['name_converter']));
        }

        if (isset($configs['circular_reference_handler']) && $configs['circular_reference_handler']) {
            $context = ['circular_reference_handler' => new Reference($configs['circular_reference_handler'])];
            $sno->args([5 => null, 6 => $context]);
        }

        if ($configs['max_depth_handler'] ?? false) {
            $defaultContext = $sno->getArgument(6) ?? [];
            $defaultContext += ['max_depth_handler' => new Reference($configs['max_depth_handler'])];
            $sno->arg(6, $defaultContext);
        }

        if (isset($configs['default_context']) && $configs['default_context']) {
            $container->parameters['serializer.default_context'] = $configs['default_context'];
        }

        $container->multiple($definitions);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        if (!$container->has('serializer')) {
            return;
        }

        if (!$container->tagged('serializer.normalizer')) {
            throw new \RuntimeException('You must tag at least one service as "serializer.normalizer" to use the "serializer" service.');
        }

        if (!$container->tagged('serializer.encoder')) {
            throw new \RuntimeException('You must tag at least one service as "serializer.encoder" to use the "serializer" service.');
        }

        if ($defaultContext = $container->parameters['serializer.default_context'] ?? null) {
            foreach (\array_merge($container->findBy('serializer.normalizer'), $container->findBy('serializer.encoder')) as $service) {
                $definition = $container->definition($service);
                $initialContext = [];

                if ('serializer.normalizer.object' === $service) {
                    $initialContext = $definition->getArgument(6) ?? [];
                }

                $definition->arg('defaultContext', $defaultContext + $initialContext);
            }

            unset($container->parameters['serializer.default_context']);
        }
    }
}
