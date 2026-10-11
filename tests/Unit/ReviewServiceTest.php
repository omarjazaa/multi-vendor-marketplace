<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\IneligibleReviewException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalculates_and_caches_average_rating_and_count_on_product(): void
    {
        $product = Product::factory()->create([
            'average_rating' => 0.00,
            'reviews_count' => 0,
        ]);

        $order = Order::factory()->create(['status' => OrderStatus::DELIVERED]);

        Review::factory()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 5,
        ]);
        Review::factory()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'rating' => 4,
        ]);

        $service = new ReviewService;
        $service->recalculateRating($product);

        $product->refresh();
        $this->assertEquals(4.33, (float) $product->average_rating);
        $this->assertSame(3, $product->reviews_count);
    }

    public function test_it_creates_review_and_updates_product_cache_for_delivered_order(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $service = new ReviewService;
        $review = $service->createReview($customer, $product, 5, 'Exceptional quality product!');

        $this->assertInstanceOf(Review::class, $review);
        $this->assertSame(5, $review->rating);
        $this->assertSame('Exceptional quality product!', $review->comment);
        $this->assertSame($customer->id, $review->user_id);
        $this->assertSame($product->id, $review->product_id);
        $this->assertSame($order->id, $review->order_id);

        $product->refresh();
        $this->assertEquals(5.00, (float) $product->average_rating);
        $this->assertSame(1, $product->reviews_count);
    }

    public function test_it_rejects_review_if_order_is_not_delivered(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::SHIPPED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $service = new ReviewService;

        $this->expectException(IneligibleReviewException::class);
        $this->expectExceptionMessage('Only verified buyers with delivered orders can review this product.');

        $service->createReview($customer, $product, 4, 'Still waiting for arrival.');
    }

    public function test_it_rejects_review_if_customer_never_ordered_product(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $otherProduct->id,
        ]);

        $service = new ReviewService;

        $this->expectException(IneligibleReviewException::class);
        $this->expectExceptionMessage('Only verified buyers with delivered orders can review this product.');

        $service->createReview($customer, $product, 5, 'Great from what I heard.');
    }

    public function test_it_rejects_duplicate_review_by_same_customer(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        $service = new ReviewService;
        $service->createReview($customer, $product, 5, 'First review');

        $this->expectException(IneligibleReviewException::class);
        $this->expectExceptionMessage('You have already reviewed this product.');

        $service->createReview($customer, $product, 4, 'Second review attempt');
    }
}
