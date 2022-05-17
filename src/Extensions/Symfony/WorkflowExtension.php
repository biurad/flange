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

use Rade\Commands\Symfony\WorkflowDumpCommand;
use Rade\DI\AbstractContainer;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowEvents;

use function Rade\DI\Loader\service;

/**
 * Symfony component workflow extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class WorkflowExtension implements AliasedInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'workflows';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->canBeEnabled()
            ->beforeNormalization()
                ->always(function ($v) {
                    if (\is_array($v) && true === $v['enabled']) {
                        $workflows = $v;
                        unset($workflows['enabled']);

                        if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                            $workflows = [];
                        }

                        if (1 === \count($workflows) && isset($workflows['workflows']) && !\array_is_list($workflows['workflows']) && !empty(\array_diff(\array_keys($workflows['workflows']), ['audit_trail', 'type', 'marking_store', 'supports', 'support_strategy', 'initial_marking', 'places', 'transitions']))) {
                            $workflows = $workflows['workflows'];
                        }

                        foreach ($workflows as $key => $workflow) {
                            if (isset($workflow['enabled']) && false === $workflow['enabled']) {
                                throw new \LogicException(\sprintf('Cannot disable a single workflow. Remove the configuration for the workflow "%s" instead.', $workflow['name']));
                            }

                            unset($workflows[$key]['enabled']);
                        }

                        $v = [
                            'enabled' => true,
                            'workflows' => $workflows,
                        ];
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('workflows')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->fixXmlConfig('support')
                        ->fixXmlConfig('place')
                        ->fixXmlConfig('transition')
                        ->fixXmlConfig('event_to_dispatch', 'events_to_dispatch')
                        ->children()
                            ->arrayNode('audit_trail')
                                ->canBeEnabled()
                            ->end()
                            ->enumNode('type')
                                ->values(['workflow', 'state_machine'])
                                ->defaultValue('state_machine')
                            ->end()
                            ->arrayNode('marking_store')
                                ->children()
                                    ->enumNode('type')
                                        ->values(['method'])
                                    ->end()
                                    ->scalarNode('property')
                                        ->defaultValue('marking')
                                    ->end()
                                    ->scalarNode('service')
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('supports')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(function ($v) {
                                        return [$v];
                                    })
                                ->end()
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                    ->validate()
                                        ->ifTrue(function ($v) {
                                            return !\class_exists($v) && !\interface_exists($v, false);
                                        })
                                        ->thenInvalid('The supported class or interface "%s" does not exist.')
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('support_strategy')
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('initial_marking')
                                ->beforeNormalization()->castToArray()->end()
                                ->defaultValue([])
                                ->prototype('scalar')->end()
                            ->end()
                            ->variableNode('events_to_dispatch')
                                ->defaultValue(null)
                                ->validate()
                                    ->ifTrue(function ($v) {
                                        if (null === $v) {
                                            return false;
                                        }

                                        if (!\is_array($v)) {
                                            return true;
                                        }

                                        foreach ($v as $value) {
                                            if (!\is_string($value)) {
                                                return true;
                                            }

                                            if (\class_exists(WorkflowEvents::class) && !\in_array($value, WorkflowEvents::ALIASES)) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    })
                                    ->thenInvalid('The value must be "null" or an array of workflow events (like ["workflow.enter"]).')
                                ->end()
                                ->info('Select which Transition events should be dispatched for this Workflow')
                                ->example(['workflow.enter', 'workflow.transition'])
                            ->end()
                            ->arrayNode('places')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function ($places) {
                                        // It's an indexed array of shape  ['place1', 'place2']
                                        if (isset($places[0]) && \is_string($places[0])) {
                                            return \array_map(function (string $place) {
                                                return ['name' => $place];
                                            }, $places);
                                        }

                                        // It's an indexed array, we let the validation occur
                                        if (isset($places[0]) && \is_array($places[0])) {
                                            return $places;
                                        }

                                        foreach ($places as $name => $place) {
                                            if (\is_array($place) && \array_key_exists('name', $place)) {
                                                continue;
                                            }
                                            $place['name'] = $name;
                                            $places[$name] = $place;
                                        }

                                        return \array_values($places);
                                    })
                                ->end()
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->arrayNode('metadata')
                                            ->normalizeKeys(false)
                                            ->defaultValue([])
                                            ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                            ->prototype('variable')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('transitions')
                                ->beforeNormalization()
                                    ->always()
                                    ->then(function ($transitions) {
                                        // It's an indexed array, we let the validation occur
                                        if (isset($transitions[0]) && \is_array($transitions[0])) {
                                            return $transitions;
                                        }

                                        foreach ($transitions as $name => $transition) {
                                            if (\is_array($transition) && \array_key_exists('name', $transition)) {
                                                continue;
                                            }
                                            $transition['name'] = $name;
                                            $transitions[$name] = $transition;
                                        }

                                        return $transitions;
                                    })
                                ->end()
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->scalarNode('guard')
                                            ->cannotBeEmpty()
                                            ->info('An expression to block the transition')
                                            ->example('is_fully_authenticated() and is_granted(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')
                                        ->end()
                                        ->arrayNode('from')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) {
                                                    return [$v];
                                                })
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('scalar')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                        ->arrayNode('to')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) {
                                                    return [$v];
                                                })
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('scalar')
                                                ->cannotBeEmpty()
                                            ->end()
                                        ->end()
                                        ->arrayNode('metadata')
                                            ->normalizeKeys(false)
                                            ->defaultValue([])
                                            ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                            ->prototype('variable')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('metadata')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                ->prototype('variable')
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return $v['supports'] && isset($v['support_strategy']);
                            })
                            ->thenInvalid('"supports" and "support_strategy" cannot be used together.')
                        ->end()
                        ->validate()
                            ->ifTrue(function ($v) {
                                return !$v['supports'] && !isset($v['support_strategy']);
                            })
                            ->thenInvalid('"supports" or "support_strategy" should be configured.')
                        ->end()
                        ->beforeNormalization()
                                ->always()
                                ->then(function ($values) {
                                    // Special case to deal with XML when the user wants an empty array
                                    if (\array_key_exists('event_to_dispatch', $values) && null === $values['event_to_dispatch']) {
                                        $values['events_to_dispatch'] = [];
                                        unset($values['event_to_dispatch']);
                                    }

                                    return $values;
                                })
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

        if (!\class_exists(Workflow\Workflow::class)) {
            throw new \LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }

        $registryDefinition = $container->autowire('workflow.registry', service(Registry::class));
        $workflows = [];

        foreach ($configs['workflows'] as $name => $workflow) {
            $type = $workflow['type'];
            $workflowId = \sprintf('%s.%s', $type, $name);

            // Process Metadata (workflow + places (transition is done in the "create transition" block))
            $metadataStoreDefinition = service(Workflow\Metadata\InMemoryMetadataStore::class, [[], [], null]);

            if ($workflow['metadata']) {
                $metadataStoreDefinition->arg(0, $workflow['metadata']);
            }
            $placesMetadata = [];

            foreach ($workflow['places'] as $place) {
                if ($place['metadata']) {
                    $placesMetadata[$place['name']] = $place['metadata'];
                }
            }

            if ($placesMetadata) {
                $metadataStoreDefinition->arg(1, $placesMetadata);
            }

            // Create transitions
            $transitions = [];
            $guardsConfiguration = [];
            $transitionsMetadataDefinition = service(\SplObjectStorage::class);
            $transitionCounter = 0; // Global transition counter per workflow

            foreach ($workflow['transitions'] as $transition) {
                if ('workflow' === $type) {
                    $transitionDefinition = service(Workflow\Transition::class, [$transition['name'], $transition['from'], $transition['to']])->public(false);
                    $container->set($transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++), $transitionDefinition);
                    $transitions[] = new Reference($transitionId);

                    if (isset($transition['guard'])) {
                        $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guardsConfiguration[$eventName][] = new Statement(Workflow\EventListener\GuardExpression::class, [new Reference($transitionId), $transition['guard']]);
                    }

                    if ($transition['metadata']) {
                        $transitionsMetadataDefinition->bind('attach', [new Reference($transitionId), $transition['metadata']]);
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitionDefinition = service(Workflow\Transition::class, [$transition['name'], $from, $to])->public(false);
                            $container->set($transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++), $transitionDefinition);
                            $transitions[] = new Reference($transitionId);

                            if (isset($transition['guard'])) {
                                $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guardsConfiguration[$eventName][] = new Statement(Workflow\EventListener\GuardExpression::class, [new Reference($transitionId), $transition['guard']]);
                            }

                            if ($transition['metadata']) {
                                $transitionsMetadataDefinition->bind('attach', [new Reference($transitionId), $transition['metadata']]);
                            }
                        }
                    }
                }
            }
            $container->set($transitionMetaId = \sprintf('%s.transition_meta', $workflowId), $transitionsMetadataDefinition)->public(false);
            $metadataStoreDefinition->arg(2, new Reference($transitionMetaId))->public(false);
            $container->set($metaStoreId = \sprintf('%s.metadata_store', $workflowId), $metadataStoreDefinition);

            // Create places
            $places = \array_column($workflow['places'], 'name');
            $initialMarking = $workflow['initial_marking'] ?? [];

            // Create a Definition
            $definitionDefinition = new Statement(Workflow\Definition::class, [$places, $transitions, $initialMarking, new Reference($metaStoreId)]);
            $workflows[$workflowId] = $definitionDefinition;

            // Create MarkingStore
            if (isset($workflow['marking_store']['type'])) {
                $markingStoreDefinition = new Statement(Workflow\MarkingStore\MethodMarkingStore::class, ['state_machine' === $type, $workflow['marking_store']['property']]);
            } elseif (isset($workflow['marking_store']['service'])) {
                $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
            }

            // Create Workflow
            $workflowDefinition = new Statement('workflow' === $type ? Workflow\Workflow::class : Workflow\StateMachine::class, [
                0 => $definitionDefinition,
                1 => $markingStoreDefinition ?? null,
                3 => $name,
                4 => $workflow['events_to_dispatch'],
            ]);

            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supportedClassName) {
                    $strategyDefinition = new Statement(Workflow\SupportStrategy\InstanceOfSupportStrategy::class, [$supportedClassName]);
                    $registryDefinition->bind('addWorkflow', [$workflowDefinition, $strategyDefinition]);
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registryDefinition->bind('addWorkflow', [$workflowDefinition, new Reference($workflow['support_strategy'])]);
            }

            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled'] && $container->has('logger')) {
                $listener = service(Workflow\EventListener\AuditTrailListener::class, [new Reference('logger')]);
                $listener->tags([
                    'event_listener' => ['event' => \sprintf('workflow.%s.leave', $name), 'method' => 'onLeave'],
                    'event_listener' => ['event' => \sprintf('workflow.%s.transition', $name), 'method' => 'onTransition'],
                    'event_listener' => ['event' => \sprintf('workflow.%s.enter', $name), 'method' => 'onEnter'],
                ]);
                $container->set(\sprintf('.%s.listener.audit_trail', $workflowId), $listener)->public(false);
            }

            // Add Guard Listener
            if ($guardsConfiguration) {
                if (!\class_exists(ExpressionLanguage::class)) {
                    throw new \LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }

                if (!\class_exists(Security::class)) {
                    throw new \LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
                }

                $guard = service(Workflow\EventListener\GuardListener::class);

                $guard->args([
                    $guardsConfiguration,
                    new Statement(Workflow\EventListener\ExpressionLanguage::class),
                    new Reference('security.token_storage'),
                    new Reference('security.authorization_checker'),
                    new Reference('security.authentication.trust_resolver'),
                    new Reference('security.role_hierarchy'),
                    new Reference('?validator'),
                ]);

                foreach ($guardsConfiguration as $eventName => $config) {
                    $guard->tag('event_listener', ['event' => $eventName, 'method' => 'onTransition']);
                }

                $container->set(\sprintf('.%s.listener.guard', $workflowId), $guard);
                $container->parameters['workflow.has_guard_listeners'] = true;
            }
        }

        if ($container->has('console')) {
            $container->set('console.command.workflow_dump', service(WorkflowDumpCommand::class, [$workflows]))->tag('console.command');
        }
    }
}
