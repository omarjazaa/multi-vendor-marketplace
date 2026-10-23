<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Events\OrderPaid;
use App\Events\OrderRefunded;
use App\Events\OrderShipped;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Order State Machine & Lifecycle Transitions Audit.
 *
 * Verifies exhaustive state transition matrix adherence, terminal state immutability,
 * illegal jump rejections (409 Conflict), role authorization enforcement, and
 * event lifecycle emissions.
 */
class Day28OrderStateTransitionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_traverse_full_valid_lifecycle_pipeline(): void
    {
        Event::fake([OrderPaid::class, OrderShipped::class]);

        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        // 1. Pending -> Paid
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        Event::assertDispatched(OrderPaid::class);

        // 2. Paid -> Shipped
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'shipped');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped']);
        Event::assertDispatched(OrderShipped::class);

        // 3. Shipped -> Delivered
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'delivered');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_cancelled_order_is_strictly_terminal(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::CANCELLED]);

        $illegalTargets = ['pending', 'paid', 'shipped', 'delivered', 'refunded'];

        foreach ($illegalTargets as $target) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $target])
                ->assertStatus(409)
                ->assertJsonPath('message', 'Order status transition not allowed.')
                ->assertJsonPath('errors.status', "An order cannot move from cancelled to {$target}.");

            $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        }
    }

    public function test_refunded_order_is_strictly_terminal(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::REFUNDED]);

        $illegalTargets = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

        foreach ($illegalTargets as $target) {
            $this->actingAs($admin, 'sanctum')
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $target])
                ->assertStatus(409)
                ->assertJsonPath('message', 'Order status transition not allowed.')
                ->assertJsonPath('errors.status', "An order cannot move from refunded to {$target}.");

            $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
        }
    }

    public function test_illegal_matrix_jumps_are_rejected_with_conflict(): void
    {
        $admin = $this->admin();

        $invalidTransitions = [
            OrderStatus::PENDING->value => ['shipped', 'delivered', 'refunded'],
            OrderStatus::SHIPPED->value => ['pending', 'paid', 'cancelled', 'refunded'],
            OrderStatus::DELIVERED->value => ['pending', 'paid', 'shipped', 'cancelled'],
        ];

        foreach ($invalidTransitions as $fromStatus => $forbiddenTargets) {
            foreach ($forbiddenTargets as $toStatus) {
                $order = Order::factory()->create(['status' => $fromStatus]);

                $this->actingAs($admin, 'sanctum')
                    ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $toStatus])
                    ->assertStatus(409)
                    ->assertJsonPath('errors.status', "An order cannot move from {$fromStatus} to {$toStatus}.");

                $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $fromStatus]);
            }
        }
    }

    public function test_dispute_refund_overrides_matrix_via_force_apply_for_shipped_order(): void
    {
        Event::fake([OrderRefunded::class]);

        $admin = $this->admin();
        $customer = $this->customer();
        $profile = VendorProfile::factory()->create();
        $store = Store::factory()->create(['vendor_profile_id' => $profile->id, 'status' => StoreStatus::APPROVED]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'store_id' => $store->id,
            'status' => OrderStatus::SHIPPED,
            'payment_method' => 'card',
        ]);

        // Standard admin PATCH rejects shipped -> refunded without force
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from shipped to refunded.');

        // Direct admin order refund with force=true overrides the matrix for lost in transit packages
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Package lost by carrier during shipping.',
                'force' => true,
            ])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Order refunded successfully.')
            ->assertJsonPath('data.order.status', 'refunded');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'refund_reason' => 'Package lost by carrier during shipping.',
        ]);

        Event::assertDispatched(OrderRefunded::class);
    }

    public function test_unauthorized_actors_cannot_mutate_order_status(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $customer = $this->customer();
        $vendor = $this->vendor();

        // 1. Guest
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertUnauthorized();

        // 2. Customer
        $this->actingAs($customer, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertForbidden();

        // 3. Vendor cannot call admin endpoint
        $this->actingAs($vendor, 'sanctum')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertForbidden();

        // Order remains unmodified in database
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function vendor(): User
    {
        $user = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create(['user_id' => $user->id]);
        Store::factory()->create(['vendor_profile_id' => $profile->id, 'status' => StoreStatus::APPROVED]);

        return $user;
    }
}
