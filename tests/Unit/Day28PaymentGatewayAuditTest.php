<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\UnsupportedPaymentMethodException;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyFactory;
use App\Services\Payments\PaymentStrategyInterface;
use App\Services\Payments\RefundablePaymentInterface;
use App\Services\Payments\RefundResult;
use App\Services\Payments\Strategies\BankTransferStrategy;
use App\Services\Payments\Strategies\CardPaymentStrategy;
use App\Services\Payments\Strategies\CashOnDeliveryStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Payment Strategy & Gateway Polymorphism Audit.
 *
 * Verifies factory resolution, whitespace and casing normalization, strict interface segregation,
 * simulated gateway failure modes, reference code syntax invariants, and OCP extensibility.
 */
class Day28PaymentGatewayAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'marketplace.payments.card.simulate_success' => true,
            'marketplace.payments.card.simulate_refund_failure' => false,
        ]);
    }

    public function test_factory_normalizes_casing_and_surrounding_whitespace(): void
    {
        $factory = app(PaymentStrategyFactory::class);

        $this->assertInstanceOf(CardPaymentStrategy::class, $factory->make('card'));
        $this->assertInstanceOf(CardPaymentStrategy::class, $factory->make('CARD'));
        $this->assertInstanceOf(CardPaymentStrategy::class, $factory->make("  CaRd \n"));

        $this->assertInstanceOf(CashOnDeliveryStrategy::class, $factory->make(' CoD '));
        $this->assertInstanceOf(BankTransferStrategy::class, $factory->make(' BANK_TRANSFER '));
    }

    public function test_factory_rejects_unregistered_methods_with_detailed_exception(): void
    {
        $factory = app(PaymentStrategyFactory::class);

        try {
            $factory->make('apple_pay');
            $this->fail('Expected UnsupportedPaymentMethodException was not thrown.');
        } catch (UnsupportedPaymentMethodException $exception) {
            $this->assertSame('apple_pay', $exception->method);
            $this->assertContains('card', $exception->supported);
            $this->assertContains('cod', $exception->supported);
            $this->assertContains('bank_transfer', $exception->supported);
            $this->assertStringContainsString('Payment method "apple_pay" is not supported', $exception->getMessage());
        }
    }

    public function test_all_registered_payment_strategies_satisfy_segregated_contracts(): void
    {
        $factory = app(PaymentStrategyFactory::class);

        foreach ($factory->supported() as $method) {
            $strategy = $factory->make($method);

            // Must implement base strategy
            $this->assertInstanceOf(PaymentStrategyInterface::class, $strategy);
            // Must implement segregated refundable interface (Day 27)
            $this->assertInstanceOf(RefundablePaymentInterface::class, $strategy);
            // Must satisfy unified gateway interface
            $this->assertInstanceOf(PaymentGatewayInterface::class, $strategy);

            $this->assertSame($method, $strategy->method());
        }
    }

    public function test_card_payment_strategy_charge_and_refund_lifecycle(): void
    {
        $card = app(CardPaymentStrategy::class);
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_method' => 'card',
        ]);

        // 1. Charge Success
        $charge = $card->pay($order);
        $this->assertTrue($charge->successful);
        $this->assertSame(sprintf('CARD-%06d', $order->id), $charge->reference);

        // 2. Refund Success
        $refund = $card->refund($order, 'Item returned by customer.');
        $this->assertTrue($refund->successful);
        $this->assertSame(sprintf('REF-CARD-%06d', $order->id), $refund->reference);
        $this->assertSame('Card payment refunded successfully.', $refund->message);

        // 3. Refund Failure Simulation
        config(['marketplace.payments.card.simulate_refund_failure' => true]);
        $failedRefund = $card->refund($order, 'Defective goods.');
        $this->assertFalse($failedRefund->successful);
        $this->assertNull($failedRefund->reference);
        $this->assertSame(
            config('marketplace.payments.card.refund_decline_message'),
            $failedRefund->message,
        );
    }

    public function test_cash_on_delivery_strategy_invariants(): void
    {
        $cod = app(CashOnDeliveryStrategy::class);
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_method' => 'cod',
        ]);

        $charge = $cod->pay($order);
        $this->assertTrue($charge->successful);
        $this->assertSame(sprintf('COD-%06d', $order->id), $charge->reference);

        $refund = $cod->refund($order, 'Cash refunded upon courier return.');
        $this->assertTrue($refund->successful);
        $this->assertSame(sprintf('REF-COD-%06d', $order->id), $refund->reference);
    }

    public function test_bank_transfer_strategy_invariants(): void
    {
        $bank = app(BankTransferStrategy::class);
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_method' => 'bank_transfer',
        ]);

        $charge = $bank->pay($order);
        $this->assertTrue($charge->successful);
        $this->assertSame(sprintf('BANK-%06d', $order->id), $charge->reference);

        $refund = $bank->refund($order, 'Wire reversal initiated.');
        $this->assertTrue($refund->successful);
        $this->assertSame(sprintf('REF-BANK-%06d', $order->id), $refund->reference);
    }

    public function test_open_closed_principle_dynamic_custom_gateway_registration(): void
    {
        // Custom gateway mock implementation
        $customGateway = new class implements PaymentGatewayInterface {
            public function method(): string
            {
                return 'mock_crypto';
            }

            public function pay(Order $order): PaymentResult
            {
                return PaymentResult::successful(sprintf('CRYPTO-%06d', $order->id), 'Crypto paid.');
            }

            public function refund(Order $order, ?string $reason = null): RefundResult
            {
                return RefundResult::success(sprintf('REF-CRYPTO-%06d', $order->id), (string) $reason);
            }
        };

        // Dynamically register in configuration
        config(['marketplace.payments.methods.mock_crypto' => get_class($customGateway)]);
        $this->app->instance(get_class($customGateway), $customGateway);

        $factory = app(PaymentStrategyFactory::class);
        $this->assertContains('mock_crypto', $factory->supported());

        $resolved = $factory->make('mock_crypto');
        $this->assertInstanceOf(PaymentGatewayInterface::class, $resolved);

        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $result = $resolved->pay($order);
        $this->assertTrue($result->successful);
        $this->assertSame(sprintf('CRYPTO-%06d', $order->id), $result->reference);
    }
}
