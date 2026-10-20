<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Day 28: Comprehensive Testing Sprint — Customer Dispute & Admin Refund Workflow Audit.
 *
 * Verifies dispute eligibility boundaries (e.g. cancelled orders), input validation rules,
 * admin rejection lifecycle without state mutation, double resolution guards (409 Conflict),
 * and multi-tenant customer dispute isolation.
 */
class Day28DisputeWorkflowAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.payments.card.simulate_refund_failure' => false]);
    }

    public function test_customer_cannot_open_dispute_on_cancelled_order(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::CANCELLED,
            'payment_method' => 'card',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [
                'reason' => 'Trying to dispute cancelled order',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Unpaid or cancelled orders cannot be disputed.');

        $this->assertDatabaseEmpty('disputes');
    }

    public function test_dispute_creation_validation_requires_reason(): void
    {
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
            'payment_method' => 'card',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/disputes", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseEmpty('disputes');
    }

    public function test_admin_can_reject_dispute_and_order_remains_intact(): void
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
            'reason' => 'Item arrived 1 day late but undamaged.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/reject", [
                'admin_notes' => 'Minor transit delay does not qualify for full refund per terms of service.',
            ])
            ->assertOk();

        $response
            ->assertJsonPath('message', 'Dispute rejected.')
            ->assertJsonPath('data.dispute.status', 'rejected')
            ->assertJsonPath('data.dispute.admin_notes', 'Minor transit delay does not qualify for full refund per terms of service.')
            ->assertJsonPath('data.dispute.resolved_by', $admin->id);

        // Crucial invariant: order remains DELIVERED, not refunded
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
            'refund_reference' => null,
        ]);

        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => 'rejected',
            'resolved_by' => $admin->id,
        ]);
    }

    public function test_admin_cannot_re_resolve_an_already_settled_dispute(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::REFUNDED,
            'payment_method' => 'card',
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'Settled issue',
            'status' => 'resolved_refunded',
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        // Attempting to refund again should be rejected with 409 Conflict
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/refund", [
                'admin_notes' => 'Attempting duplicate refund',
            ])
            ->assertStatus(409);

        // Attempting to reject an already refunded dispute should also be rejected
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/disputes/{$dispute->id}/reject", [
                'admin_notes' => 'Attempting to reject already refunded dispute',
            ])
            ->assertStatus(409);
    }

    public function test_customers_can_only_view_their_own_disputes(): void
    {
        $customerA = $this->customer();
        $customerB = $this->customer();

        $orderA = Order::factory()->create(['user_id' => $customerA->id, 'status' => OrderStatus::DELIVERED]);
        $orderB = Order::factory()->create(['user_id' => $customerB->id, 'status' => OrderStatus::DELIVERED]);

        $disputeA = Dispute::create([
            'order_id' => $orderA->id,
            'user_id' => $customerA->id,
            'reason' => 'Dispute for A',
            'status' => 'pending',
        ]);

        $disputeB = Dispute::create([
            'order_id' => $orderB->id,
            'user_id' => $customerB->id,
            'reason' => 'Dispute for B',
            'status' => 'pending',
        ]);

        // Customer A only sees Dispute A
        $responseA = $this->actingAs($customerA, 'sanctum')
            ->getJson('/api/v1/disputes')
            ->assertOk();

        $responseA->assertJsonCount(1, 'data.disputes.data');
        $responseA->assertJsonPath('data.disputes.data.0.id', $disputeA->id);

        // Customer B only sees Dispute B
        $responseB = $this->actingAs($customerB, 'sanctum')
            ->getJson('/api/v1/disputes')
            ->assertOk();

        $responseB->assertJsonCount(1, 'data.disputes.data');
        $responseB->assertJsonPath('data.disputes.data.0.id', $disputeB->id);
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
