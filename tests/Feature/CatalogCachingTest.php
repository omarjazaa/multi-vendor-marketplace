<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogCachingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $vendor;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $this->store = Store::factory()->create([
            'status' => StoreStatus::APPROVED,
        ]);
    }

    public function test_category_tree_is_cached_and_served_from_cache(): void
    {
        $parent = Category::factory()->create(['name' => 'Electronics', 'parent_id' => null]);
        Category::factory()->create(['name' => 'Phones', 'parent_id' => $parent->id]);

        // First request populates the cache
        $firstResponse = $this->getJson('/api/categories')->assertOk();
        $this->assertCount(1, $firstResponse->json('data.categories'));

        // Query listener to verify cache hit
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        // Second request should be served from cache without DB queries
        $secondResponse = $this->getJson('/api/categories')->assertOk();
        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertSame(0, $queryCount, 'Expected zero database queries on cached category tree request.');
    }

    public function test_category_creation_invalidates_category_cache(): void
    {
        Category::factory()->create(['name' => 'Initial Category', 'parent_id' => null]);

        // Prime cache
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data.categories');

        // Admin creates a new category
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
        ])->assertCreated();

        // Next read must reflect the new category (cache invalidated)
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data.categories');
    }

    public function test_category_update_and_deletion_invalidates_category_cache(): void
    {
        $category = Category::factory()->create(['name' => 'Books', 'slug' => 'books']);

        // Prime cache
        $this->getJson('/api/categories')->assertOk();

        // Update category
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Novels',
        ])->assertOk();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.categories.0.name', 'Novels');

        // Delete category
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertNoContent();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data.categories');
    }

    public function test_product_catalog_listing_is_cached(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // First request primes cache
        $firstResponse = $this->getJson('/api/products')->assertOk();
        $this->assertCount(1, $firstResponse->json('data.products.data'));

        // Query listener
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        // Second request should be served from cache without DB queries
        $secondResponse = $this->getJson('/api/products')->assertOk();
        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertSame(0, $queryCount, 'Expected zero database queries on cached catalog request.');
    }

    public function test_product_update_invalidates_catalog_cache(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Original Laptop',
            'is_active' => true,
        ]);
        Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Prime cache
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.name', 'Original Laptop');

        // Update product via Eloquent / repository
        $product->update(['name' => 'Updated Laptop Pro']);

        // Next request reflects new name
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.name', 'Updated Laptop Pro');
    }

    public function test_inventory_change_invalidates_catalog_cache(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $inventory = Inventory::factory()->create([
            'product_id' => $product->id,
            'quantity' => 0,
            'low_stock_threshold' => 2,
        ]);

        // Prime cache: out of stock
        $this->getJson('/api/products?availability=in_stock')
            ->assertOk()
            ->assertJsonCount(0, 'data.products.data');

        // Restock inventory
        $inventory->update(['quantity' => 50]);

        // Next request shows in_stock product
        $this->getJson('/api/products?availability=in_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data');
    }

    public function test_category_update_invalidates_catalog_cache(): void
    {
        $category = Category::factory()->create(['name' => 'Initial Cat', 'slug' => 'init-cat']);
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 5]);

        // Prime cache
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.category.name', 'Initial Cat');

        // Update category name
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Renamed Cat',
        ])->assertOk();

        // Catalog cache invalidated and reflects updated category
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.category.name', 'Renamed Cat');
    }

    public function test_single_visible_product_view_is_cached_and_invalidated(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Smartphone',
            'is_active' => true,
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 5]);

        // Prime single product cache
        $first = $this->getJson("/api/products/{$product->id}")->assertOk();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        // Hit cache
        $second = $this->getJson("/api/products/{$product->id}")->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->assertSame(0, $queryCount);

        // Update product
        $product->update(['name' => 'Smartphone Ultra']);

        // Next read is fresh
        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.product.name', 'Smartphone Ultra');
    }

    public function test_review_submission_invalidates_catalog_cache(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'is_active' => true,
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 5]);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'store_id' => $this->store->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        // Prime catalog cache
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.average_rating', 0);

        // Submit review
        $this->actingAs($customer, 'sanctum')->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Outstanding quality!',
        ])->assertCreated();

        // Catalog cache should be invalidated and show 5 average rating
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.average_rating', 5);
    }

    public function test_cache_can_be_disabled_via_config(): void
    {
        config(['marketplace.cache.enabled' => false]);

        $parent = Category::factory()->create(['name' => 'Gaming', 'parent_id' => null]);

        $this->getJson('/api/categories')->assertOk();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        // With cache disabled, subsequent requests query the database
        $this->getJson('/api/categories')->assertOk();
        $this->assertGreaterThan(0, $queryCount);
    }
}
