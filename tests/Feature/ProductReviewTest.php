<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_guest_can_list_reviews_for_product(): void
    {
        $product = $this->createApprovedProduct();
        $order = Order::factory()->create(['status' => OrderStatus::DELIVERED]);

        $reviewer = User::factory()->create(['name' => 'Alice']);
        Review::factory()->create([
            'user_id' => $reviewer->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Fantastic product!',
        ]);

        $response = $this->getJson("/api/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'product' => ['id', 'average_rating', 'reviews_count'],
                    'reviews' => [
                        'data' => [
                            ['id', 'product_id', 'rating', 'comment', 'user' => ['id', 'name'], 'created_at'],
                        ],
                        'links',
                        'meta',
                    ],
                ],
                'message',
            ]);

        $response
            ->assertJsonPath('data.reviews.data.0.rating', 5)
            ->assertJsonPath('data.reviews.data.0.comment', 'Fantastic product!')
            ->assertJsonPath('data.reviews.data.0.user.name', 'Alice');
    }

    public function test_guest_cannot_submit_review_unauthenticated(): void
    {
        $product = $this->createApprovedProduct();

        $this->postJson("/api/products/{$product->id}/reviews", [
            'rating' => 5,
            'comment' => 'Great product',
        ])->assertUnauthorized();
    }

    public function test_vendor_role_cannot_submit_review(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $product = $this->createApprovedProduct();

        $this->actingAs($vendor, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
            ])
            ->assertForbidden();
    }

    public function test_customer_with_delivered_order_can_submit_review_and_product_caches_rating(): void
    {
        $customer = $this->customer();
        $product = $this->createApprovedProduct();
        $this->deliveredOrderFor($customer, $product);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'Loved it, arrived quickly and high quality.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Review submitted successfully.')
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('data.review.comment', 'Loved it, arrived quickly and high quality.')
            ->assertJsonPath('data.product.average_rating', fn ($val): bool => (float) $val === 5.0)
            ->assertJsonPath('data.product.reviews_count', 1);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        $product->refresh();
        $this->assertEquals(5.00, (float) $product->average_rating);
        $this->assertSame(1, $product->reviews_count);
    }

    public function test_customer_cannot_review_product_with_non_delivered_order(): void
    {
        $customer = $this->customer();
        $product = $this->createApprovedProduct();

        // Order is still shipped, not delivered
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'Tried early review',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only verified buyers with delivered orders can review this product.')
            ->assertJsonPath('errors.review', 'Only verified buyers with delivered orders can review this product.');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_customer_cannot_review_product_they_never_ordered(): void
    {
        $customer = $this->customer();
        $product = $this->createApprovedProduct();
        $otherProduct = $this->createApprovedProduct();

        // Customer ordered a different product
        $this->deliveredOrderFor($customer, $otherProduct);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'Never ordered this',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only verified buyers with delivered orders can review this product.');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_customer_cannot_review_same_product_multiple_times(): void
    {
        $customer = $this->customer();
        $product = $this->createApprovedProduct();
        $this->deliveredOrderFor($customer, $product);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => 'First review',
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 4,
                'comment' => 'Second review duplicate attempt',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already reviewed this product.')
            ->assertJsonPath('errors.review', 'You have already reviewed this product.');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_review_validation_rules(): void
    {
        $customer = $this->customer();
        $product = $this->createApprovedProduct();
        $this->deliveredOrderFor($customer, $product);

        // Rating missing
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating out of bounds (0 or 6)
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Comment too long (>1000)
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
                'comment' => str_repeat('a', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_average_rating_and_count_are_cached_and_visible_in_public_catalog(): void
    {
        $customer1 = $this->customer();
        $customer2 = $this->customer();
        $product = $this->createApprovedProduct();

        $this->deliveredOrderFor($customer1, $product);
        $this->deliveredOrderFor($customer2, $product);

        $this->actingAs($customer1, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 4])
            ->assertCreated();

        $this->actingAs($customer2, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 5])
            ->assertCreated();

        $product->refresh();
        $this->assertEquals(4.50, (float) $product->average_rating);
        $this->assertSame(2, $product->reviews_count);

        // Public show endpoint reflects cached rating
        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.product.average_rating', 4.5)
            ->assertJsonPath('data.product.reviews_count', 2);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => UserRole::CUSTOMER]);
    }

    private function createApprovedProduct(): Product
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $profile = VendorProfile::factory()->create([
            'user_id' => $vendor->id,
            'verification_status' => StoreStatus::APPROVED,
        ]);
        $store = Store::factory()->create([
            'vendor_profile_id' => $profile->id,
            'status' => StoreStatus::APPROVED,
        ]);

        return Product::factory()->create([
            'store_id' => $store->id,
            'is_active' => true,
        ]);
    }

    private function deliveredOrderFor(User $customer, Product $product): Order
    {
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'store_id' => $product->store_id,
            'status' => OrderStatus::DELIVERED,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        return $order;
    }
}
