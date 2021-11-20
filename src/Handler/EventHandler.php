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

namespace Rade\Handler;

use Psr\EventDispatcher\{EventDispatcherInterface, ListenerProviderInterface, StoppableEventInterface};

/**
 * A fully strict PSR 14 dispatcher and listener.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class EventHandler implements EventDispatcherInterface, ListenerProviderInterface
{
    private array $listeners = [], $calledEvents = [];

    /**
     * {@inheritdoc}
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;
        $listeners = $this->calledEvents[\get_class($event)] ?? $this->getListenersForEvent($event);

        /** @var callable(object) $listener */
        foreach ($listeners as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                return $event;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (!\array_key_exists($eventName = \get_class($event), $this->calledEvents)) {
            if (empty($listeners = $this->listeners[$eventName] ?? [])) {
                return [];
            }

            \krsort($listeners); // Sort Listeners by priority.

            foreach ($listeners as $calledListeners) {
                $this->calledEvents[$eventName] = $calledListeners;
            }
        }

        return $this->calledEvents[$eventName];
    }

    /**
     * Attaches listener to corresponding event based on the type-hint used for the event argument.
     *
     * @param callable $listener Any callable could be used be it a closure or invokable object
     * @param int $priority The higher this value, the earlier an event listener will be triggered in the chain (defaults to 0)
     */
    public function addListener(string $eventClass, $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
        unset($this->calledEvents[$eventClass]);
    }

    /**
     * Checks if listeners exist for an event, else in general if event name is null.
     */
    public function hasListeners(string $eventName = null): bool
    {
        if (null !== $eventName) {
            return !empty($this->listeners[$eventName]);
        }

        foreach ($this->listeners as $eventListeners) {
            if ($eventListeners) {
                return true;
            }
        }

        return false;
    }
}
