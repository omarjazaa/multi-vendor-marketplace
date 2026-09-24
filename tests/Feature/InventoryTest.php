<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_product_opens_an_empty_stock_record(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $store = $this->approvedStoreFor($vendor);
        $category = Category::factory()->create();

        $response = $this->actingAs($vendor, 'sanctum')
            ->postJson('/api/vendor/products', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => 'Trail Mug',
                'slug' => 'trail-mug',
                'base_price' => '19.50',
            ])
            ->assertCreated()
            ->assertJsonPath('data.product.inventory.quantity', 0)
            ->assertJsonPath('data.product.inventory.is_low_stock', false)
            ->assertJsonPath('data.product.inventory.is_out_of_stock', true);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $response->json('data.product.id'),
            'quantity' => 0,
            'low_stock_threshold' => (int) config('marketplace.inventory.default_low_stock_threshold'),
        ]);
    }

    public function test_vendor_can_view_inventory_for_their_product(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson("/api/vendor/products/{$product->id}/inventory")
            ->assertOk()
            ->assertJsonPath('data.inventory.quantity', 0)
            ->assertJsonPath('data.inventory.is_out_of_stock', true)
            ->assertJsonStructure([
                'data' => [
                    'inventory' => [
                        'id', 'product_id', 'quantity', 'low_stock_threshold',
                        'is_low_stock', 'is_out_of_stock', 'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 0]);
    }

    public function test_vendor_can_update_stock_above_the_threshold(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => 25])
            ->assertOk()
            ->assertJsonPath('data.inventory.quantity', 25)
            ->assertJsonPath('data.inventory.is_low_stock', false)
            ->assertJsonPath('data.inventory.is_out_of_stock', false);

        $this->assertDatabaseHas('inventories', ['product_id' => $product->id, 'quantity' => 25]);
    }

    public function test_stock_level_drives_the_low_and_out_of_stock_flags(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        $threshold = (int) config('marketplace.inventory.default_low_stock_threshold');

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => $threshold])
            ->assertOk()
            ->assertJsonPath('data.inventory.low_stock_threshold', $threshold)
            ->assertJsonPath('data.inventory.is_low_stock', true)
            ->assertJsonPath('data.inventory.is_out_of_stock', false);

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => 0])
            ->assertOk()
            ->assertJsonPath('data.inventory.is_low_stock', false)
            ->assertJsonPath('data.inventory.is_out_of_stock', true);
    }

    public function test_vendor_can_change_the_low_stock_threshold(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", [
                'quantity' => 40,
                'low_stock_threshold' => 50,
            ])
            ->assertOk()
            ->assertJsonPath('data.inventory.low_stock_threshold', 50)
            ->assertJsonPath('data.inventory.is_low_stock', true);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'quantity' => 40,
            'low_stock_threshold' => 50,
        ]);
    }

    public function test_stock_update_validates_the_request(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        $max = (int) config('marketplace.inventory.max_quantity');

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", [
                'quantity' => -1,
                'low_stock_threshold' => -5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'low_stock_threshold']);

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => 'many'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($vendor, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => $max + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseMissing('inventories', ['product_id' => $product->id]);
    }

    public function test_vendor_cannot_manage_inventory_of_another_vendors_product(): void
    {
        $owner = User::factory()->create(['role' => UserRole::VENDOR]);
        $intruder = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($owner)->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/vendor/products/{$product->id}/inventory")
            ->assertForbidden();

        $this->actingAs($intruder, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => 10])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventories', ['product_id' => $product->id]);
    }

    public function test_inventory_routes_require_authentication_and_a_vendor_role(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $this->getJson("/api/vendor/products/{$product->id}/inventory")->assertUnauthorized();

        $this->actingAs($customer, 'sanctum')
            ->putJson("/api/vendor/products/{$product->id}/inventory", ['quantity' => 5])
            ->assertForbidden();
    }

    public function test_vendor_product_list_includes_the_stock_record(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'low_stock_threshold' => 5,
        ]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/products')
            ->assertOk()
            ->assertJsonPath('data.products.0.inventory.quantity', 3)
            ->assertJsonPath('data.products.0.inventory.is_low_stock', true)
            ->assertJsonPath('data.products.0.inventory.is_out_of_stock', false);
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
