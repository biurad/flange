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

use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Flange\Handler\EventHandler;
use Rade\DI\Container;
use Rade\DI\Definition;
use Rade\DI\Definitions\Reference;
use Rade\DI\Exceptions\NotFoundServiceException;
use Rade\DI\Exceptions\ServiceCreationException;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Doctrine, Symfony component, and default event dispatcher extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class EventDispatcherExtension implements AliasedInterface, BootExtensionInterface, ConfigurationInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'events_dispatcher';
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(__CLASS__);

        $treeBuilder->getRootNode()
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
                ->ifString()->then(fn ($v) => ['dispatcher_class' => $v])
            ->end()
            ->children()
                ->scalarNode('dispatcher_class')->defaultValue(EventHandler::class)->end()
                ->arrayNode('doctrine')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('event_listeners_tag')->defaultValue('doctrine.event_listener')->end()
                        ->scalarNode('event_subscribers_tag')->defaultValue('doctrine.event_subscriber')->end()
                    ->end()
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
        if (isset($configs['dispatcher_class'])) {
            $dispatcher = $configs['dispatcher_class'];

            if (!(\is_subclass_of($dispatcher, EventDispatcherInterface::class) || $dispatcher === EventHandler::class)) {
                throw new ServiceCreationException(\sprintf('The service "%s" must implement "%s" or "%s".', $dispatcher, EventDispatcherInterface::class, EventHandler::class));
            }

            $container->set('events.dispatcher', new Definition($dispatcher))->typed();
        }

        if (true === ($configs['doctrine']['enabled'] ?? false)) {
            if (!\class_exists(EventManager::class)) {
                throw new \LogicException('Doctrine event manager is not installed. Try running "composer require doctrine/event-manager".');
            }

            $container->set('doctrine.event_manager', new Definition(EventManager::class))->typed();
            $container->parameters[__CLASS__] = $configs['doctrine'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Container $container): void
    {
        if ($container->has('events.dispatcher')) {
            if ($container->shared('events.dispatcher')) {
                $container->removeShared('events.dispatcher');
            }

            if (\is_subclass_of(($dispatcher = $container->definition('events.dispatcher'))->getEntity(), EventDispatcherInterface::class)) {
                $eventSubscribers = \array_merge($container->tagged('event_subscriber'), $container->findBy(EventSubscriberInterface::class));
            }

            $this->doEventsRegister($container, $dispatcher, 'event_listener', ['event_subscriber', $eventSubscribers ?? []]);
        }

        if (isset($container->parameters[__CLASS__])) {
            $dispatcher = $container->definition('doctrine.event_manager') ?? throw new NotFoundServiceException('Doctrine event manager might have been removed from container.');
            $eventSubscribers = \array_merge($container->tagged($tag = $container->parameters[__CLASS__]['event_subscribers_tag']), $container->findBy(EventSubscriber::class));

            $this->doEventsRegister($container, $dispatcher, $container->parameters[__CLASS__]['event_listeners_tag'], [$tag, $eventSubscribers]);
            unset($container->parameters[__CLASS__]);
        }
    }

    private function doEventsRegister(Container $container, Definition $eventDispatcher, string $listenerTag, array $eventSubscribers)
    {
        [$subscriberTag, $eventSubscribers] = $eventSubscribers;

        foreach ($container->tagged($listenerTag) as $id => $event) {
            $event = \is_array($event) ? $event : (\is_string($event) ? ['event' => $event] : []);
            $priority = $event['priority'] ?? 0;

            if (!isset($event['event'])) {
                if ($container->definition($id)->tagged($subscriberTag)) {
                    continue;
                }

                $event['method'] = $event['method'] ?? '__invoke';
                $event['event'] = $this->getEventFromTypeDeclaration($container, $id, $event['method'], $listenerTag);
            }

            if (!isset($event['method'])) {
                $event['method'] = 'on'.\preg_replace_callback([
                    '/(?<=\b|_)[a-z]/i',
                    '/[^a-z0-9]/i',
                ], fn ($matches) => \strtoupper($matches[0]), $event['event']);
                $event['method'] = \preg_replace('/[^a-z0-9]/i', '', $event['method']);

                if (null !== ($class = $container->definition($id)->getEntity()) && ($r = new \ReflectionClass($class, false)) && !$r->hasMethod($event['method']) && $r->hasMethod('__invoke')) {
                    $event['method'] = '__invoke';
                }
            }
            $dispatchers = isset($event['dispatcher']) ? (is_array($event['dispatcher']) ? $event['dispatcher'] : [$event['dispatcher']]) : [];

            if ([true] === $dispatchers || empty($dispatchers)) {
                $dispatchers = [$eventDispatcher];
            }

            foreach ($dispatchers as $dispatcherDefinition) {
                if (\is_string($dispatcherDefinition) && $container->has($dispatcherDefinition)) {
                    $dispatcherDefinition = $container->definition($dispatcherDefinition);
                } elseif (!$dispatcherDefinition instanceof Definition) {
                    throw new NotFoundServiceException(\sprintf('Event dispatcher(s) provided for service "%s" does not exist.', $id));
                }

                if (\is_subclass_of($dispatcherDefinition->getEntity(), EventManager::class)) {
                    $dispatcherDefinition->bind('addEventListener', [$event, new Reference($id)]);
                    continue;
                }

                $this->addEventListener($container, $dispatcherDefinition, [$id, $priority, $event]);
            }
        }

        foreach ($eventSubscribers as $id => $dispatcher) {
            [$id, $dispatchers] = \is_int($id) ? [$dispatcher, []] : [$id, \is_array($dispatcher) ? $dispatcher : [$dispatcher]];
            $def = $container->definition($id);

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            if (!$r = new \ReflectionClass($class = $def->getEntity())) {
                throw new \InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if (!$r->isSubclassOf($type = 'event_subscriber' === $subscriberTag ? EventSubscriberInterface::class : EventSubscriber::class)) {
                throw new \InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, $type));
            }

            if ([true] === $dispatchers || empty($dispatchers)) {
                $dispatchers = [$eventDispatcher];
            }

            foreach ($dispatchers as $dispatcherDefinition) {
                if (\is_string($dispatcherDefinition) && $container->has($dispatcherDefinition)) {
                    $dispatcherDefinition = $container->definition($dispatcherDefinition);
                } elseif (!$dispatcherDefinition instanceof Definition) {
                    throw new NotFoundServiceException(\sprintf('Event dispatcher(s) provided for service "%s" does not exist.', $id));
                }

                if (EventSubscriber::class === $type) {
                    $dispatcherDefinition->bind('addEventSubscriber', new Reference($id));
                    continue;
                }

                foreach ($r->name::getSubscribedEvents() as $event => $params) {
                    if (\is_string($params)) {
                        $this->addEventListener($container, $dispatcherDefinition, [$id, 0, ['method' => $params, 'event' => $event]]);
                    } elseif (\is_string($params[0])) {
                        $this->addEventListener($container, $dispatcherDefinition, [$id, $params[1] ?? 0, ['method' => $params[0], 'event' => $event]]);
                    } else {
                        foreach ($params as $listener) {
                            $this->addEventListener($container, $dispatcherDefinition, [$id, $listener[1] ?? 0, ['method' => $listener[0], 'event' => $event]]);
                        }
                    }
                }
            }
        }
    }

    private function getEventFromTypeDeclaration(Container $container, string $id, string $method, string $listenerTag): string
    {
        if (
            null === ($class = $container->definition($id)->getEntity())
            || !($r = new \ReflectionClass($class))
            || !$r->hasMethod($method)
            || 1 > ($m = $r->getMethod($method))->getNumberOfParameters()
            || !($type = $m->getParameters()[0]->getType()) instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || Event::class === ($name = $type->getName())
        ) {
            throw new \InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "%s" tags.', $id, $listenerTag));
        }

        return $name;
    }

    private function addEventListener(Container $container, Definition $dispatcherDefinition, array $data): void
    {
        [$id, $priority, $event] = $data;

        if ($dispatcherDefinition instanceof Definition) {
            $dispatcherDefinition->bind('addListener', [$event['event'], [new Reference($id), $event['method']], $priority]);
        } else {
            $dispatcherDefinition->addListener($event['event'], [$container->get($id), $event['method']], $priority);
        }
    }
}
