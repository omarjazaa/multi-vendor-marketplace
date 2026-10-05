<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_move_a_paid_order_to_refunded(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::PAID]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Order status updated.')
            ->assertJsonPath('data.order.status', 'refunded');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_admin_can_move_a_delivered_order_to_refunded(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::DELIVERED]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'refunded');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_refunded_is_terminal(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::REFUNDED]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Order status transition not allowed.')
            ->assertJsonPath('errors.status', 'An order cannot move from refunded to paid.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
    }

    public function test_pending_cannot_jump_straight_to_refunded(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'refunded'])
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from pending to refunded.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_the_refunded_status_is_a_valid_listing_filter(): void
    {
        $admin = $this->admin();
        Order::factory()->create(['status' => OrderStatus::REFUNDED]);
        Order::factory()->create(['status' => OrderStatus::PAID]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?status=refunded')
            ->assertOk()
            ->assertJsonCount(1, 'data.orders.data')
            ->assertJsonPath('data.orders.data.0.status', 'refunded')
            ->assertJsonPath('data.orders.meta.total', 1);

        // An unfiltered value still fails validation — refunded joined the list, nothing fell out of it.
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }
}
