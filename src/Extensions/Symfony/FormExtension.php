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
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Extensions\Symfony\Form\HttpFoundationRequestHandler;
use Rade\DI\Services\ServiceLocator;
use Rade\KernelInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\Command\DebugCommand;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TransformationFailureExtension;
use Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension;
use Symfony\Component\Form\Extension\DependencyInjection\DependencyInjectionExtension;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\Type\FormTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\RepeatedTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\SubmitTypeValidatorExtension;
use Symfony\Component\Form\Extension\Validator\Type\UploadValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Symfony component form extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class FormExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'form';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->info('form configuration')
            ->canBeEnabled()
            ->children()
                ->arrayNode('csrf_protection')
                    ->treatFalseLike(['enabled' => false])
                    ->treatTrueLike(['enabled' => true])
                    ->treatNullLike(['enabled' => true])
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultNull()->end() // defaults to framework.csrf_protection.enabled
                        ->scalarNode('field_name')->defaultValue('_token')->end()
                    ->end()
                ->end()
                // to be deprecated in Symfony 6.1
                ->booleanNode('legacy_error_messages')->end()
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

        if (!\class_exists(Form::class)) {
            throw new \LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
        }

        $container->set('form.resolved_type_factory', new Definition(ResolvedFormTypeFactory::class))->autowire([ResolvedFormTypeFactoryInterface::class]);
        $container->autowire('form.registry', new Definition(FormRegistry::class, [[new Reference('form.extension')]]));
        $container->autowire('form.factory', new Definition(FormFactory::class));
        $container->autowire('form.extension', new Definition(DependencyInjectionExtension::class));
        $container->autowire($formChoiceId = 'form.choice_list_factory.default', new Definition(DefaultChoiceListFactory::class));

        if ($container->hasExtension(PropertyAccessorExtension::class) || \class_exists(PropertyAccess::class)) {
            $container->autowire($formChoiceId = 'form.choice_list_factory.property_access', new Definition(PropertyAccessDecorator::class, [new Reference('form.choice_list_factory.default')]));
            $container->autowire('form.type.form', new Definition(FormType::class))->tag('form.type');
        }

        $container->autowire('form.choice_list_factory.cached', new Definition(CachingFactoryDecorator::class, [new Reference($formChoiceId)]));
        $container->alias('form.choice_list_factory', 'form.choice_list_factory.cached');

        $container->autowire('form.server_params', new Definition(ServerParams::class));
        $container->autowire('form.type.choice', new Definition(ChoiceType::class, [new Reference('form.choice_list_factory')]))->tag('form.type');
        $container->autowire('form.type.file', new Definition(FileType::class))->tag('form.type');
        $container->autowire('form.type.color', new Definition(ColorType::class))->tag('form.type');
        $container->autowire('form.type_extension.repeated.validator', new Definition(RepeatedTypeValidatorExtension::class))->tag('form.type_extension');
        $container->autowire('form.type_extension.form.http_foundation', new Definition(FormTypeHttpFoundationExtension::class))->tag('form.type_extension');
        $container->autowire('form.type_extension.submit.validator', new Definition(SubmitTypeValidatorExtension::class))->tag('form.type_extension', ['extended-type' => SubmitType::class]);
        $container->autowire('form.type_extension.form.transformation_failure_handling', new Definition(TransformationFailureExtension::class))->tag('form.type_extension', ['extended-type' => FormType::class]);

        if ($container->hasExtension(ValidatorExtension::class)) {
            $container->getExtensionBuilder()->modifyConfig(ValidatorExtension::class, ['enabled' => true], FrameworkExtension::CONFIG_CALL);
            $container->set('form.type_guesser.validator', new Definition(ValidatorTypeGuesser::class))->tag('form.type_guesser');
            $container->autowire('form.type_extension.form.validator', new Definition(FormTypeValidatorExtension::class, [1 => false]))->tag('form.type_extension', ['extended-type' => FormType::class]);
        } else {
            $container->parameters['validator.translation_domain'] = 'validators';
        }

        if ($container->hasExtension(TranslatorExtension::class)) {
            $container->autowire('form.type_extension.upload.validator', new Definition(UploadValidatorExtension::class, [1 => '%validator.translation_domain%']))->tag('form.type_extension');
        }

        if ($configs['csrf_protection']['enabled']) {
            $container->autowire('form.type_extension.csrf', new Definition(FormTypeCsrfExtension::class))->tag('form.type_extension');

            $container->parameters['form.type_extension.csrf.enabled'] = true;
            $container->parameters['form.type_extension.csrf.field_name'] = $configs['form']['csrf_protection']['field_name'];
        } else {
            $container->parameters['form.type_extension.csrf.enabled'] = false;
        }

        if ($container instanceof KernelInterface && $container->isRunningInConsole()) {
            $container->set('console.command.form_debug', new Definition(DebugCommand::class))->tag('console.command');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $container->definition('form.extension')->args([
            $this->processFormTypes($container),
            $this->processFormTypeExtensions($container),
            $this->processFormTypeGuessers($container),
        ]);

        if (!$container->typed(RequestHandlerInterface::class)) {
            $container->autowire('form.type_extension.form.request_handler', new Definition(HttpFoundationRequestHandler::class));
        }
    }

    private function processFormTypes(AbstractContainer $container): Statement
    {
        // Get service locator argument
        $servicesMap = [];
        $namespaces = ['Symfony\Component\Form\Extension\Core\Type' => true];

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->tagged('form.type') as $serviceId => $tag) {
            // Add form type service to the service locator
            $serviceDefinition = $container->definition($serviceId);
            $servicesMap[$formType = $serviceDefinition->getEntity()] = new Reference($serviceId);
            $namespaces[\substr($formType, 0, \strrpos($formType, '\\'))] = true;
        }

        if ($container->has('console.command.form_debug')) {
            $container->definition('console.command.form_debug')->args([1 => \array_keys($namespaces), 2 => \array_keys($servicesMap)]);
        }

        return new Statement(ServiceLocator::class, $servicesMap);
    }

    private function processFormTypeExtensions(AbstractContainer $container): array
    {
        $typeExtensions = $typeExtensionsClasses = [];

        foreach ($container->tagged('form.type_extension') as $serviceId => $tag) {
            $serviceDefinition = $container->definition($serviceId);
            $typeExtensionClass = $container->parameter($serviceDefinition->getEntity());

            if (isset($tag['extended_type'])) {
                $typeExtensions[$tag['extended_type']][] = new Reference($serviceId);
                $typeExtensionsClasses[] = $typeExtensionClass;
            } else {
                $extendsTypes = false;

                $typeExtensionsClasses[] = $typeExtensionClass;

                foreach ($typeExtensionClass::getExtendedTypes() as $extendedType) {
                    $typeExtensions[$extendedType][] = new Reference($serviceId);
                    $extendsTypes = true;
                }

                if (!$extendsTypes) {
                    throw new \InvalidArgumentException(\sprintf('The getExtendedTypes() method for service "%s" does not return any extended types.', $serviceId));
                }
            }
        }

        if ($container->has('console.command.form_debug')) {
            $container->definition('console.command.form_debug')->arg(3, $typeExtensionsClasses);
        }

        return $typeExtensions;
    }

    private function processFormTypeGuessers(AbstractContainer $container): array
    {
        $guessers = $guessersClasses = [];

        foreach ($container->tagged('form.type_guesser') as $serviceId => $tags) {
            $guessers[] = new Reference($serviceId);

            $serviceDefinition = $container->definition($serviceId);
            $guessersClasses[] = $serviceDefinition->getEntity();
        }

        if ($container->has('console.command.form_debug')) {
            $container->definition('console.command.form_debug')->arg(4, $guessersClasses);
        }

        return $guessers;
    }
}
