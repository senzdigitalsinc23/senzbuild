<?php
declare(strict_types=1);

namespace App\Core;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * PSR-14 compliant event dispatcher.
 *
 * Bridges the framework's custom event system with PSR-14 standard.
 * Events are PHP objects; listeners are callables that accept the event.
 *
 * Usage (PSR-14):
 *   $dispatcher->dispatch(new UserRegistered($user));
 *
 * Usage (legacy):
 *   $dispatcher->dispatch('user.registered', $user);
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, callable[]> Event name => list of listeners
     */
    protected array $listeners = [];

    /**
     * @var Queue
     */
    protected ?Queue $queue;

    public function __construct(?Queue $queue = null)
    {
        $this->queue = $queue;
    }

    /**
     * Register a listener for an event (string or object class).
     *
     * @param string $event   Event name (legacy) or class name (PSR-14)
     * @param callable $listener Closure that receives the event
     */
    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    /**
     * PSR-14: Dispatch an event object.
     *
     * @param object $event The event to dispatch
     * @return object The (possibly modified) event
     */
    public function dispatch(object $event): object
    {
        $className = $event::class;

        // PSR-14: Dispatch by class name
        if (!empty($this->listeners[$className])) {
            foreach ($this->listeners[$className] as $listener) {
                $result = $listener($event);
                if ($result instanceof Job && $result instanceof ShouldQueue) {
                    $this->queue->dispatch($result::class, (array)$result);
                }
            }
        }

        // Also check for string-named events that match the class
        foreach ($this->listeners as $name => $listeners) {
            if (is_subclass_of($event, $name) || $event instanceof \Throwable && $name === \Throwable::class) {
                foreach ($listeners as $listener) {
                    $listener($event);
                }
            }
        }

        return $event;
    }

    /**
     * Legacy dispatch by event name string.
     *
     * @deprecated Use dispatch(object $event) instead (PSR-14)
     */
    public function dispatchEvent(string $event, mixed $payload = null): mixed
    {
        if (!empty($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $result = $listener($payload);
                if ($result instanceof Job && $result instanceof ShouldQueue) {
                    $this->queue->dispatch($result::class, (array)$result);
                }
            }
        }
        return $payload;
    }

    /**
     * Check if any listeners are registered for an event.
     */
    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Get all registered listeners for an event.
     *
     * @return callable[]
     */
    public function getListeners(string $event): array
    {
        return $this->listeners[$event] ?? [];
    }
}
