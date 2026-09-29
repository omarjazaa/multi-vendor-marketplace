<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Exceptions\FraudRiskException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Checkout\CheckoutContext;
use App\Services\Checkout\CheckoutValidationHandler;
use App\Services\Checkout\CheckoutValidationPipeline;
use App\Services\Checkout\CouponValidityCheck;
use App\Services\Checkout\FraudRiskCheck;
use App\Services\Checkout\StockAvailabilityCheck;
use ArrayObject;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class CheckoutChainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pin the fraud heuristics; individual cases override what they exercise.
        config([
            'marketplace.checkout.fraud.max_order_total' => 1000,
            'marketplace.checkout.fraud.max_orders_per_window' => 5,
            'marketplace.checkout.fraud.window_minutes' => 60,
        ]);
    }

    public function test_the_chain_runs_handlers_in_link_order_and_halts_at_a_rejection(): void
    {
        $log = new ArrayObject;
        $halt = new RuntimeException('halted');

        $first = $this->recorder($log, 'first', $halt);
        $first->setNext($this->recorder($log, 'second'))->setNext($this->recorder($log, 'third'));

        try {
            $first->handle($this->context());
            $this->fail('The chain should have halted at the first handler.');
        } catch (RuntimeException $exception) {
            $this->assertSame($halt, $exception);
        }

        $this->assertSame(['first'], $log->getArrayCopy());

        // A passing first link lets the whole chain run in wired order.
        $log->exchangeArray([]);

        $passing = $this->recorder($log, 'a');
        $passing->setNext($this->recorder($log, 'b'))->setNext($this->recorder($log, 'c'));

        $passing->handle($this->context());

        $this->assertSame(['a', 'b', 'c'], $log->getArrayCopy());
    }

    public function test_a_stock_shortfall_halts_the_chain_with_the_typed_exception(): void
    {
        $log = new ArrayObject;
        $handler = new StockAvailabilityCheck(new ChainFakeInventoryRepository(available: 1));
        $handler->setNext($this->recorder($log, 'after-stock'));

        try {
            $handler->handle($this->context(quantity: 2));
            $this->fail('The stock guard should have rejected the checkout.');
        } catch (InsufficientStockException $exception) {
            $this->assertSame(1, $exception->available);
            $this->assertSame('Desk Lamp', $exception->productName);
        }

        // The chain halted at the guard: the next link never saw the context.
        $this->assertSame([], $log->getArrayCopy());
    }

    public function test_the_coupon_guard_parks_a_valid_coupon_and_rejects_bad_ones(): void
    {
        $valid = Coupon::factory()->fixed()->make(['code' => 'SAVE10', 'value' => 10]);
        $log = new ArrayObject;
        $handler = new CouponValidityCheck(new ChainFakeCouponRepository($valid));
        $handler->setNext($this->recorder($log, 'after-coupon'));

        $context = $this->context(couponCode: 'save10');
        $handler->handle($context);

        // Codes match case-insensitively and the accepted coupon is parked on
        // the context for the pricing step.
        $this->assertSame($valid, $context->coupon);
        $this->assertSame(['after-coupon'], $log->getArrayCopy());

        // Unknown code → the same sentence the cart preview shows.
        try {
            (new CouponValidityCheck(new ChainFakeCouponRepository(null)))
                ->handle($this->context(couponCode: 'NOPE'));
            $this->fail('An unknown code should have been rejected.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame('No coupon found for code NOPE.', $exception->getMessage());
        }

        // Expired coupon → the same sentence the cart preview shows.
        $expired = Coupon::factory()->make(['code' => 'OLD', 'expires_at' => now()->subDay()]);

        try {
            (new CouponValidityCheck(new ChainFakeCouponRepository($expired)))
                ->handle($this->context(couponCode: 'OLD'));
            $this->fail('An expired coupon should have been rejected.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame('This coupon has expired.', $exception->getMessage());
        }

        // Minimum-order shortfall → the same sentence the cart preview shows.
        $whale = Coupon::factory()->fixed()->make(['code' => 'WHALE', 'min_order_amount' => 500]);

        try {
            (new CouponValidityCheck(new ChainFakeCouponRepository($whale)))
                ->handle($this->context(couponCode: 'WHALE'));
            $this->fail('A minimum-order shortfall should have been rejected.');
        } catch (InvalidCouponException $exception) {
            $this->assertSame(
                'A minimum order of 500.00 is required to use this coupon.',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_fraud_guard_enforces_the_configured_ceiling_and_window(): void
    {
        // A zero ceiling disables the total rule even for a huge cart.
        config(['marketplace.checkout.fraud.max_order_total' => 0]);

        $orders = new ChainFakeOrderRepository(recentOrders: 0);
        (new FraudRiskCheck($orders))->handle($this->context(quantity: 500));
        $this->assertSame(1, $orders->windowQueries);

        // Above the ceiling → rejected and the rule named for the API.
        config(['marketplace.checkout.fraud.max_order_total' => 50]);

        try {
            (new FraudRiskCheck(new ChainFakeOrderRepository(recentOrders: 0)))
                ->handle($this->context(quantity: 6));
            $this->fail('A subtotal above the ceiling should have been rejected.');
        } catch (FraudRiskException $exception) {
            $this->assertSame('max_order_total', $exception->rule);
            $this->assertSame(
                'Order total of 60.00 exceeds the allowed limit of 50.00.',
                $exception->getMessage(),
            );
        }

        // Too many recent orders → the rate rule is named. Restore the ceiling
        // first: it short-circuits before the window rule runs.
        config(['marketplace.checkout.fraud.max_order_total' => 1000]);

        try {
            (new FraudRiskCheck(new ChainFakeOrderRepository(recentOrders: 5)))
                ->handle($this->context());
            $this->fail('A burst of orders inside the window should have been rejected.');
        } catch (FraudRiskException $exception) {
            $this->assertSame('max_orders_per_window', $exception->rule);
            $this->assertSame(
                'Too many orders in a short window: 5 placed within the last 60 minutes (maximum 5).',
                $exception->getMessage(),
            );
        }
    }

    public function test_the_pipeline_reports_the_stock_rejection_before_any_other(): void
    {
        // Stock would fail, the coupon is unknown and a fraud rule would trip:
        // the stock link runs first, so its typed exception is the one that
        // escapes the pipeline.
        $pipeline = new CheckoutValidationPipeline(
            new StockAvailabilityCheck(new ChainFakeInventoryRepository(available: 0)),
            new CouponValidityCheck(new ChainFakeCouponRepository(null)),
            new FraudRiskCheck(new ChainFakeOrderRepository(recentOrders: 5)),
        );

        $this->expectException(InsufficientStockException::class);

        $pipeline->run($this->context(couponCode: 'NOPE'));
    }

    public function test_the_pipeline_reports_the_coupon_rejection_before_fraud(): void
    {
        // Stock passes, the coupon is unknown and the fraud window would also
        // trip: the coupon link runs second, so it wins the rejection.
        $pipeline = new CheckoutValidationPipeline(
            new StockAvailabilityCheck(new ChainFakeInventoryRepository(available: 10)),
            new CouponValidityCheck(new ChainFakeCouponRepository(null)),
            new FraudRiskCheck(new ChainFakeOrderRepository(recentOrders: 5)),
        );

        $this->expectException(InvalidCouponException::class);

        $pipeline->run($this->context(couponCode: 'NOPE'));
    }

    public function test_a_passing_chain_runs_every_link_and_completes(): void
    {
        $coupon = Coupon::factory()->fixed()->make(['code' => 'SAVE10', 'value' => 10]);
        $orders = new ChainFakeOrderRepository(recentOrders: 0);

        $pipeline = new CheckoutValidationPipeline(
            new StockAvailabilityCheck(new ChainFakeInventoryRepository(available: 10)),
            new CouponValidityCheck(new ChainFakeCouponRepository($coupon)),
            new FraudRiskCheck($orders),
        );

        $context = $this->context(couponCode: 'save10');
        $pipeline->run($context);

        // Stock passed, coupon accepted and parked, fraud window consulted.
        $this->assertSame($coupon, $context->coupon);
        $this->assertSame(10.0, $context->subtotal);
        $this->assertSame(1, $orders->windowQueries);
    }

    /**
     * A one-line chain link that records its turn and, when given one,
     * throws that throwable — used to observe order and halting.
     *
     * @param  ArrayObject<int, string>  $log
     */
    private function recorder(ArrayObject $log, string $name, ?Throwable $throw = null): CheckoutValidationHandler
    {
        return new class($log, $name, $throw) extends CheckoutValidationHandler
        {
            public function __construct(
                private readonly ArrayObject $log,
                private readonly string $name,
                private readonly ?Throwable $throw,
            ) {}

            protected function validate(CheckoutContext $context): void
            {
                $this->log->append($this->name);

                if ($this->throw !== null) {
                    throw $this->throw;
                }
            }
        };
    }

    /** Build a one-line context in memory — the chain only reads, no database involved. */
    private function context(float $unitPrice = 10.0, int $quantity = 1, ?string $couponCode = null): CheckoutContext
    {
        $product = new Product(['store_id' => 3, 'name' => 'Desk Lamp']);
        $line = new CartItem([
            'product_id' => 1,
            'quantity' => $quantity,
            'unit_price' => number_format($unitPrice, 2, '.', ''),
        ]);
        $line->setRelation('product', $product);

        return new CheckoutContext(
            new Cart(['user_id' => 7]),
            new EloquentCollection([$line]),
            $couponCode,
            'card',
        );
    }
}

/** In-memory inventory double: reports one fixed available level to the chain. */
class ChainFakeInventoryRepository implements InventoryRepositoryInterface
{
    public function __construct(private readonly int $available) {}

    public function firstOrCreateForProduct(Product $product): Inventory
    {
        return new Inventory(['product_id' => $product->id, 'quantity' => $this->available]);
    }

    public function update(Inventory $inventory, array $attributes): Inventory
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function decrementQuantity(Product $product, int $quantity): bool
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }
}

/** In-memory coupon double: resolves exactly one known (case-insensitive) code. */
class ChainFakeCouponRepository implements CouponRepositoryInterface
{
    public function __construct(private readonly ?Coupon $coupon) {}

    public function create(array $attributes): Coupon
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function findByCode(string $code): ?Coupon
    {
        if ($this->coupon === null) {
            return null;
        }

        return strtoupper(trim($code)) === $this->coupon->code ? $this->coupon : null;
    }
}

/** In-memory order double: reports a fixed window count and records lookups. */
class ChainFakeOrderRepository implements OrderRepositoryInterface
{
    public int $windowQueries = 0;

    public function __construct(private readonly int $recentOrders = 0) {}

    public function create(
        int $userId,
        ?int $storeId,
        OrderStatus $status,
        ?string $paymentMethod,
        string $discount,
        string $tax,
        string $totalPrice,
        array $items,
    ): Order {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function forUser(int $userId, int $perPage): LengthAwarePaginator
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function linesForVendor(int $userId, array $filters, int $perPage): LengthAwarePaginator
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function allForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function updateStatus(Order $order, OrderStatus $status): Order
    {
        throw new RuntimeException('Not used by the checkout chain.');
    }

    public function countForUserSince(int $userId, CarbonInterface $since): int
    {
        $this->windowQueries++;

        return $this->recentOrders;
    }
}
