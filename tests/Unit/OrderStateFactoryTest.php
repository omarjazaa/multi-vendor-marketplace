<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\UnsupportedOrderStateException;
use App\States\CancelledState;
use App\States\DeliveredState;
use App\States\OrderState;
use App\States\OrderStateFactory;
use App\States\PaidState;
use App\States\PendingState;
use App\States\RefundedState;
use App\States\ShippedState;
use stdClass;
use Tests\TestCase;

class OrderStateFactoryTest extends TestCase
{
    public function test_it_resolves_every_configured_status_to_its_own_state(): void
    {
        $factory = app(OrderStateFactory::class);

        $this->assertInstanceOf(PendingState::class, $factory->make('pending'));
        $this->assertInstanceOf(PaidState::class, $factory->make(OrderStatus::PAID));
        $this->assertInstanceOf(ShippedState::class, $factory->make('shipped'));
        $this->assertInstanceOf(DeliveredState::class, $factory->make('delivered'));
        $this->assertInstanceOf(RefundedState::class, $factory->make('refunded'));
        $this->assertInstanceOf(CancelledState::class, $factory->make('cancelled'));

        $this->assertSame('pending', $factory->make('pending')->status()->value);
        $this->assertSame('refunded', $factory->make('refunded')->status()->value);
    }

    public function test_the_supported_statuses_are_exactly_the_configured_keys(): void
    {
        $this->assertSame(
            array_keys((array) config('marketplace.orders.states')),
            app(OrderStateFactory::class)->supported(),
        );
    }

    public function test_status_names_are_normalised_before_the_lookup(): void
    {
        $this->assertInstanceOf(
            PaidState::class,
            app(OrderStateFactory::class)->make('  PAID  '),
        );
    }

    public function test_an_unknown_status_is_rejected_and_names_the_supported_ones(): void
    {
        try {
            app(OrderStateFactory::class)->make('teleported');

            $this->fail('An unregistered status must be rejected.');
        } catch (UnsupportedOrderStateException $exception) {
            $this->assertSame('teleported', $exception->status);
            $this->assertSame(
                ['pending', 'paid', 'shipped', 'delivered', 'refunded', 'cancelled'],
                $exception->supported,
            );
            $this->assertStringContainsString('not supported', $exception->getMessage());
            $this->assertStringContainsString('pending, paid', $exception->getMessage());
        }
    }

    public function test_a_misconfigured_state_class_is_rejected(): void
    {
        config()->set('marketplace.orders.states.pending', stdClass::class);

        try {
            app(OrderStateFactory::class)->make('pending');

            $this->fail('A class that is not a state must be rejected.');
        } catch (UnsupportedOrderStateException $exception) {
            $this->assertSame('pending', $exception->status);
            $this->assertStringContainsString('is misconfigured', $exception->getMessage());
            $this->assertStringContainsString(stdClass::class, $exception->getMessage());
        }
    }

    public function test_a_new_state_is_registered_through_config_alone(): void
    {
        config()->set('marketplace.orders.states.smoke', StateFake::class);

        $factory = app(OrderStateFactory::class);

        $this->assertInstanceOf(StateFake::class, $factory->make('smoke'));
        $this->assertContains('smoke', $factory->supported());
    }

    /**
     * The defensive line: a config edit that forgets an enum case, a state
     * registration or a matrix row fails here rather than orphaning a status
     * at runtime.
     */
    public function test_the_enum_the_states_and_the_transition_matrix_stay_in_sync(): void
    {
        $factory = app(OrderStateFactory::class);
        $enum = array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases());
        $states = array_keys((array) config('marketplace.orders.states'));
        $matrix = array_keys((array) config('marketplace.orders.transitions'));

        $this->assertEqualsCanonicalizing($enum, $states);
        $this->assertEqualsCanonicalizing($enum, $matrix);

        foreach ($states as $key) {
            $state = $factory->make($key);

            $this->assertSame($key, $state->status()->value, "The state for \"{$key}\" reports another status.");
            $this->assertSame(
                config("marketplace.orders.transitions.{$key}"),
                $state->allowedTransitions(),
                "The state \"{$key}\" diverges from its configured matrix row.",
            );
        }
    }

    /** Locks the intended graph — the config itself could be edited wrongly, which the sync test alone would not catch. */
    public function test_the_frozen_lifecycle_graph_is_exactly_as_specified(): void
    {
        $factory = app(OrderStateFactory::class);

        $this->assertSame(['paid', 'cancelled'], $factory->make('pending')->allowedTransitions());
        $this->assertSame(['shipped', 'cancelled', 'refunded'], $factory->make('paid')->allowedTransitions());
        $this->assertSame(['delivered'], $factory->make('shipped')->allowedTransitions());
        $this->assertSame(['refunded'], $factory->make('delivered')->allowedTransitions());
        $this->assertSame([], $factory->make('refunded')->allowedTransitions());
        $this->assertSame([], $factory->make('cancelled')->allowedTransitions());
    }
}

/** State used to prove a config-only registration works. */
class StateFake implements OrderState
{
    public function status(): OrderStatus
    {
        return OrderStatus::PENDING;
    }

    public function allowedTransitions(): array
    {
        return [];
    }
}
