<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\IneligibleReviewException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Product Reviews & Ratings (Day 22).
 *
 * Enforces the rule that customers can only review products from orders
 * that reached DELIVERED status, and maintains cached rating aggregates
 * directly on the product row.
 */
class ReviewService
{
    /**
     * Submit a review for a product from a verified delivered order.
     *
     * @throws IneligibleReviewException when the user already reviewed or has no delivered order
     */
    public function createReview(User $user, Product $product, int $rating, ?string $comment = null): Review
    {
        $hasReviewed = Review::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->exists();

        if ($hasReviewed) {
            throw new IneligibleReviewException('You have already reviewed this product.');
        }

        // Verify the customer purchased this product in an order that has been delivered
        $deliveredOrder = Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::DELIVERED)
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->first();

        if (! $deliveredOrder) {
            throw new IneligibleReviewException('Only verified buyers with delivered orders can review this product.');
        }

        return DB::transaction(function () use ($user, $product, $deliveredOrder, $rating, $comment): Review {
            $review = Review::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'order_id' => $deliveredOrder->id,
                'rating' => $rating,
                'comment' => $comment,
            ]);

            $this->recalculateRating($product);

            return $review->load(['user', 'product']);
        });
    }

    /** Recalculate and cache average rating and review count on the product. */
    public function recalculateRating(Product $product): void
    {
        $stats = Review::query()
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as count')
            ->first();

        $average = $stats ? round((float) $stats->avg_rating, 2) : 0.00;
        $count = $stats ? (int) $stats->count : 0;

        $product->update([
            'average_rating' => $average,
            'reviews_count' => $count,
        ]);
    }

    /** List paginated reviews for a product with author profiles. */
    public function getReviews(Product $product, int $perPage = 15): LengthAwarePaginator
    {
        return $product->reviews()
            ->with('user')
            ->paginate($perPage);
    }
}
