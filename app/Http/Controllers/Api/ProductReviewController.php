<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IneligibleReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ProductReviewController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReviewService $reviews) {}

    /**
     * List paginated reviews for a given product (Public catalog endpoint).
     */
    public function index(Product $product, Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $reviews = $this->reviews->getReviews($product, $perPage);

        return $this->successResponse([
            'product' => [
                'id' => $product->id,
                'average_rating' => (float) $product->average_rating,
                'reviews_count' => (int) $product->reviews_count,
            ],
            'reviews' => $this->paginatedReviews($reviews, $request),
        ]);
    }

    /**
     * Submit a customer review for a product from a verified delivered order (role:customer).
     */
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        try {
            $review = $this->reviews->createReview(
                $request->user(),
                $product,
                (int) $request->validated('rating'),
                $request->validated('comment'),
            );
        } catch (IneligibleReviewException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['review' => $exception->getMessage()],
                422,
            );
        }

        return $this->successResponse(
            [
                'review' => ReviewResource::make($review),
                'product' => [
                    'id' => $product->id,
                    'average_rating' => (float) $product->average_rating,
                    'reviews_count' => (int) $product->reviews_count,
                ],
            ],
            'Review submitted successfully.',
            201,
        );
    }

    /** Shape paginated reviews inside standard API envelope. */
    private function paginatedReviews(LengthAwarePaginator $reviews, Request $request): array
    {
        $paginated = $reviews->toArray();

        return [
            'data' => ReviewResource::collection($reviews->getCollection())->resolve($request),
            'links' => [
                'first' => $paginated['first_page_url'],
                'last' => $paginated['last_page_url'],
                'prev' => $paginated['prev_page_url'],
                'next' => $paginated['next_page_url'],
            ],
            'meta' => Arr::except($paginated, [
                'data', 'first_page_url', 'last_page_url', 'prev_page_url', 'next_page_url',
            ]),
        ];
    }
}
