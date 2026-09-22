<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_upload_multiple_images_and_receive_resource_data(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $response = $this->actingAs($vendor, 'sanctum')->postJson(
            "/api/vendor/products/{$product->id}/images",
            [
                'images' => [
                    UploadedFile::fake()->image('front.jpg'),
                    UploadedFile::fake()->image('side.png'),
                ],
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.images')
            ->assertJsonStructure(['data' => ['images' => [['id', 'url', 'path', 'is_primary']]]]);

        $this->assertDatabaseCount('product_images', 2);
        Storage::disk('public')->assertExists('products/'.$product->id.'/front.jpg');
        Storage::disk('public')->assertExists('products/'.$product->id.'/side.png');
    }

    public function test_vendor_can_delete_an_image_from_their_product(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        $this->actingAs($vendor, 'sanctum')->postJson("/api/vendor/products/{$product->id}/images", [
            'images' => [UploadedFile::fake()->image('remove.jpg')],
        ])->assertCreated();
        $image = $product->images()->firstOrFail();

        $this->actingAs($vendor, 'sanctum')
            ->deleteJson("/api/vendor/products/{$product->id}/images/{$image->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($image->path);
    }

    public function test_vendor_cannot_manage_images_for_another_vendors_product(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => UserRole::VENDOR]);
        $otherVendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($owner)->id]);

        $this->actingAs($otherVendor, 'sanctum')
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->image('blocked.jpg')],
            ])
            ->assertForbidden();
    }

    public function test_image_upload_validates_file_type_and_count(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');
    }

    public function test_first_uploaded_image_becomes_primary_and_primary_is_promoted_after_deletion(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.jpg'),
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.images.0.is_primary', true)
            ->assertJsonPath('data.images.1.is_primary', false);

        $primary = ProductImage::where('is_primary', true)->sole();
        $this->assertSame('products/'.$product->id.'/first.jpg', $primary->path);

        $this->actingAs($vendor, 'sanctum')
            ->deleteJson("/api/vendor/products/{$product->id}/images/{$primary->id}")
            ->assertNoContent();

        $remaining = ProductImage::sole();
        $this->assertSame('products/'.$product->id.'/second.jpg', $remaining->path);
        $this->assertTrue($remaining->is_primary);
    }

    public function test_vendor_can_list_the_images_of_their_product(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        ProductImage::factory()->count(2)->create(['product_id' => $product->id]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson("/api/vendor/products/{$product->id}/images")
            ->assertOk()
            ->assertJsonCount(2, 'data.images');
    }

    public function test_uploading_more_images_than_allowed_in_one_request_is_rejected(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        $limit = (int) config('marketplace.products.images.max_per_product');

        $files = collect(range(1, $limit + 1))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("image-{$index}.jpg"))
            ->all();

        $this->actingAs($vendor, 'sanctum')
            ->postJson("/api/vendor/products/{$product->id}/images", ['images' => $files])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_uploading_beyond_the_product_image_limit_is_rejected(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = Product::factory()->create(['store_id' => $this->approvedStoreFor($vendor)->id]);
        $limit = (int) config('marketplace.products.images.max_per_product');
        ProductImage::factory()->count($limit)->create(['product_id' => $product->id]);

        $this->actingAs($vendor, 'sanctum')
            ->postJson("/api/vendor/products/{$product->id}/images", [
                'images' => [UploadedFile::fake()->image('overflow.jpg')],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');

        $this->assertDatabaseCount('product_images', $limit);
    }

    public function test_vendor_cannot_delete_an_image_that_belongs_to_another_product(): void
    {
        Storage::fake('public');
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $store = $this->approvedStoreFor($vendor);
        $product = Product::factory()->create(['store_id' => $store->id]);
        $otherProduct = Product::factory()->create(['store_id' => $store->id]);
        $image = ProductImage::factory()->create(['product_id' => $otherProduct->id]);

        $this->actingAs($vendor, 'sanctum')
            ->deleteJson("/api/vendor/products/{$product->id}/images/{$image->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
    }

    public function test_image_routes_require_authentication_and_a_vendor_role(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $this->getJson("/api/vendor/products/{$product->id}/images")->assertUnauthorized();

        $this->actingAs($customer, 'sanctum')
            ->getJson("/api/vendor/products/{$product->id}/images")
            ->assertForbidden();
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
