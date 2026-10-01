<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Exceptions\UnsupportedPaymentMethodException;
use App\Models\Cart;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\PaymentStrategyFactory;
use App\Services\Payments\PaymentStrategyInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_on_delivery_checkout_records_a_reference_and_stays_pending(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 2);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        $response
            ->assertJsonPath('data.orders.0.payment_method', 'cod')
            ->assertJsonPath('data.orders.0.status', 'pending')
            ->assertJsonPath('data.payment.method', 'cod')
            ->assertJsonPath('data.payment.successful', true)
            ->assertJsonCount(1, 'data.payment.results');

        $orderId = $response->json('data.orders.0.id');

        // Each split order carries its own reference, on the row and in the payload.
        $reference = sprintf('COD-%06d', $orderId);
        $response->assertJsonPath('data.orders.0.payment_reference', $reference);
        $response->assertJsonPath('data.payment.results.0.order_id', $orderId);
        $response->assertJsonPath('data.payment.results.0.successful', true);
        $response->assertJsonPath('data.payment.results.0.reference', $reference);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'payment_reference' => $reference,
            'status' => 'pending',
        ]);
    }

    public function test_a_declined_card_leaves_the_order_placed_but_unpaid(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 2);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        // The order is real; only the payment failed.
        $response
            ->assertJsonPath('data.orders.0.status', 'pending')
            ->assertJsonPath('data.orders.0.payment_reference', null)
            ->assertJsonPath('data.payment.successful', false)
            ->assertJsonPath('data.payment.results.0.successful', false)
            ->assertJsonPath('data.payment.results.0.reference', null)
            ->assertJsonPath(
                'data.payment.results.0.message',
                config('marketplace.payments.card.decline_message'),
            );

        $orderId = $response->json('data.orders.0.id');

        // Snapshot lines, the reserved stock and the cleared cart all survive.
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'pending',
            'payment_reference' => null,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 3]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_an_approved_card_marks_every_split_order_paid(): void
    {
        config(['marketplace.payments.card.simulate_success' => true]);

        $customer = $this->customer();
        $alpha = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $beta = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $lamp = $this->productIn($alpha, ['name' => 'Desk Lamp', 'base_price' => '49.99']);
        $mug = $this->productIn($beta, ['name' => 'Trail Mug', 'base_price' => '10.00']);
        $this->stockFor($lamp, 10);
        $this->stockFor($mug, 10);
        $this->addToCart($customer, $lamp, 1);
        $this->addToCart($customer, $mug, 1);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'card'])
            ->assertCreated();

        $response
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonCount(2, 'data.payment.results')
            ->assertJsonPath('data.payment.successful', true)
            ->assertJsonPath('data.orders.0.status', 'paid')
            ->assertJsonPath('data.orders.1.status', 'paid');

        foreach (Order::orderBy('id')->get() as $order) {
            $this->assertSame(
                sprintf('CARD-%06d', $order->id),
                $order->payment_reference,
                'Each split order is settled by its own charge.',
            );
            $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        }
    }

    public function test_bank_transfer_checkout_issues_a_reference_and_waits(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'bank_transfer'])
            ->assertCreated();

        $orderId = $response->json('data.orders.0.id');

        // Accepted for settlement, but nothing is paid until an admin confirms.
        $response
            ->assertJsonPath('data.orders.0.status', 'pending')
            ->assertJsonPath('data.orders.0.payment_reference', sprintf('BANK-%06d', $orderId))
            ->assertJsonPath('data.payment.successful', true);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'pending']);
    }

    public function test_a_multi_vendor_cart_charges_every_split_order_exactly_once(): void
    {
        // A recording strategy, registered through config alone, counts the calls
        // checkout makes: one per split order, and never a second time.
        config()->set('marketplace.payments.methods.cod', RecordingPaymentStrategy::class);
        RecordingPaymentStrategy::$chargedOrderIds = [];

        $customer = $this->customer();

        foreach (['10.00', '20.00', '30.00'] as $price) {
            $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
            $product = $this->productIn($store, ['base_price' => $price]);
            $this->stockFor($product, 5);
            $this->addToCart($customer, $product, 1);
        }

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'cod'])
            ->assertCreated();

        $response->assertJsonCount(3, 'data.orders')->assertJsonCount(3, 'data.payment.results');

        $orderIds = Order::orderBy('id')->pluck('id')->all();

        // Three split orders, three charges, in order and without duplicates.
        $this->assertSame($orderIds, RecordingPaymentStrategy::$chargedOrderIds);
        $this->assertSame($orderIds, array_column($response->json('data.payment.results'), 'order_id'));

        foreach ($orderIds as $orderId) {
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'payment_reference' => sprintf('REC-%06d', $orderId),
            ]);
        }
    }

    public function test_a_checkout_without_a_payment_method_skips_the_payment_step(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout')
            ->assertCreated();

        $response
            ->assertJsonPath('data.orders.0.payment_method', null)
            ->assertJsonPath('data.orders.0.payment_reference', null)
            ->assertJsonPath('data.payment.method', null)
            ->assertJsonPath('data.payment.successful', true)
            ->assertJsonCount(0, 'data.payment.results');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.orders.0.id'),
            'payment_method' => null,
            'payment_reference' => null,
        ]);
    }

    public function test_an_unsupported_method_is_rejected_by_the_api(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/checkout', ['payment_method' => 'bitcoin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        // Rejected before any write: the cart is still there to be retried.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_the_service_refuses_an_unregistered_method_before_writing(): void
    {
        $customer = $this->customer();
        $product = $this->visibleProduct();
        $this->stockFor($product, 5);
        $this->addToCart($customer, $product, 1);

        $cart = Cart::where('user_id', $customer->id)->firstOrFail();

        try {
            app(OrderService::class)->place($cart, 'bitcoin');
            $this->fail('A method with no configured strategy must be refused.');
        } catch (UnsupportedPaymentMethodException $exception) {
            $this->assertSame('bitcoin', $exception->method);
            $this->assertSame(['cod', 'card', 'bank_transfer'], $exception->supported);
            $this->assertStringContainsString('is not supported', $exception->getMessage());
        }

        // The strategy is resolved before the transaction, so nothing moved.
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 5]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_the_accepted_methods_and_the_strategy_map_stay_in_sync(): void
    {
        $this->assertEqualsCanonicalizing(
            (array) config('marketplace.checkout.payment_methods'),
            app(PaymentStrategyFactory::class)->supported(),
        );
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    /** A product priced well inside the fraud ceiling, so the payment rules stay the subject under test. */
    private function visibleProduct(array $attributes = []): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        return $this->productIn($store, ['base_price' => '25.00'] + $attributes);
    }

    /** Create a product inside an existing store so carts can span vendors. */
    private function productIn(Store $store, array $attributes = []): Product
    {
        return Product::factory()->create(['store_id' => $store->id] + $attributes);
    }

    private function stockFor(Product $product, int $quantity): Inventory
    {
        return Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold'),
        ]);
    }

    private function addToCart(User $customer, Product $product, int $quantity): void
    {
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])
            ->assertCreated();
    }
}

/** Strategy double that records the order ids checkout charged. */
class RecordingPaymentStrategy implements PaymentStrategyInterface
{
    /** @var list<int> */
    public static array $chargedOrderIds = [];

    public function method(): string
    {
        return 'cod';
    }

    public function pay(Order $order): PaymentResult
    {
        self::$chargedOrderIds[] = (int) $order->id;

        return PaymentResult::successful(sprintf('REC-%06d', $order->id), 'recorded');
    }
}
