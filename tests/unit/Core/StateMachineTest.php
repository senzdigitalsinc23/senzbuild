<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\StateMachine;
use App\Core\StateMachineRegistry;

class StateMachineTest extends TestCase
{
    public function test_initial_state(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid', 'shipped', 'delivered'],
            'transitions' => [
                'pay'     => ['from' => 'pending', 'to' => 'paid'],
                'ship'    => ['from' => 'paid',    'to' => 'shipped'],
                'deliver' => ['from' => 'shipped', 'to' => 'delivered'],
            ],
        ]);

        $this->assertSame('pending', $machine->getCurrent());
    }

    public function test_can_transition(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid', 'shipped'],
            'transitions' => [
                'pay'  => ['from' => 'pending', 'to' => 'paid'],
                'ship' => ['from' => 'paid',    'to' => 'shipped'],
            ],
        ]);

        $this->assertTrue($machine->can('pay'));
        $this->assertFalse($machine->can('ship'));
    }

    public function test_transition_changes_state(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid'],
            'transitions' => ['pay' => ['from' => 'pending', 'to' => 'paid']],
        ]);

        $newState = $machine->transition('pay');
        $this->assertSame('paid', $newState);
        $this->assertSame('paid', $machine->getCurrent());
    }

    public function test_invalid_transition_throws(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid'],
            'transitions' => [
                'pay'    => ['from' => 'pending', 'to' => 'paid'],
                'refund' => ['from' => 'paid',    'to' => 'pending'],
            ],
        ]);

        $machine->transition('pay');
        $this->expectException(\InvalidArgumentException::class);
        $machine->transition('pay'); // already paid, can't pay again
    }

    public function test_get_available_transitions(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid', 'shipped'],
            'transitions' => [
                'pay'  => ['from' => 'pending', 'to' => 'paid'],
                'ship' => ['from' => 'paid',    'to' => 'shipped'],
            ],
        ]);

        $this->assertSame(['pay'], $machine->getAvailableTransitions());

        $machine->transition('pay');
        $this->assertSame(['ship'], $machine->getAvailableTransitions());
    }

    public function test_from_state(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid'],
            'transitions' => ['cancel' => ['from' => ['pending', 'paid'], 'to' => 'cancelled']],
        ]);

        $this->assertTrue($machine->canFrom('cancel', 'pending'));
        $this->assertTrue($machine->canFrom('cancel', 'paid'));
        $this->assertFalse($machine->canFrom('cancel', 'shipped'));
    }

    public function test_reset(): void
    {
        $machine = new StateMachine('order', [
            'states' => ['pending', 'paid'],
            'transitions' => ['pay' => ['from' => 'pending', 'to' => 'paid']],
        ]);

        $machine->transition('pay');
        $machine->reset();
        $this->assertSame('pending', $machine->getCurrent());
    }

    public function test_registry(): void
    {
        StateMachineRegistry::reset();

        $orderMachine = new StateMachine('order', [
            'states' => ['pending', 'paid'],
            'transitions' => ['pay' => ['from' => 'pending', 'to' => 'paid']],
        ]);
        $paymentMachine = new StateMachine('payment', [
            'states' => ['pending', 'completed'],
            'transitions' => ['complete' => ['from' => 'pending', 'to' => 'completed']],
        ]);

        StateMachineRegistry::register('order', $orderMachine);
        StateMachineRegistry::register('payment', $paymentMachine);

        $this->assertTrue(StateMachineRegistry::has('order'));
        $this->assertFalse(StateMachineRegistry::has('nonexistent'));
        $this->assertSame($orderMachine, StateMachineRegistry::get('order'));
    }
}
