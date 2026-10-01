<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\Strategies\BankTransferStrategy;
use App\Services\Payments\Strategies\CardPaymentStrategy;
use App\Services\Payments\Strategies\CashOnDeliveryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStrategiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The simulated gateway declines unless a case explicitly opts in.
        config(['marketplace.payments.card.simulate_success' => false]);
    }

    public function test_cash_on_delivery_always_succeeds_and_leaves_the_order_pending(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $result = app(CashOnDeliveryStrategy::class)->pay($order);

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('COD-%06d', $order->id), $result->reference);
        $this->assertStringContainsString('Cash on delivery', (string) $result->message);
        // Still owed: only a vendor marking it paid settles a COD order.
        $this->assertSame(OrderStatus::PENDING, $order->fresh()?->status);
    }

    public function test_bank_transfer_issues_a_reference_and_waits_for_confirmation(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $result = app(BankTransferStrategy::class)->pay($order);

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('BANK-%06d', $order->id), $result->reference);
        $this->assertStringContainsString('unpaid until the transfer is confirmed', (string) $result->message);
        $this->assertSame(OrderStatus::PENDING, $order->fresh()?->status);
    }

    public function test_a_declined_card_reports_the_decline_and_issues_no_reference(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $result = app(CardPaymentStrategy::class)->pay($order);

        $this->assertFalse($result->successful);
        $this->assertNull($result->reference);
        $this->assertSame(config('marketplace.payments.card.decline_message'), $result->message);
        // A decline changes nothing about the order itself.
        $this->assertSame(OrderStatus::PENDING, $order->fresh()?->status);
        $this->assertNull($order->fresh()?->payment_reference);
    }

    public function test_an_approved_card_settles_the_order_through_the_transition_map(): void
    {
        config(['marketplace.payments.card.simulate_success' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $result = app(CardPaymentStrategy::class)->pay($order);

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('CARD-%06d', $order->id), $result->reference);
        $this->assertSame('Card payment approved.', $result->message);
        // pending -> paid is the configured lifecycle edge, not a raw write.
        $this->assertSame(OrderStatus::PAID, $order->fresh()?->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
    }

    public function test_an_approved_card_cannot_settle_an_order_the_lifecycle_forbids(): void
    {
        config(['marketplace.payments.card.simulate_success' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::CANCELLED]);

        try {
            app(CardPaymentStrategy::class)->pay($order);
            $this->fail('A cancelled order must not be settled by a payment.');
        } catch (InvalidOrderTransitionException $exception) {
            $this->assertSame('cancelled', $exception->fromStatus);
            $this->assertSame('paid', $exception->toStatus);
        }

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()?->status);
    }

    public function test_payment_result_exposes_exactly_successful_reference_and_message(): void
    {
        $settled = PaymentResult::successful('COD-000001', 'recorded');

        $this->assertTrue($settled->successful);
        $this->assertSame('COD-000001', $settled->reference);
        $this->assertSame('recorded', $settled->message);
        $this->assertSame(
            ['successful' => true, 'reference' => 'COD-000001', 'message' => 'recorded'],
            $settled->toArray(),
        );

        $declined = PaymentResult::failed('Your card was declined.');

        $this->assertFalse($declined->successful);
        $this->assertNull($declined->reference);
        $this->assertSame(
            ['successful' => false, 'reference' => null, 'message' => 'Your card was declined.'],
            $declined->toArray(),
        );

        // A reference may stand on its own, without a message.
        $this->assertNull(PaymentResult::successful('CARD-000002')->message);
    }
}
