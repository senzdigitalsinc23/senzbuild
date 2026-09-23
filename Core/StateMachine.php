<?php
declare(strict_types=1);

namespace App\Core;

/**
 * State Machine — define and manage state transitions for entities.
 *
 * Usage:
 *   $machine = new StateMachine('order', [
 *       'states'  => ['pending', 'paid', 'shipped', 'delivered', 'cancelled'],
 *       'transitions' => [
 *           'pay'      => ['from' => 'pending',        'to' => 'paid'],
 *           'ship'     => ['from' => 'paid',           'to' => 'shipped'],
 *           'deliver'  => ['from' => 'shipped',        'to' => 'delivered'],
 *           'cancel'   => ['from' => ['pending', 'paid'], 'to' => 'cancelled'],
 *       ],
 *   ]);
 *
 *   $machine->can('pay', 'pending');    // true
 *   $machine->transition('pay', 'pending'); // 'paid'
 *   $machine->getCurrent();             // 'paid'
 */
class StateMachine
{
    protected string $name;
    protected array $states;
    protected array $transitions;
    protected string $currentState;

    /**
     * @param string $name Machine name
     * @param array $config Machine configuration
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->states = $config['states'] ?? [];
        $this->transitions = $config['transitions'] ?? [];
        $this->currentState = $config['initial_state'] ?? ($this->states[0] ?? 'unknown');
    }

    /**
     * Get the current state.
     */
    public function getCurrent(): string
    {
        return $this->currentState;
    }

    /**
     * Check if a transition is valid from the current state.
     */
    public function can(string $transition): bool
    {
        $trans = $this->transitions[$transition] ?? null;
        if ($trans === null) {
            return false;
        }
        $from = $trans['from'] ?? [];
        $from = is_array($from) ? $from : [$from];
        return in_array($this->currentState, $from, true);
    }

    /**
     * Check if a transition is valid from a specific state.
     */
    public function canFrom(string $transition, string $state): bool
    {
        $trans = $this->transitions[$transition] ?? null;
        if ($trans === null) {
            return false;
        }
        $from = $trans['from'] ?? [];
        $from = is_array($from) ? $from : [$from];
        return in_array($state, $from, true);
    }

    /**
     * Execute a transition, changing the state.
     *
     * @return string The new state
     * @throws \InvalidArgumentException If transition is not allowed
     */
    public function transition(string $transition): string
    {
        if (!$this->can($transition)) {
            $trans = $this->transitions[$transition] ?? [];
            $from = $trans['from'] ?? [];
            $toState = $trans['to'] ?? 'unknown';
            throw new \InvalidArgumentException(
                "Cannot transition from '{$this->currentState}' to '{$toState}' via '{$transition}'"
            );
        }

        $this->currentState = $this->transitions[$transition]['to'];
        return $this->currentState;
    }

    /**
     * Get all possible transitions from the current state.
     */
    public function getAvailableTransitions(): array
    {
        $allowed = [];
        foreach (array_keys($this->transitions) as $transition) {
            if ($this->can($transition)) {
                $allowed[] = $transition;
            }
        }
        return $allowed;
    }

    /**
     * Get all defined states.
     */
    public function getStates(): array
    {
        return $this->states;
    }

    /**
     * Get the machine name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if a state exists.
     */
    public function hasState(string $state): bool
    {
        return in_array($state, $this->states, true);
    }

    /**
     * Reset to initial state.
     */
    public function reset(): void
    {
        $this->currentState = $this->states[0] ?? 'unknown';
    }
}

/**
 * State Machine Registry — manages multiple state machines.
 */
class StateMachineRegistry
{
    protected static array $machines = [];

    public static function register(string $name, StateMachine $machine): void
    {
        self::$machines[$name] = $machine;
    }

    public static function get(string $name): ?StateMachine
    {
        return self::$machines[$name] ?? null;
    }

    public static function has(string $name): bool
    {
        return isset(self::$machines[$name]);
    }

    public static function reset(): void
    {
        self::$machines = [];
    }
}
