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

namespace Flange\Handler;

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

        /** @var callable(object) $listener */
        foreach ($this->getListenersForEvent($event) as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                break;
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
        if (null === $calledListeners = &$this->calledEvents[$event::class] ?? null) {
            if (empty($listeners = $this->listeners[$event::class] ?? [])) {
                return [];
            }

            \krsort($listeners); // Sort Listeners by priority.
            $calledListeners = \array_merge(...$listeners);
        }

        yield from $calledListeners;
    }

    /**
     * Attaches listener to corresponding event based on the type-hint used for the event argument.
     *
     * @param callable $listener Any callable could be used be it a closure or invokable object
     * @param int      $priority The higher this value, the earlier an event listener will be triggered in the chain (defaults to 0)
     */
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
        unset($this->calledEvents[$eventClass]);
    }

    /**
     * Checks if listeners exist for an event, else in general if event name is null.
     */
    public function hasListener(string $eventClass = null): bool
    {
        if (null !== $eventClass) {
            return !empty($this->listeners[$eventClass]);
        }

        foreach ($this->listeners as $eventListeners) {
            if ($eventListeners) {
                return true;
            }
        }

        return false;
    }
}
