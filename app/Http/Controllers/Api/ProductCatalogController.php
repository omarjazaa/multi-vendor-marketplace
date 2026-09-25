<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductsRequest;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ProductCatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ProductCatalogService $catalog) {}

    public function index(IndexProductsRequest $request): JsonResponse
    {
        $products = $this->catalog->index($request->validated());

        return $this->successResponse([
            'products' => $this->paginatedProducts($products, $request),
        ]);
    }

    public function show(int $product): JsonResponse
    {
        return $this->successResponse([
            'product' => ProductResource::make($this->catalog->findVisible($product)),
        ]);
    }

    /**
     * Shape the paginator as { data, links, meta } inside the API envelope.
     *
     * @return array{data: array<int, mixed>, links: array<string, string|null>, meta: array<string, mixed>}
     */
    private function paginatedProducts(LengthAwarePaginator $products, Request $request): array
    {
        $paginated = $products->toArray();

        return [
            'data' => ProductResource::collection($products->getCollection())->resolve($request),
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
