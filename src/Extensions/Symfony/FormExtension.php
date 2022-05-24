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
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Rade\DI\Extensions\RequiredPackagesInterface;
use Rade\DI\Extensions\Symfony\Form\HttpFoundationRequestHandler;
use Rade\DI\Services\ServiceLocator;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function Rade\DI\Loader\service;

/**
 * Symfony component form extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class FormExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface, RequiredPackagesInterface
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
                        ->booleanNode('enabled')->defaultTrue()->end()
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
    public function getRequiredPackages(): array
    {
        return [
            Form::class => 'symfony/form',
            PropertyAccess::class => 'symfony/property-access',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        if (!$configs['enabled']) {
            return;
        }

        $definitions = [
            'form.resolved_type_factory' => service(ResolvedFormTypeFactory::class)->autowire([ResolvedFormTypeFactoryInterface::class]),
            'form.registry' => service(FormRegistry::class, [new TaggedLocator('form.extension')])->autowire(),
            'form.factory' => service(FormFactory::class)->autowire(),
            'form.extension' => service(DependencyInjectionExtension::class)->autowire()->tag('form.extension'),
            'form.choice_list_factory.default' => service(DefaultChoiceListFactory::class)->public(false),
            'form.choice_list_factory.property_access' => service(PropertyAccessDecorator::class, [new Reference('form.choice_list_factory.default')])->public(false),
            'form.choice_list_factory.cached' => service(CachingFactoryDecorator::class, [new Reference('form.choice_list_factory.property_access')])->autowire(),
            'form.server_params' => service(ServerParams::class, [new Reference('request_stack')])->autowire(),
            'form.type.form' => service(FormType::class)->public(false)->tag('form.type'),
            'form.type.choice' => service(ChoiceType::class)->public(false)->tag('form.type'),
            'form.type.color' => service(ColorType::class)->public(false)->tag('form.type'),
            'form.type.file' => service(FileType::class)->public(false)->tag('form.type'),
            'form.type_extension.repeated.validator' => service(RepeatedTypeValidatorExtension::class)->public(false)->tag('form.type_extension'),
            'form.type_extension.submit.validator' => service(SubmitTypeValidatorExtension::class)->public(false)->tag('form.type_extension', ['extended-type' => SubmitType::class]),
            'form.type_extension.http_foundation' => service(FormTypeHttpFoundationExtension::class)->public(false)->tag('form.type_extension'),
            'form.type_extension.transformation_failure_handling' => service(TransformationFailureExtension::class)->public(false)->tag('form.type_extension', ['extended-type' => FormType::class]),
        ];

        if ($container->hasExtension(ValidatorExtension::class)) {
            $container->getExtensionBuilder()->modifyConfig(ValidatorExtension::class, ['enabled' => true], FrameworkExtension::CONFIG_CALL);
            $definitions['form.type_guesser.validator'] = service(ValidatorTypeGuesser::class)->public(false)->tag('form.type_guesser');
            $definitions['form.type_extension.validator'] = service(FormTypeValidatorExtension::class)->public(false)->tag('form.type_extension', ['extended-type' => FormType::class]);
        } else {
            $container->parameters['validator.translation_domain'] = 'validators';
        }

        if ($container->hasExtension(TranslatorExtension::class)) {
            $definitions['form.type_extension.upload.validator'] = service(UploadValidatorExtension::class, [1 => new Parameter('validator.translation_domain')])->public(false)->tag('form.type_extension');
        }

        if ($container->typed(CsrfTokenManagerInterface::class)) {
            $definitions['form.type_extension.csrf'] = service(FormTypeCsrfExtension::class)
                ->args([1 => $configs['csrf_protection']['enabled'], 2 => $configs['csrf_protection']['field_name']])
                ->tag('form.type_extension')
                ->public(false);
        }

        if ($container->has('console')) {
            $definitions['console.command.form_debug'] = service(DebugCommand::class)->tag('console.command', 'debug:form');
        }

        $container->multiple($definitions);
        $container->alias('form.choice_list_factory', 'form.choice_list_factory.cached');
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
