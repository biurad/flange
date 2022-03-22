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
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Definitions\TaggedLocator;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Services\ServiceLocator;
use Rade\KernelInterface;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

/**
 * Symfony component validator extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ValidatorExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    use Traits\FilesMappingTrait;

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'validator';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('validation configuration')
            ->canBeEnabled()
            ->children()
                ->scalarNode('cache')->end()
                ->scalarNode('config_dir')->defaultNull()->end()
                ->booleanNode('enable_annotations')->defaultFalse()->end()
                ->arrayNode('static_method')
                    ->defaultValue(['loadValidatorMetadata'])
                    ->prototype('scalar')->end()
                    ->treatFalseLike([])
                    ->validate()->castToArray()->end()
                ->end()
                ->scalarNode('translation_domain')->defaultValue('validators')->end()
                ->enumNode('email_validation_mode')->values(['html5', 'loose', 'strict'])->defaultValue('loose')->end()
                ->arrayNode('mapping')
                    ->addDefaultsIfNotSet()
                    ->fixXmlConfig('path')
                    ->children()
                        ->arrayNode('paths')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('not_compromised_password')
                    ->canBeDisabled()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('When disabled, compromised passwords will be accepted as valid.')
                        ->end()
                        ->scalarNode('endpoint')
                            ->defaultNull()
                            ->info('API endpoint for the NotCompromisedPassword Validator.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('auto_mapping')
                    ->info('A collection of namespaces for which auto-mapping will be enabled by default, or null to opt-in with the EnableAutoMapping constraint.')
                    ->example([
                        'App\\Entity\\' => [],
                        'App\\WithSpecificLoaders\\' => ['validator.property_info_loader'],
                    ])
                    ->useAttributeAsKey('namespace')
                    ->normalizeKeys(false)
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(function (array $values): array {
                            foreach ($values as $k => $v) {
                                if (isset($v['service'])) {
                                    continue;
                                }

                                if (isset($v['namespace'])) {
                                    $values[$k]['services'] = [];

                                    continue;
                                }

                                if (!\is_array($v)) {
                                    $values[$v]['services'] = [];
                                    unset($values[$k]);

                                    continue;
                                }

                                $tmp = $v;
                                unset($values[$k]);
                                $values[$k]['services'] = $tmp;
                            }

                            return $values;
                        })
                    ->end()
                    ->arrayPrototype()
                        ->fixXmlConfig('service')
                        ->children()
                            ->arrayNode('services')
                                ->prototype('scalar')->end()
                            ->end()
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

        if (!\class_exists(Validation::class)) {
            throw new \LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }

        $validatorBuilder = $container->set('validator.builder', new Definition(ValidatorBuilder::class))->autowire([ValidatorBuilder::class])
            ->bind('setConstraintValidatorFactory', [new Reference('validator.validator_factory')])
            ->bind('setTranslationDomain', ['%validator.translation_domain%']);

        if ($container->hasExtension(TranslationExtension::class)) {
            $validatorBuilder->bind('setTranslator', [new Reference('translator')]);
        }

        if ($container->hasExtension(CacheExtension::class)) {
            $container->set('validator.mapping.cache.adapter', new Definition(PhpArrayAdapter::class . '::create', ['%project.cache_dir%/validation.php', new Reference('cache.system')]))->public(false);

            if (!$container->parameters['debug']) {
                $validatorBuilder->bind('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
            }
        }

        $container->set('validator', new Definition([new Reference('validator.builder'), 'getValidator']))->autowire([ValidatorInterface::class, MetadataFactoryInterface::class]);
        $container->set('validator.validator_factory', new Definition(ContainerConstraintValidatorFactory::class));
        $container->set('validator.email', new Definition(EmailValidator::class, [$configs['email_validation_mode']]))->public(false)->tag('validator.constraint_validator', ['alias' => EmailValidator::class]);

        if (\class_exists(\Symfony\Component\HttpClient\HttpClient::class)) {
            $container->set('validator.not_compromised_password', new Definition(NotCompromisedPasswordValidator::class))
                ->public(false)
                ->arg(2, $configs['not_compromised_password']['enabled'])
                ->arg(3, $configs['not_compromised_password']['endpoint'])
                ->tag('validator.constraint_validator', ['alias' => NotCompromisedPasswordValidator::class]);
        }

        $container->alias('validator.mapping.class_metadata_factory', 'validator');
        $container->parameters['validator.translation_domain'] = $configs['translation_domain'] ?? 'validators';
        $container->parameters['validator.auto_mapping'] = $configs['auto_mapping'];
        $container->parameters['validator.mapping.paths'] = $configs['mapping']['paths'];

        if (\array_key_exists('enable_annotations', $configs) && $configs['enable_annotations']) {
            $validatorBuilder->bind('enableAnnotationMapping', [true]);

            if ($container->has('annotation.doctrine')) {
                $validatorBuilder->bind('setDoctrineAnnotationReader', [new Reference('annotation.doctrine')]);
            }
        }

        if (\array_key_exists('static_method', $configs) && $configs['static_method']) {
            foreach ($configs['static_method'] as $methodName) {
                $validatorBuilder->bind('addMethodMapping', [$methodName]);
            }
        }

        if ($container->hasExtension(PropertyInfoExtension::class)) {
            $container->set('validator.property_info_loader', new Definition(PropertyInfoLoader::class))->public(false)->tag('validator.auto_mapper');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $files = ['xml' => [], 'yml' => []];
        $globalNamespaces = $servicesToNamespaces = $validators = [];
        $validatorBuilder = $container->definition('validator.builder');
        $this->registerValidatorMapping($container, $files);

        if (!empty($files['xml'])) {
            $validatorBuilder->bind('addXmlMappings', [$files['xml']]);
        }

        if (!empty($files['yml'])) {
            $validatorBuilder->bind('addYamlMappings', [$files['yml']]);
        }

        foreach ($container->parameters['validator.auto_mapping'] as $namespace => $value) {
            if ([] === $value['services']) {
                $globalNamespaces[] = $namespace;

                continue;
            }

            foreach ($value['services'] as $service) {
                $servicesToNamespaces[$service][] = $namespace;
            }
        }

        foreach ($container->tagged('validator.auto_mapper') as $AId => $tags) {
            $regexp = $this->getRegexp(\array_merge($globalNamespaces, $servicesToNamespaces[$AId] ?? []));
            $validatorBuilder->bind('addLoader', [new Reference($AId)]);
            $container->definition($AId)->arg('classValidatorRegexp', $regexp);
        }

        foreach ($container->tagged('validator.constraint_validator') as $cId => $attributes) {
            $definition = $container->definition($cId);
            $validators[$definition->getEntity()] = $cRef = new Reference($cId);

            if (isset($attributes['alias'])) {
                $validators[$attributes['alias']] = $cRef;
            }
        }

        $validatorBuilder->bind('addObjectInitializers', [new TaggedLocator('validator.initializer')]);
        $container->definition('validator.validator_factory')->arg(0, new Statement(ServiceLocator::class, $validators));

        unset($container->parameters['validator.auto_mapping']);
    }

    private function registerValidatorMapping(AbstractContainer $container, array &$files): void
    {
        $fileRecorder = function ($extension, $path) use (&$files): void {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if ($container->hasExtension(FormExtension::class) || \class_exists(Form::class)) {
            $reflClass = new \ReflectionClass(Form::class);
            $fileRecorder('xml', \dirname($reflClass->getFileName()) . '/Resources/config/validation.xml');
        }

        if (isset($configs['config_dir'])) {
            if ($container instanceof ContainerBuilder) {
                $container->addResource(new FileExistenceResource($dir = $container->parameter($configs['config_dir'])));
            }
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        if ($container instanceof KernelInterface) {
            foreach ($container->getExtensions() as $extension) {
                try {
                    $configDir = $container->getLocation('@' . \get_class($extension) . '/');
                    $configDir = \is_dir($configDir . 'Resources/config') ? $configDir . 'Resources/config' : $configDir . 'config';
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (
                    \file_exists($file = $configDir . '/validation.yaml') ||
                    \file_exists($file = $configDir . '/validation.yml')
                ) {
                    $fileRecorder('yml', $file);
                }

                if (\file_exists($file = $configDir . '/validation.xml')) {
                    $fileRecorder('xml', $file);
                }

                if (\file_exists($dir = $configDir . '/validation')) {
                    $this->registerMappingFilesFromDir($dir, $fileRecorder);
                }
            }
        }

        $this->registerMappingFilesFromConfig($container, $container->parameters['validator.mapping.paths'], $fileRecorder);
        unset($container->parameters['validator.mapping.paths']);
    }

    /**
     * Builds a regexp to check if a class is auto-mapped.
     */
    private function getRegexp(array $patterns): ?string
    {
        if (!$patterns) {
            return null;
        }

        $regexps = [];

        foreach ($patterns as $pattern) {
            // Escape namespace
            $regex = \preg_quote(\ltrim($pattern, '\\'));

            // Wildcards * and **
            $regex = \strtr($regex, ['\\*\\*' => '.*?', '\\*' => '[^\\\\]*?']);

            // If this class does not end by a slash, anchor the end
            if (!\str_ends_with($regex, '\\')) {
                $regex .= '$';
            }

            $regexps[] = '^' . $regex;
        }

        return \sprintf('{%s}', \implode('|', $regexps));
    }
}
