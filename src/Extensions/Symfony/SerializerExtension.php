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
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\AliasedInterface;
use Rade\KernelInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
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
class SerializerExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
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
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        if (!\class_exists(\Symfony\Component\Serializer\Serializer::class)) {
            throw new \LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
        }

        $serializerLoaders = [];
        $container->autowire('serializer', new Definition(Serializer::class));
        $container->autowire('serializer.mapping.class_discriminator_resolver', new Definition(ClassDiscriminatorFromClassMetadata::class, [new Reference('serializer.mapping.class_metadata_factory')]));
        $factory = $container->set('serializer.mapping.class_metadata_factory', new Definition(ClassMetadataFactory::class, [new Reference('serializer.mapping.chain_loader')]));

        if ($container->hasExtension(CacheExtension::class)) {
            $container->autowire('serializer.mapping.cache_class_metadata_factory', new Definition(CacheClassMetadataFactory::class, [new Reference('serializer.mapping.class_metadata_factory'), new Reference('cache.system')]));
        } else {
            $factory->autowire();
        }

        $container->set('serializer.normalizer.constraint_violation_list', new Definition(ConstraintViolationListNormalizer::class, [[], new Reference('serializer.name_converter.metadata_aware')]))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.mime_message', new Definition(MimeMessageNormalizer::class, [new Reference('serializer.normalizer.property')]))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.datetimezone', new Definition(DateTimeZoneNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.dateinterval', new Definition(DateIntervalNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.data_uri', new Definition(DataUriNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.datetime', new Definition(DateTimeNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.json_serializable', new Definition(JsonSerializableNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.problem', new Definition(ProblemNormalizer::class, ['%debug%']))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.normalizer.uid', new Definition(UidNormalizer::class))->public(false)->tag('serializer.normalizer');
        $container->set('serializer.denormalizer.array', new Definition(ArrayDenormalizer::class))->public(false)->tag('serializer.normalizer');
        $chainLoader = $container->set('serializer.mapping.chain_loader', new Definition(LoaderChain::class, [[]]))->public(false);

        $container->set('serializer.encoder.xml', new Definition(XmlEncoder::class))->public(false)->tag('serializer.encoder');
        $container->set('serializer.encoder.json', new Definition(JsonEncoder::class))->public(false)->tag('serializer.encoder');
        $container->set('serializer.encoder.csv', new Definition(CsvEncoder::class))->public(false)->tag('serializer.encoder');

        if (\class_exists(Yaml::class)) {
            $container->set('serializer.encoder.yaml', new Definition(YamlEncoder::class))->public(false)->tag('serializer.encoder');
        }

        if ($container->hasExtension(FormExtension::class)) {
            $container->set('serializer.normalizer.form_error', new Definition(FormErrorNormalizer::class))->public(false)->tag('serializer.normalizer');
        }

        if ($container->hasExtension(PropertyAccessExtension::class)) {
            $container->set('serializer.denormalizer.unwrapping', new Definition(UnwrappingDenormalizer::class))->public(false)->tag('serializer.normalizer');
            $container->set('serializer.normalizer.object', new Definition(
                ObjectNormalizer::class,
                [
                    new Reference('serializer.mapping.class_metadata_factory'),
                    new Reference('serializer.name_converter.metadata_aware'),
                    new Reference('?property_accessor'),
                    new Reference('serializer.mapping.class_discriminator_resolver'),
                ]
            ))->public(false)->tag('serializer.normalizer')->autowire([ObjectNormalizer::class]);
            $container->set('serializer.normalizer.property', new Definition(
                PropertyNormalizer::class,
                [
                    new Reference('serializer.mapping.class_metadata_factory'),
                    new Reference('serializer.name_converter.metadata_aware'),
                    new Reference('?property_accessor'),
                    new Reference('serializer.mapping.class_discriminator_resolver'),
                ]
            ))->public(false)->tag('serializer.normalizer')->autowire([PropertyNormalizer::class]);
        }

        $container->set('property_info.serializer_extractor', new Definition(SerializerExtractor::class, [new Reference('serializer.mapping.class_metadata_factory')]))->public(false)->tag('property_info.list_extractor');
        $container->set('serializer.name_converter.camel_case_to_snake_case', new Definition(CamelCaseToSnakeCaseNameConverter::class))->public(false);
        $nameConverter = $container->set('serializer.name_converter.metadata_aware', new Definition(MetadataAwareNameConverter::class, [new Reference('serializer.mapping.class_metadata_factory')]))->public(false);

        if (\interface_exists(\BackedEnum::class)) {
            $container->set('serializer.normalizer.backed_enum', new Definition(BackedEnumNormalizer::class))->public(false)->tag('serializer.normalizer');
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
                    $configDir = $container->getLocation('@' . $extension . '/');
                    $configDir = \is_dir($configDir . 'Resources/config') ? $configDir . 'Resources/config' : $configDir . '/config';
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (\file_exists($file = $configDir . '/serialization.xml')) {
                    $fileRecorder('xml', $file);
                }

                if (
                    \file_exists($file = $configDir . '/serialization.yaml') ||
                    \file_exists($file = $configDir . '/serialization.yml')
                ) {
                    $fileRecorder('yml', $file);
                }

                if (\file_exists($dir = $configDir . '/serialization')) {
                    $this->registerMappingFilesFromDir($dir, $fileRecorder);
                }
            }
        }

        $this->registerMappingFilesFromConfig($container, $configs['mapping']['paths'], $fileRecorder);
        $chainLoader->arg(0, $serializerLoaders);

        if (isset($config['name_converter']) && $config['name_converter']) {
            $nameConverter->arg(1, new Reference($config['name_converter']));
        }

        if (isset($config['circular_reference_handler']) && $config['circular_reference_handler'] && $container->has('serializer.normalizer.object')) {
            $arguments = $container->definition('serializer.normalizer.object')->getArguments();
            $context = ($arguments[6] ?? []) + ['circular_reference_handler' => new Reference($config['circular_reference_handler'])];
            $container->definition('serializer.normalizer.object')->args([5 => null, 6 => $context]);
        }

        if ($config['max_depth_handler'] ?? false) {
            $defaultContext = $container->definition('serializer.normalizer.object')->getArguments()[6] ?? [];
            $defaultContext += ['max_depth_handler' => new Reference($configs['max_depth_handler'])];
            $container->definition('serializer.normalizer.object')->arg(6, $defaultContext);
        }

        if (isset($config['default_context']) && $config['default_context']) {
            $container->parameters['serializer.default_context'] = $configs['default_context'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $defCallable = static fn ($v) => $container->has($v) ? new Reference($v) : new Statement($v);

        if (!$normalizers = $container->findBy('serializer.normalizer', $defCallable)) {
            throw new \RuntimeException('You must tag at least one service as "serializer.normalizer" to use the "serializer" service.');
        }

        $serializerDefinition = $container->definition('serializer');
        $serializerDefinition->arg(0, $normalizers);

        if (!$encoders = $container->findBy('serializer.encoder', $defCallable)) {
            throw new \RuntimeException('You must tag at least one service as "serializer.encoder" to use the "serializer" service.');
        }

        $serializerDefinition->arg(1, $encoders);

        if (($defaultContext = $container->parameters['serializer.default_context'] ?? null)) {
            foreach (\array_merge($container->findBy('serializer.normalizer'), $container->findBy('serializer.encoder')) as $service) {
                $definition = $container->definition($service);
                $definition->arg('defaultContext', $defaultContext + $definition->getBindings());
            }

            unset($container->parameters['serializer.default_context']);
        }
    }
}
