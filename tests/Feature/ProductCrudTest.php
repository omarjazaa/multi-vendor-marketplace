<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_create_and_list_products_for_their_store(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $store = $this->approvedStoreFor($vendor);
        $category = Category::factory()->create();

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/products', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => 'Handmade Lamp',
                'slug' => 'handmade-lamp',
                'description' => 'A warm desk lamp.',
                'base_price' => '49.99',
            ])
            ->assertCreated()
            ->assertJsonPath('data.product.name', 'Handmade Lamp');

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products');
    }

    public function test_vendor_can_update_and_delete_their_own_product(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}", [
                'name' => 'Updated Product',
                'base_price' => '75.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.product.name', 'Updated Product');

        $this->actingAs($vendor, 'sanctum')
            ->deleteJson("/api/vendor/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_vendor_cannot_manage_another_vendors_product(): void
    {
        $owner = User::factory()->create(['role' => UserRole::VENDOR]);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($owner)->id]);

        $this->actingAs($otherVendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}", ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($otherVendor, 'sanctum')
            ->deleteJson("/api/vendor/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_only_vendors_can_use_product_crud(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $product = Product::factory()->create();

        foreach ([$customer, $admin] as $user) {
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/vendor/products')
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->putJson("/api/vendor/products/{$product->id}", ['name' => 'Blocked'])
                ->assertForbidden();
        }
    }

    public function test_product_creation_validates_store_ownership_and_unique_slug(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $store = $this->approvedStoreFor($vendor);
        $category = Category::factory()->create();
        Product::factory()->create(['slug' => 'existing-slug']);

        $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/products', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => 'Duplicate',
                'slug' => 'existing-slug',
                'base_price' => '10.00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');
    }

    public function test_product_routes_require_authentication(): void
    {
        $this->getJson('/api/vendor/products')->assertUnauthorized();
    }

    private function approvedStoreFor(User $vendor): Store
    {
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);

        return Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);
    }
}
