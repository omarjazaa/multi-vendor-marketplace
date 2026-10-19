<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\RefundablePaymentInterface;
use App\Services\Payments\RefundResult;
use App\Services\Payments\Strategies\BankTransferStrategy;
use App\Services\Payments\Strategies\CardPaymentStrategy;
use App\Services\Payments\Strategies\CashOnDeliveryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolymorphicRefundStrategiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.payments.card.simulate_refund_failure' => false]);
    }

    public function test_all_payment_strategies_implement_refundable_and_payment_gateway_interfaces(): void
    {
        $card = app(CardPaymentStrategy::class);
        $cod = app(CashOnDeliveryStrategy::class);
        $bank = app(BankTransferStrategy::class);

        $this->assertInstanceOf(RefundablePaymentInterface::class, $card);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $card);

        $this->assertInstanceOf(RefundablePaymentInterface::class, $cod);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $cod);

        $this->assertInstanceOf(RefundablePaymentInterface::class, $bank);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $bank);
    }

    public function test_card_payment_strategy_refunds_successfully(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'payment_method' => 'card',
        ]);

        $result = app(CardPaymentStrategy::class)->refund($order, 'Customer requested cancellation.');

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('REF-CARD-%06d', $order->id), $result->reference);
        $this->assertSame('Card payment refunded successfully.', $result->message);
    }

    public function test_card_payment_strategy_handles_simulated_refund_failure(): void
    {
        config([
            'marketplace.payments.card.simulate_refund_failure' => true,
            'marketplace.payments.card.refund_decline_message' => 'Simulated gateway refund failure.',
        ]);

        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'payment_method' => 'card',
        ]);

        $result = app(CardPaymentStrategy::class)->refund($order);

        $this->assertFalse($result->successful);
        $this->assertNull($result->reference);
        $this->assertSame('Simulated gateway refund failure.', $result->message);
    }

    public function test_cash_on_delivery_strategy_refunds_successfully(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::DELIVERED,
            'payment_method' => 'cod',
        ]);

        $result = app(CashOnDeliveryStrategy::class)->refund($order, 'Returned package.');

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('REF-COD-%06d', $order->id), $result->reference);
        $this->assertStringContainsString('Cash on delivery refund recorded', (string) $result->message);
    }

    public function test_bank_transfer_strategy_refunds_successfully(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'payment_method' => 'bank_transfer',
        ]);

        $result = app(BankTransferStrategy::class)->refund($order, 'Duplicate payment.');

        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('REF-BANK-%06d', $order->id), $result->reference);
        $this->assertStringContainsString('Bank transfer refund initiated', (string) $result->message);
    }

    public function test_refund_result_serialization(): void
    {
        $success = RefundResult::successful('REF-CARD-000001', 'Approved');
        $this->assertSame([
            'successful' => true,
            'reference' => 'REF-CARD-000001',
            'message' => 'Approved',
        ], $success->toArray());

        $failed = RefundResult::failed('Declined');
        $this->assertSame([
            'successful' => false,
            'reference' => null,
            'message' => 'Declined',
        ], $failed->toArray());
    }
}
