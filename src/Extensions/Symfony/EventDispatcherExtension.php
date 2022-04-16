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
use Rade\DI\Definitions\DefinitionInterface;
use Rade\DI\Definitions\Reference;
use Rade\DI\Extensions\AliasedInterface;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Symfony component event dispatcher extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class EventDispatcherExtension implements AliasedInterface, BootExtensionInterface, ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return 'event_dispatcher';
    }

    /**
     * {@inheritdoc}
     */
    public function register(AbstractContainer $container, array $configs): void
    {
        $globalDispatcher = $container->definition($id = 'events.dispatcher');

        if (
            $globalDispatcher instanceof EventDispatcherInterface ||
            ($globalDispatcher instanceof DefinitionInterface && \is_subclass_of($globalDispatcher->getEntity(), EventDispatcherInterface::class))
        ) {
            return;
        }

        $container->removeDefinition($id);
        $container->set($inner = $id . '.inner', $globalDispatcher);
        $container->tag($inner, 'container.decorated_services');
        $container->set('events.dispatcher', new Definition(EventDispatcher::class))->autowire();
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $globalDispatcherDefinition = $container->definition('events.dispatcher');
        $eventSubscribers = \array_merge($container->tagged('event_subscriber'), $container->findBy(EventSubscriberInterface::class));

        foreach ($container->tagged('event_listener') as $id => $events) {
            foreach ($events as $event) {
                $priority = $event['priority'] ?? 0;

                if (!isset($event['event'])) {
                    if ($container->definition($id)->tagged('event_subscriber')) {
                        continue;
                    }

                    $event['method'] = $event['method'] ?? '__invoke';
                    $event['event'] = $this->getEventFromTypeDeclaration($container, $id, $event['method']);
                }

                $event['event'] = $aliases[$event['event']] ?? $event['event'];

                if (!isset($event['method'])) {
                    $event['method'] = 'on' . \preg_replace_callback([
                        '/(?<=\b|_)[a-z]/i',
                        '/[^a-z0-9]/i',
                    ], function ($matches) {
                        return \strtoupper($matches[0]);
                    }, $event['event']);
                    $event['method'] = \preg_replace('/[^a-z0-9]/i', '', $event['method']);

                    if (null !== ($class = $container->definition($id)->getEntity()) && ($r = new \ReflectionClass($class, false)) && !$r->hasMethod($event['method']) && $r->hasMethod('__invoke')) {
                        $event['method'] = '__invoke';
                    }
                }

                $this->addEventListener($container, isset($event['dispatcher']) ? $container->definition($event['dispatcher']) : $globalDispatcherDefinition, [$id, $priority, $event]);
            }
        }

        foreach ($eventSubscribers as $id => $dispatcher) {
            [$id, $dispatcher] = \is_int($id) ? [$dispatcher, null] : [$id, $dispatcher];
            $def = $container->definition($id);

            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $def->getEntity();

            if (!$r = new \ReflectionClass($class)) {
                throw new \InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if (!$r->isSubclassOf(EventSubscriberInterface::class)) {
                throw new \InvalidArgumentException(\sprintf('Service "%s" must implement interface "%s".', $id, EventSubscriberInterface::class));
            }
            $class = $r->name;
            $dispatcherDefinitions = [];

            if (\is_string($dispatcher)) {
                $dispatcherDefinitions[$dispatcher] = $container->definition($dispatcher);
            }

            if (!$dispatcherDefinitions) {
                $dispatcherDefinitions = [$globalDispatcherDefinition];
            }

            foreach ($dispatcherDefinitions as $dispatcherDefinition) {
                foreach ($class::getSubscribedEvents() as $event => $params) {
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

    private function getEventFromTypeDeclaration(AbstractContainer $container, string $id, string $method): string
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
            throw new \InvalidArgumentException(\sprintf('Service "%s" must define the "event" attribute on "event_listener" tags.', $id));
        }

        return $name;
    }

    /**
     * @param Definition|EventDispatcherInterface $dispatcherDefinition
     */
    private function addEventListener(AbstractContainer $container, object $dispatcherDefinition, array $data): void
    {
        [$id, $priority, $event] = $data;

        if ($dispatcherDefinition instanceof DefinitionInterface) {
            $dispatcherDefinition->bind('addListener', [$event['event'], [new Reference($id), $event['method']], $priority]);
        } else {
            $dispatcherDefinition->addListener($event['event'], [$container->get($id), $event['method']], $priority);
        }
    }
}
