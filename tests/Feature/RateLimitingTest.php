<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Rate Limiting Tests (Day 26 — PRD §5 Week 4).
 *
 * Verifies rate limiter enforcement on sensitive operations:
 * - Authentication (login/register)
 * - Checkout submission
 * - Asynchronous vendor report generation
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('auth');
        RateLimiter::clear('checkout');
        RateLimiter::clear('reports');
    }

    public function test_auth_rate_limiter_throttles_after_limit_is_exceeded(): void
    {
        $payload = [
            'email' => 'throttled@example.com',
            'password' => 'wrong-password',
        ];

        // First 10 attempts pass to controller (returning 422 for bad credentials)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)
                ->assertStatus(422);
        }

        // 11th attempt is blocked by rate limiter
        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(429)
            ->assertJsonPath('message', 'Too many authentication attempts. Please try again later.')
            ->assertJsonPath('errors.rate_limit.0', 'Too many authentication attempts. Please try again later.')
            ->assertHeader('Retry-After');
    }

    public function test_checkout_rate_limiter_throttles_repeated_checkout_attempts(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'base_price' => '50.00',
            'is_active' => true,
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 100]);

        // Trigger 10 checkout calls (empty cart or invalid will pass the rate limiter guard to the controller)
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/v1/checkout', [
                    'payment_method' => 'cod',
                ]);
        }

        // 11th call gets throttled
        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout', [
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('message', 'Too many checkout attempts. Please try again later.')
            ->assertHeader('Retry-After');
    }

    public function test_reports_rate_limiter_throttles_rapid_report_generation(): void
    {
        Queue::fake();

        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        // First 5 requests succeed (202 Accepted)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($vendor, 'sanctum')
                ->postJson('/api/v1/vendor/analytics/reports', [
                    'type' => 'sales_summary',
                ])
                ->assertStatus(202);
        }

        // 6th request is throttled
        $response = $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/v1/vendor/analytics/reports', [
                'type' => 'sales_summary',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('message', 'Too many report generation requests. Please wait a moment.')
            ->assertHeader('Retry-After');
    }
}
