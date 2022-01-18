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
use Rade\DI\Definitions\DefinitionInterface;
use Rade\DI\Definitions\Reference;
use Rade\DI\Definitions\Statement;
use Rade\DI\Extensions\BootExtensionInterface;
use Rade\DI\Extensions\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Symfony component event dispatcher extension.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class EventDispatcherExtension implements BootExtensionInterface, ExtensionInterface
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
    }

    /**
     * {@inheritdoc}
     */
    public function boot(AbstractContainer $container): void
    {
        $globalDispatcherDefinition = $container->definition('events.dispatcher');
        $eventClass = $globalDispatcherDefinition instanceof DefinitionInterface ? $globalDispatcherDefinition->getEntity() : \get_class($globalDispatcherDefinition);

        if (!\is_subclass_of($eventClass, EventDispatcherInterface::class)) {
            return;
        }

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

                $dispatcherDefinition = $globalDispatcherDefinition;

                if (isset($event['dispatcher'])) {
                    $dispatcherDefinition = $container->definition($event['dispatcher']);
                }

                if ($dispatcherDefinition instanceof DefinitionInterface) {
                    $dispatcherDefinition->bind('addListener', [$event['event'], [new Statement(new Reference($id), [], true), $event['method']], $priority]);
                } else {
                    $dispatcherDefinition->addListener($event['event'], [$container->get($id), $event['method']], $priority);
                }
            }
        }

        foreach ($container->tagged('event_subscriber') as $id => $tags) {
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

            if (\is_array($tags)) {
                foreach ($tags as $attributes) {
                    if (!isset($attributes['dispatcher']) || isset($dispatcherDefinitions[$attributes['dispatcher']])) {
                        continue;
                    }

                    $dispatcherDefinitions[$attributes['dispatcher']] = $container->definition($attributes['dispatcher']);
                }
            }

            if (!$dispatcherDefinitions) {
                $dispatcherDefinitions = [$globalDispatcherDefinition];
            }

            foreach ($dispatcherDefinitions as $dispatcherDefinition) {
                if ($dispatcherDefinition instanceof EventDispatcherInterface) {
                    $dispatcherDefinition->addSubscriber($container->get($id));
                } else {
                    $dispatcherDefinition->bind('addSubscriber', [new Reference($id)]);
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
}
