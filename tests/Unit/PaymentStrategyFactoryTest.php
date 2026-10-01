<?php

namespace Tests\Unit;

use App\Exceptions\UnsupportedPaymentMethodException;
use App\Models\Order;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyFactory;
use App\Services\Payments\PaymentStrategyInterface;
use App\Services\Payments\Strategies\BankTransferStrategy;
use App\Services\Payments\Strategies\CardPaymentStrategy;
use App\Services\Payments\Strategies\CashOnDeliveryStrategy;
use stdClass;
use Tests\TestCase;

class PaymentStrategyFactoryTest extends TestCase
{
    public function test_it_resolves_every_configured_method_to_its_own_strategy(): void
    {
        $factory = app(PaymentStrategyFactory::class);

        $this->assertInstanceOf(CashOnDeliveryStrategy::class, $factory->make('cod'));
        $this->assertInstanceOf(CardPaymentStrategy::class, $factory->make('card'));
        $this->assertInstanceOf(BankTransferStrategy::class, $factory->make('bank_transfer'));

        // Each strategy also names the method it answers to.
        $this->assertSame('cod', $factory->make('cod')->method());
        $this->assertSame('card', $factory->make('card')->method());
        $this->assertSame('bank_transfer', $factory->make('bank_transfer')->method());
    }

    public function test_the_supported_methods_are_exactly_the_configured_keys(): void
    {
        $this->assertSame(
            array_keys((array) config('marketplace.payments.methods')),
            app(PaymentStrategyFactory::class)->supported(),
        );
    }

    public function test_method_names_are_normalised_before_the_lookup(): void
    {
        $factory = app(PaymentStrategyFactory::class);

        $this->assertInstanceOf(CardPaymentStrategy::class, $factory->make('  CARD  '));
    }

    public function test_an_unknown_method_is_rejected_and_names_the_supported_ones(): void
    {
        try {
            app(PaymentStrategyFactory::class)->make('bitcoin');
            $this->fail('An unregistered method must be rejected.');
        } catch (UnsupportedPaymentMethodException $exception) {
            $this->assertSame('bitcoin', $exception->method);
            $this->assertSame(['cod', 'card', 'bank_transfer'], $exception->supported);
            $this->assertStringContainsString(
                'Payment method "bitcoin" is not supported.',
                $exception->getMessage(),
            );
            $this->assertStringContainsString('cod, card, bank_transfer', $exception->getMessage());
        }
    }

    public function test_a_misconfigured_strategy_class_is_rejected(): void
    {
        config()->set('marketplace.payments.methods.cod', stdClass::class);

        try {
            app(PaymentStrategyFactory::class)->make('cod');
            $this->fail('A class that is not a strategy must be rejected.');
        } catch (UnsupportedPaymentMethodException $exception) {
            $this->assertSame('cod', $exception->method);
            $this->assertStringContainsString('is misconfigured', $exception->getMessage());
            $this->assertStringContainsString(stdClass::class, $exception->getMessage());
        }
    }

    public function test_a_new_method_is_registered_through_config_alone(): void
    {
        // The factory carries no map of its own: pointing the config at a new
        // strategy is the whole registration step.
        config()->set('marketplace.payments.methods.wallet', FactoryFakeStrategy::class);

        $factory = app(PaymentStrategyFactory::class);

        $this->assertInstanceOf(FactoryFakeStrategy::class, $factory->make('wallet'));
        $this->assertContains('wallet', $factory->supported());
    }
}

/** Strategy used to prove a config-only registration works. */
class FactoryFakeStrategy implements PaymentStrategyInterface
{
    public function method(): string
    {
        return 'wallet';
    }

    public function pay(Order $order): PaymentResult
    {
        return PaymentResult::successful('WALLET-'.$order->id);
    }
}
