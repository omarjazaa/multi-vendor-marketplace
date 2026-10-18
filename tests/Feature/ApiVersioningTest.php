<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API Versioning Tests (Day 26 — PRD §5 Week 4).
 *
 * Verifies API version routing (/api/v1/...), header injection (X-API-Version, X-Request-Id),
 * content negotiation, and backward compatibility with legacy unversioned routes.
 */
class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_include_version_and_request_id_headers(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-Request-Id');

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    public function test_api_respects_client_provided_request_id_header(): void
    {
        $customRequestId = (string) Str::uuid();

        $response = $this->withHeaders([
            'X-Request-Id' => $customRequestId,
        ])->getJson('/api/v1/categories');

        $response->assertOk()
            ->assertHeader('X-Request-Id', $customRequestId);
    }

    public function test_api_v1_public_catalog_and_category_endpoints_are_accessible(): void
    {
        $category = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'store_id' => $store->id,
            'name' => 'Pro Laptop',
            'is_active' => true,
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 15]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.categories.0.slug', 'laptops');

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.name', 'Pro Laptop');

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_api_v1_auth_workflow_works_as_expected(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Version Tester',
            'email' => 'v1user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UserRole::CUSTOMER->value,
        ]);

        $registerResponse->assertCreated()
            ->assertJsonPath('data.user.email', 'v1user@example.com')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'role'], 'token']]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'v1user@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    public function test_unsupported_api_version_is_rejected_with_bad_request(): void
    {
        $response = $this->withHeaders([
            'X-API-Version' => 'v99',
        ])->getJson('/api/v1/categories');

        $response->assertStatus(400)
            ->assertJsonPath('message', "Unsupported API version requested: 'v99'. Supported versions: v1.")
            ->assertJsonValidationErrors('api_version');
    }

    public function test_unsupported_version_via_vendor_accept_header_is_rejected(): void
    {
        $response = $this->get('/api/v1/categories', [
            'Accept' => 'application/vnd.marketplace.v99+json',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', "Unsupported API version requested: 'v99'. Supported versions: v1.");
    }

    public function test_supported_version_via_vendor_accept_header_is_accepted(): void
    {
        $response = $this->get('/api/v1/categories', [
            'Accept' => 'application/vnd.marketplace.v1+json',
        ]);

        $response->assertOk()
            ->assertHeader('X-API-Version', 'v1');
    }

    public function test_legacy_unversioned_routes_continue_to_operate_seamlessly(): void
    {
        Category::factory()->create(['name' => 'Legacy Books', 'slug' => 'legacy-books']);

        $unversionedResponse = $this->getJson('/api/categories')->assertOk();
        $versionedResponse = $this->getJson('/api/v1/categories')->assertOk();

        $this->assertSame(
            $unversionedResponse->json('data.categories'),
            $versionedResponse->json('data.categories'),
        );
    }
}
