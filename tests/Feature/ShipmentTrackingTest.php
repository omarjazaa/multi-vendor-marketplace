<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\ShipmentTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_user_can_track_shipment_by_tracking_number(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();

        $response = $this->getJson("/api/v1/shipments/track/{$shipment->tracking_number}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'shipment' => [
                        'id',
                        'tracking_number',
                        'status',
                        'status_label',
                        'status_icon',
                        'is_delivered',
                        'shipped_at',
                        'delivered_at',
                        'timeline' => [
                            '*' => [
                                'step',
                                'status',
                                'title',
                                'description',
                                'icon',
                                'completed',
                                'current',
                                'timestamp',
                            ],
                        ],
                    ],
                ],
                'message',
            ]);

        $this->assertSame((string) $shipment->tracking_number, $response->json('data.shipment.tracking_number'));
        $this->assertSame('processing', $response->json('data.shipment.status'));
        $this->assertFalse($response->json('data.shipment.is_delivered'));
        $this->assertCount(4, $response->json('data.shipment.timeline'));
    }

    public function test_tracking_nonexistent_number_returns_404(): void
    {
        $this->getJson('/api/v1/shipments/track/TRK-NONEXISTENT-999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Tracking number not found.');
    }

    public function test_customer_can_list_shipments_for_their_order(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();
        $customer = $order->user;

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/shipments")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'shipments' => [
                        '*' => [
                            'id',
                            'tracking_number',
                            'status',
                            'status_label',
                        ],
                    ],
                ],
            ]);

        $this->assertSame($order->id, $response->json('data.order_id'));
        $this->assertCount(1, $response->json('data.shipments'));
        $this->assertSame($shipment->tracking_number, $response->json('data.shipments.0.tracking_number'));
    }

    public function test_unauthorized_user_cannot_view_order_shipments(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();
        $stranger = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/orders/{$order->id}/shipments")
            ->assertForbidden();
    }

    public function test_vendor_can_advance_shipment_to_in_transit(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();

        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/v1/vendor/shipments/{$shipment->id}/status", [
                'status' => ShipmentStatus::IN_TRANSIT->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'in_transit')
            ->assertJsonPath('data.shipment.status_label', 'In Transit with Carrier')
            ->assertJsonPath('message', 'Shipment status updated to in_transit.');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'in_transit',
            'delivered_at' => null,
        ]);
    }

    public function test_vendor_can_advance_shipment_to_delivered_and_transitions_order(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();
        $customer = $order->user;

        $this->assertSame(OrderStatus::SHIPPED, $order->status);

        $response = $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/v1/vendor/shipments/{$shipment->id}/status", [
                'status' => ShipmentStatus::DELIVERED->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment.status', 'delivered')
            ->assertJsonPath('data.shipment.is_delivered', true)
            ->assertJsonPath('data.order.status', 'delivered');

        $this->assertNotNull($response->json('data.shipment.delivered_at'));

        // Assert database records updated
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'delivered',
        ]);
        $this->assertNotNull($shipment->fresh()->delivered_at);

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        // Assert customer received in-app delivery notification
        $customerNotifications = $customer->notifications()->get();
        $this->assertTrue(
            $customerNotifications->contains(function ($notif) use ($order) {
                return ($notif->data['kind'] ?? '') === 'customer_order_delivered'
                    && (($notif->data['data']['order_id'] ?? $notif->data['order_id'] ?? null) === $order->id);
            })
        );
    }

    public function test_order_delivery_unlocks_customer_product_review(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();
        $customer = $order->user;
        $product = $item->product;

        // 1. Before delivery, customer attempting to review product gets rejected
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'Great product!',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only verified buyers with delivered orders can review this product.');

        // 2. Vendor marks shipment as delivered
        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/v1/vendor/shipments/{$shipment->id}/status", [
                'status' => ShipmentStatus::DELIVERED->value,
            ])
            ->assertOk();

        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        // 3. Customer can now review the delivered product!
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'Verified purchase: absolutely fantastic quality!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('message', 'Review submitted successfully.');

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'rating' => 5,
        ]);
    }

    public function test_stranger_vendor_cannot_update_another_stores_shipment(): void
    {
        [$vendor1, $order, $item, $shipment] = $this->createShippedOrder();

        $vendor2 = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile2 = VendorProfile::factory()->create([
            'user_id' => $vendor2->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        Store::factory()->create([
            'vendor_profile_id' => $profile2->id,
            'status' => StoreStatus::APPROVED,
        ]);

        $this->actingAs($vendor2, 'sanctum')
            ->patchJson("/api/v1/vendor/shipments/{$shipment->id}/status", [
                'status' => ShipmentStatus::IN_TRANSIT->value,
            ])
            ->assertForbidden();
    }

    public function test_invalid_shipment_status_rejected_by_validation(): void
    {
        [$vendor, $order, $item, $shipment] = $this->createShippedOrder();

        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/v1/vendor/shipments/{$shipment->id}/status", [
                'status' => 'teleported_to_moon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Helper to create a fully wired shipped order with store, product, order item and shipment.
     *
     * @return array{0: User, 1: Order, 2: OrderItem, 3: Shipment}
     */
    private function createShippedOrder(): array
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'is_active' => true,
            'base_price' => 75.00,
            'name' => 'Ultra Wireless Headphones',
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'low_stock_threshold' => 3,
        ]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'store_id' => $store->id,
            'status' => OrderStatus::SHIPPED,
            'total_price' => 75.00,
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'unit_price' => 75.00,
        ]);

        $trackingService = app(ShipmentTrackingService::class);
        $trackingNumber = $trackingService->generate($order->id, $item->id);
        $shipment = $trackingService->record($order->id, $item->id, $trackingNumber);

        return [$vendor, $order, $item, $shipment];
    }
}
