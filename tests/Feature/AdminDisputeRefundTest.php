<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\OrderRefunded;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminDisputeRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.payments.card.simulate_refund_failure' => false]);
    }

    public function test_admin_can_refund_paid_order_via_polymorphic_gateway(): void
    {
        Event::fake([OrderRefunded::class]);

        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'payment_method' => 'card',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Customer received damaged package.',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Order refunded successfully.')
            ->assertJsonPath('data.order.status', 'refunded')
            ->assertJsonPath('data.refund.successful', true)
            ->assertJsonPath('data.refund.reference', sprintf('REF-CARD-%06d', $order->id))
            ->assertJsonPath('data.refund.reason', 'Customer received damaged package.');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'refund_reference' => sprintf('REF-CARD-%06d', $order->id),
            'refund_reason' => 'Customer received damaged package.',
        ]);

        Event::assertDispatched(OrderRefunded::class);
    }

    public function test_admin_can_refund_delivered_order_with_cash_on_delivery(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::DELIVERED,
            'payment_method' => 'cod',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/orders/{$order->id}/refund", [
                'reason' => 'COD item returned at service center.',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('data.order.status', 'refunded')
            ->assertJsonPath('data.refund.reference', sprintf('REF-COD-%06d', $order->id));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'refund_reference' => sprintf('REF-COD-%06d', $order->id),
        ]);
    }

    public function test_admin_can_force_refund_on_shipped_order(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::SHIPPED,
            'payment_method' => 'bank_transfer',
        ]);

        // Attempting standard refund without force fails due to state machine matrix
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Lost package in transit',
                'force' => false,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from shipped to refunded.');

        // Forcing transition bypasses ordinary lifecycle matrix for dispute override
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Package confirmed lost by courier company.',
                'force' => true,
            ])
            ->assertOk();

        $response
            ->assertJsonPath('data.order.status', 'refunded')
            ->assertJsonPath('data.refund.reference', sprintf('REF-BANK-%06d', $order->id));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'refund_reference' => sprintf('REF-BANK-%06d', $order->id),
        ]);
    }

    public function test_refunding_unpaid_pending_order_is_rejected(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
            'payment_method' => 'card',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Premature refund request',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from pending to refunded.');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_refunding_already_refunded_order_is_rejected(): void
    {
        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::REFUNDED,
            'payment_method' => 'card',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Duplicate refund',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.status', 'An order cannot move from refunded to refunded.');
    }

    public function test_simulated_gateway_failure_halts_refund_and_preserves_order_state(): void
    {
        config([
            'marketplace.payments.card.simulate_refund_failure' => true,
            'marketplace.payments.card.refund_decline_message' => 'Processor declined refund: insufficient merchant funds.',
        ]);

        $admin = $this->admin();
        $order = Order::factory()->create([
            'status' => OrderStatus::PAID,
            'payment_method' => 'card',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", [
                'reason' => 'Valid refund request',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment gateway refund failed.')
            ->assertJsonPath('errors.gateway', 'Processor declined refund: insufficient merchant funds.');

        // Order status must remain paid and untouched
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
            'refund_reference' => null,
        ]);
    }

    public function test_customer_can_open_dispute_on_paid_or_delivered_order(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
            'payment_method' => 'card',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [
                'reason' => 'Product arrived defective and does not turn on.',
            ])
            ->assertCreated();

        $response
            ->assertJsonPath('message', 'Dispute opened successfully.')
            ->assertJsonPath('data.dispute.status', 'pending')
            ->assertJsonPath('data.dispute.reason', 'Product arrived defective and does not turn on.')
            ->assertJsonPath('data.dispute.order_id', $order->id);

        $this->assertDatabaseHas('disputes', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'pending',
            'reason' => 'Product arrived defective and does not turn on.',
        ]);
    }

    public function test_customer_cannot_open_dispute_on_another_users_order(): void
    {
        $customerA = $this->customer();
        $customerB = $this->customer();

        $order = Order::factory()->create([
            'user_id' => $customerA->id,
            'status' => OrderStatus::DELIVERED,
        ]);

        $this->actingAs($customerB, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [
                'reason' => 'Unauthorized dispute attempt',
            ])
            ->assertForbidden();
    }

    public function test_customer_cannot_open_dispute_on_pending_order(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::PENDING,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [
                'reason' => 'Premature dispute',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Unpaid or cancelled orders cannot be disputed.');
    }

    public function test_customer_cannot_open_duplicate_active_dispute(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::PAID,
        ]);

        Dispute::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'First dispute',
            'status' => 'pending',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [
                'reason' => 'Second duplicate dispute',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'An active dispute already exists for this order.');
    }

    public function test_admin_can_list_and_view_disputes(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => OrderStatus::DELIVERED]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'Box was empty on delivery.',
            'status' => 'pending',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/disputes')
            ->assertOk()
            ->assertJsonCount(1, 'data.disputes.data')
            ->assertJsonPath('data.disputes.data.0.id', $dispute->id);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/disputes/{$dispute->id}")
            ->assertOk()
            ->assertJsonPath('data.dispute.id', $dispute->id)
            ->assertJsonPath('data.dispute.reason', 'Box was empty on delivery.');
    }

    public function test_admin_can_resolve_dispute_with_polymorphic_gateway_refund(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
            'payment_method' => 'card',
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'Defective item verified by photo.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/refund", [
                'admin_notes' => 'Customer claim approved. Issuing full refund.',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Dispute resolved and order refunded successfully.')
            ->assertJsonPath('data.dispute.status', 'resolved_refunded')
            ->assertJsonPath('data.dispute.admin_notes', 'Customer claim approved. Issuing full refund.')
            ->assertJsonPath('data.dispute.resolved_by', $admin->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refunded',
            'refund_reference' => sprintf('REF-CARD-%06d', $order->id),
        ]);

        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => 'resolved_refunded',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_admin_can_reject_dispute(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'Changed my mind after 60 days.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/reject", [
                'admin_notes' => 'Dispute rejected: return window of 30 days has expired.',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Dispute rejected.')
            ->assertJsonPath('data.dispute.status', 'rejected')
            ->assertJsonPath('data.dispute.admin_notes', 'Dispute rejected: return window of 30 days has expired.')
            ->assertJsonPath('data.dispute.resolved_by', $admin->id);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => 'rejected',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_non_admin_cannot_access_admin_dispute_and_refund_routes(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create(['status' => OrderStatus::PAID]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$order->id}/refund", ['reason' => 'Hack attempt'])
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/admin/disputes')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }
}
