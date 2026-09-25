<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_list_visible_products_with_pagination_meta(): void
    {
        $products = $this->catalogProducts(3);

        $response = $this->getJson('/api/products')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'products' => [
                    'data' => [['id', 'name', 'slug', 'base_price', 'is_active', 'inventory', 'store', 'category', 'images']],
                    'links' => ['first', 'last', 'prev', 'next'],
                    'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
                ],
            ],
            'message',
        ]);

        $response
            ->assertJsonCount(3, 'data.products.data')
            ->assertJsonPath('data.products.meta.total', 3)
            ->assertJsonPath('data.products.meta.current_page', 1)
            ->assertJsonPath('data.products.meta.last_page', 1)
            ->assertJsonPath('data.products.meta.per_page', (int) config('marketplace.catalog.default_per_page'));

        $this->assertEqualsCanonicalizing(
            $products->pluck('id')->all(),
            array_column($response->json('data.products.data'), 'id'),
        );
    }

    public function test_hidden_products_are_excluded_from_the_listing(): void
    {
        $visible = $this->catalogProduct();
        $this->catalogProduct(['is_active' => false]);
        // StoreFactory defaults to a pending store, so this product stays hidden.
        Product::factory()->create();
        Product::factory()->create(['store_id' => Store::factory()->create(['status' => StoreStatus::REJECTED])->id]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.meta.total', 1)
            ->assertJsonPath('data.products.data.0.id', $visible->id);
    }

    public function test_listing_filters_by_category_slug(): void
    {
        $gadgets = Category::factory()->create(['slug' => 'gadgets']);
        $attire = Category::factory()->create(['slug' => 'attire']);
        $gadget = $this->catalogProduct(['category_id' => $gadgets->id]);
        $this->catalogProduct(['category_id' => $attire->id]);

        $this->getJson('/api/products?category=gadgets')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $gadget->id);

        $this->getJson('/api/products?category=unknown')
            ->assertOk()
            ->assertJsonCount(0, 'data.products.data')
            ->assertJsonPath('data.products.meta.total', 0);
    }

    public function test_listing_filters_by_price_range(): void
    {
        $this->catalogProduct(['base_price' => '10.00']);
        $mid = $this->catalogProduct(['base_price' => '50.00']);
        $pricey = $this->catalogProduct(['base_price' => '200.00']);

        $this->getJson('/api/products?min_price=40&max_price=100')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $mid->id)
            ->assertJsonPath('data.products.data.0.base_price', '50.00');

        $this->getJson('/api/products?min_price=150')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $pricey->id);
    }

    public function test_listing_searches_names_and_descriptions(): void
    {
        $kettle = $this->catalogProduct(['name' => 'Copper Kettle', 'description' => 'Brews tea beautifully.']);
        $blanket = $this->catalogProduct(['name' => 'Wool Blanket', 'description' => 'Woven with copper threads.']);

        $this->getJson('/api/products?search=copper')
            ->assertOk()
            ->assertJsonCount(2, 'data.products.data');

        $this->getJson('/api/products?search=kettle')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $kettle->id);

        $this->getJson('/api/products?search=threads')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $blanket->id);

        $this->getJson('/api/products?search=quantum')
            ->assertOk()
            ->assertJsonCount(0, 'data.products.data');
    }

    public function test_availability_filter_reflects_stock_levels(): void
    {
        $inStock = $this->catalogProduct();
        Inventory::factory()->create(['product_id' => $inStock->id, 'quantity' => 50, 'low_stock_threshold' => 5]);

        $low = $this->catalogProduct();
        Inventory::factory()->create(['product_id' => $low->id, 'quantity' => 3, 'low_stock_threshold' => 5]);

        $out = $this->catalogProduct();
        Inventory::factory()->create(['product_id' => $out->id, 'quantity' => 0, 'low_stock_threshold' => 5]);

        $untracked = $this->catalogProduct();

        $this->getJson('/api/products?availability=in_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $inStock->id);

        $this->getJson('/api/products?availability=low_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data.products.data')
            ->assertJsonPath('data.products.data.0.id', $low->id);

        $response = $this->getJson('/api/products?availability=out_of_stock')
            ->assertOk()
            ->assertJsonCount(2, 'data.products.data');

        $this->assertEqualsCanonicalizing(
            [$out->id, $untracked->id],
            array_column($response->json('data.products.data'), 'id'),
        );
    }

    public function test_listing_sorts_by_configured_keys(): void
    {
        $thirty = $this->catalogProduct(['base_price' => '30.00']);
        $ten = $this->catalogProduct(['base_price' => '10.00']);
        $twenty = $this->catalogProduct(['base_price' => '20.00']);

        // Default sort is "latest"; identical timestamps fall back to id descending.
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.id', $twenty->id)
            ->assertJsonPath('data.products.data.2.id', $thirty->id);

        $this->getJson('/api/products?sort=price_asc')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.id', $ten->id)
            ->assertJsonPath('data.products.data.0.base_price', '10.00')
            ->assertJsonPath('data.products.data.2.id', $thirty->id);

        $this->getJson('/api/products?sort=price_desc')
            ->assertOk()
            ->assertJsonPath('data.products.data.0.id', $thirty->id)
            ->assertJsonPath('data.products.data.0.base_price', '30.00');
    }

    public function test_per_page_and_page_slice_the_results(): void
    {
        $products = $this->catalogProducts(15);

        $response = $this->getJson('/api/products?per_page=10&page=2')->assertOk();

        $response
            ->assertJsonCount(5, 'data.products.data')
            ->assertJsonPath('data.products.meta.current_page', 2)
            ->assertJsonPath('data.products.meta.last_page', 2)
            ->assertJsonPath('data.products.meta.per_page', 10)
            ->assertJsonPath('data.products.meta.total', 15);

        // "latest" ordering pages ids 15..6 first, so page two holds ids 5..1.
        $this->assertEqualsCanonicalizing(
            $products->slice(0, 5)->pluck('id')->all(),
            array_column($response->json('data.products.data'), 'id'),
        );
    }

    public function test_guests_can_view_a_visible_product_with_relations(): void
    {
        $product = $this->catalogProduct(['name' => 'Desk Lamp']);
        Inventory::factory()->create(['product_id' => $product->id, 'quantity' => 7, 'low_stock_threshold' => 5]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Success.')
            ->assertJsonPath('data.product.name', 'Desk Lamp')
            ->assertJsonPath('data.product.inventory.quantity', 7)
            ->assertJsonPath('data.product.inventory.is_low_stock', false)
            ->assertJsonPath('data.product.inventory.is_out_of_stock', false)
            ->assertJsonPath('data.product.store.id', $product->store_id)
            ->assertJsonPath('data.product.category.slug', $product->category->slug)
            ->assertJsonStructure([
                'data' => [
                    'product' => [
                        'id', 'name', 'slug', 'base_price',
                        'images', 'inventory', 'store', 'category',
                    ],
                ],
                'message',
            ]);
    }

    public function test_show_returns_404_for_missing_inactive_or_hidden_products(): void
    {
        $inactive = $this->catalogProduct(['is_active' => false]);
        $pendingStoreProduct = Product::factory()->create();
        $visible = $this->catalogProduct();

        $this->getJson('/api/products/999999')->assertNotFound();
        $this->getJson('/api/products/not-a-number')->assertNotFound();
        $this->getJson("/api/products/{$inactive->id}")->assertNotFound();
        $this->getJson("/api/products/{$pendingStoreProduct->id}")->assertNotFound();
        $this->getJson("/api/products/{$visible->id}")->assertOk();
    }

    public function test_invalid_catalog_filters_are_rejected(): void
    {
        $this->getJson('/api/products?per_page=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/products?sort=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');

        $this->getJson('/api/products?availability=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('availability');

        $this->getJson('/api/products?min_price=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('min_price');
    }

    public function test_product_payload_exposes_only_public_fields(): void
    {
        $product = $this->catalogProduct();

        $response = $this->getJson("/api/products/{$product->id}")->assertOk();

        $this->assertEqualsCanonicalizing(['data', 'message'], array_keys($response->json()));

        $this->assertEqualsCanonicalizing([
            'id', 'store_id', 'category_id', 'name', 'slug', 'description', 'base_price',
            'is_active', 'images', 'inventory', 'store', 'category', 'created_at', 'updated_at',
        ], array_keys($response->json('data.product')));
    }

    private function catalogProduct(array $attributes = []): Product
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        return Product::factory()->create(['store_id' => $store->id] + $attributes);
    }

    /** @return Collection<int, Product> */
    private function catalogProducts(int $count, array $attributes = []): Collection
    {
        $store = Store::factory()->create(['status' => StoreStatus::APPROVED]);

        return Product::factory($count)->create(['store_id' => $store->id] + $attributes);
    }
}
