<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Events\OrderShipped;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class VendorShipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_ship_a_paid_order_and_gets_a_mock_tracking_number(): void
    {
        Event::fake([OrderShipped::class]);

        $vendor = $this->vendor();
        [$order, $item] = $this->orderWithLineFor($vendor);
        $tracking = sprintf('%s-%d-%d', config('marketplace.shipments.tracking_prefix'), $order->id, $item->id);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("api/vendor/orders/{$order->id}/items/{$item->id}/ship")
            ->assertOk()
            ->assertJsonPath('message', 'Order shipped.')
            ->assertJsonPath('data.shipment.tracking_number', $tracking)
            ->assertJsonPath('data.shipment.status', 'processing')
            ->assertJsonPath('data.shipment.delivered_at', null)
            ->assertJsonPath('data.order.status', 'shipped')
            ->assertJsonStructure([
                'data' => [
                    'shipment' => ['id', 'tracking_number', 'status', 'shipped_at', 'delivered_at'],
                    'order' => ['id', 'user_id', 'store_id', 'status', 'items'],
                ],
                'message',
            ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
        $this->assertDatabaseHas('shipments', [
            'order_item_id' => $item->id,
            'tracking_number' => $tracking,
            'status' => 'processing',
        ]);
        $this->assertNotNull(Shipment::query()->value('shipped_at'));

        // Firing exactly once proves the event is owned by the shared gate.
        Event::assertDispatchedTimes(OrderShipped::class, 1);
    }

    public function test_shipping_a_pending_order_is_rejected_with_409(): void
    {
        $vendor = $this->vendor();
        [$order, $item] = $this->orderWithLineFor($vendor, OrderStatus::PENDING);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("api/vendor/orders/{$order->id}/items/{$item->id}/ship")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Order cannot be shipped in its current state.')
            ->assertJsonPath('errors.status', 'An order cannot move from pending to shipped.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_an_order_can_only_be_shipped_once(): void
    {
        $vendor = $this->vendor();
        [$order, $item] = $this->orderWithLineFor($vendor);
        $uri = "api/vendor/orders/{$order->id}/items/{$item->id}/ship";

        $this->actingAs($vendor, 'sanctum')->postJson($uri)->assertOk();

        $this->actingAs($vendor, 'sanctum')
            ->postJson($uri)
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from shipped to shipped.');

        $this->assertDatabaseCount('shipments', 1);
    }

    public function test_ship_rejects_a_line_from_another_order(): void
    {
        $vendor = $this->vendor();
        [$order, $item] = $this->orderWithLineFor($vendor);

        // A different paid order — the line must be scoped to the order being
        // shipped, otherwise a vendor could key a tracker onto a foreign order.
        $foreignOrder = Order::factory()->create([
            'user_id' => $this->customer()->id,
            'status' => OrderStatus::PAID,
        ]);
        $foreignItem = OrderItem::factory()->create([
            'order_id' => $foreignOrder->id,
            'product_id' => $item->product_id,
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("api/vendor/orders/{$order->id}/items/{$foreignItem->id}/ship")
            ->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_vendor_cannot_ship_another_vendors_order(): void
    {
        [$order, $item] = $this->orderWithLineFor($this->vendor());
        $rival = User::factory()->create(['role' => UserRole::VENDOR]);

        $this->actingAs($rival, 'sanctum')
            ->postJson("api/vendor/orders/{$order->id}/items/{$item->id}/ship")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_guests_and_customers_cannot_ship(): void
    {
        [$order, $item] = $this->orderWithLineFor($this->vendor());
        $uri = "api/vendor/orders/{$order->id}/items/{$item->id}/ship";

        $this->postJson($uri)->assertUnauthorized();

        $this->actingAs($this->customer(), 'sanctum')
            ->postJson($uri)
            ->assertForbidden();

        $this->assertDatabaseCount('shipments', 0);
    }

    private function vendor(): User
    {
        return User::factory()->create(['role' => UserRole::VENDOR]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    /**
     * Build an order for the given vendor's store containing a single line.
     *
     * @return array{0: Order, 1: OrderItem}
     */
    private function orderWithLineFor(User $vendor, OrderStatus $status = OrderStatus::PAID): array
    {
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);
        $product = Product::factory()->create(['store_id' => $store->id]);

        $order = Order::factory()->create([
            'user_id' => $this->customer()->id,
            'status' => $status,
        ]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        return [$order, $item];
    }
}
