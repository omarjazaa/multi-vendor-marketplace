<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidCouponException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShowCartRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use App\Services\Pricing\CartSummary;
use App\Services\PricingService;
use App\Services\ProductCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CartService $cart,
        private readonly ProductCatalogService $catalog,
        private readonly PricingService $pricing,
    ) {}

    /**
     * View the cart with its pricing summary (Day 14): subtotal, discount,
     * tax and total from the Decorator pricing engine. Pass ?coupon=CODE to
     * preview a coupon without applying it to the stored cart.
     */
    public function show(ShowCartRequest $request): JsonResponse
    {
        $cart = $this->cart->forUser((int) $request->user()->id);

        try {
            $summary = $this->pricing->summarize($cart, $request->validated('coupon'));
        } catch (InvalidCouponException $exception) {
            return $this->couponConflict($exception);
        }

        return $this->respondWithCart($cart, summary: $summary);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        // Catalog visibility decides 404 before any cart mutation happens.
        $product = $this->catalog->findVisible($request->integer('product_id'));
        $cart = $this->cart->forUser((int) $request->user()->id);

        try {
            $this->cart->addItem($cart, $product, $request->integer('quantity'));
        } catch (InsufficientStockException $exception) {
            return $this->stockConflict($exception);
        }

        return $this->respondWithCart($cart, 'Item added to cart.', 201);
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        Gate::authorize('update', $item->cart);

        try {
            $this->cart->updateQuantity($item, $request->integer('quantity'));
        } catch (InsufficientStockException $exception) {
            return $this->stockConflict($exception);
        }

        return $this->respondWithCart($item->cart, 'Cart updated.');
    }

    public function destroy(CartItem $item): JsonResponse
    {
        Gate::authorize('delete', $item->cart);

        $cart = $item->cart;
        $this->cart->removeItem($item);

        return $this->respondWithCart($cart, 'Item removed from cart.');
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cart->forUser((int) $request->user()->id);
        Gate::authorize('delete', $cart);

        $this->cart->clear($cart);

        return $this->respondWithCart($cart, 'Cart cleared.');
    }

    /** Return the cart freshly loaded with its items, products and live pricing breakdown. */
    private function respondWithCart(
        Cart $cart,
        string $message = 'Success.',
        int $status = 200,
        ?CartSummary $summary = null,
    ): JsonResponse {
        $cart->load('items.product');

        return $this->successResponse(
            [
                'cart' => CartResource::make($cart),
                // Every cart response carries the current pricing summary;
                // show() may pass one already computed against a coupon.
                'summary' => ($summary ?? $this->pricing->summarize($cart))->toArray(),
            ],
            $message,
            $status,
        );
    }

    /** Signal that the requested coupon cannot price this cart. */
    private function couponConflict(InvalidCouponException $exception): JsonResponse
    {
        return $this->errorResponse(
            'Coupon cannot be applied.',
            ['coupon' => $exception->getMessage()],
            422,
        );
    }

    /** Signal that the requested quantity outran the available stock. */
    private function stockConflict(InsufficientStockException $exception): JsonResponse
    {
        return $this->errorResponse(
            'Insufficient stock for this product.',
            ['quantity' => "Only {$exception->available} units are available."],
            409,
        );
    }
}
