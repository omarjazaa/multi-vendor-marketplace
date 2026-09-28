<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_fixed_coupon(): void
    {
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/coupons', [
                'code' => 'SAVE10',
                'type' => 'fixed',
                'value' => 10,
                'expires_at' => now()->addDays(14)->toIso8601String(),
                'usage_limit' => 100,
                'min_order_amount' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Coupon created.')
            ->assertJsonPath('data.coupon.code', 'SAVE10')
            ->assertJsonPath('data.coupon.type', 'fixed')
            ->assertJsonPath('data.coupon.value', '10.00')
            ->assertJsonPath('data.coupon.usage_limit', 100)
            ->assertJsonPath('data.coupon.used_count', 0)
            ->assertJsonStructure([
                'data' => [
                    'coupon' => [
                        'id', 'code', 'type', 'value', 'expires_at', 'usage_limit',
                        'used_count', 'min_order_amount', 'created_by', 'created_at', 'updated_at',
                    ],
                ],
                'message',
            ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE10',
            'type' => 'fixed',
            'usage_limit' => 100,
            'min_order_amount' => '50.00',
        ]);
    }

    public function test_vendor_can_create_a_percentage_coupon(): void
    {
        $this->actingAs($this->vendor(), 'sanctum')
            ->postJson('/api/vendor/coupons', [
                'code' => 'PCT15',
                'type' => 'percentage',
                'value' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Coupon created.')
            ->assertJsonPath('data.coupon.code', 'PCT15')
            ->assertJsonPath('data.coupon.type', 'percentage')
            ->assertJsonPath('data.coupon.value', '15.00');

        $this->assertDatabaseCount('coupons', 1);
    }

    public function test_only_admins_and_vendors_may_create_coupons(): void
    {
        $payload = ['code' => 'SAVE10', 'type' => 'fixed', 'value' => 10];

        // Guest checks first: actingAs below would otherwise persist.
        foreach (['/api/admin/coupons', '/api/vendor/coupons'] as $endpoint) {
            $this->postJson($endpoint, $payload)->assertUnauthorized();
        }

        foreach (['/api/admin/coupons', '/api/vendor/coupons'] as $endpoint) {
            $this->actingAs($this->customer(), 'sanctum')
                ->postJson($endpoint, $payload)
                ->assertForbidden();
        }

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_coupon_codes_are_normalised_to_uppercase(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => ' save20 ', 'type' => 'fixed', 'value' => 20])
            ->assertCreated()
            ->assertJsonPath('data.coupon.code', 'SAVE20');

        $this->assertDatabaseHas('coupons', ['code' => 'SAVE20']);

        // The same code in any casing collides with the stored, normalised one.
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => 'Save20', 'type' => 'fixed', 'value' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_coupon_creation_validates_its_payload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'type', 'value']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => 'BAD!', 'type' => 'bogus', 'value' => 10])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'type']);

        // Percentages above 100 would pay the customer to order.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => 'TOOBIG', 'type' => 'percentage', 'value' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', [
                'code' => 'PAST', 'type' => 'fixed', 'value' => 10,
                'expires_at' => now()->subDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', [
                'code' => 'ZEROUSE', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('usage_limit');

        $this->assertDatabaseCount('coupons', 0);
    }

    public function test_a_fixed_coupon_may_exceed_one_hundred(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/coupons', ['code' => 'BIG150', 'type' => 'fixed', 'value' => 150])
            ->assertCreated()
            ->assertJsonPath('data.coupon.value', '150.00');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }

    private function vendor(): User
    {
        return User::factory()->create(['role' => UserRole::VENDOR]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }
}
