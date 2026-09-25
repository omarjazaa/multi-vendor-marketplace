<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Traits\ApiResponse;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
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
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respondWithCart(
            $this->cart->forUser((int) $request->user()->id),
        );
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

    /** Return the cart freshly loaded with its items and products. */
    private function respondWithCart(Cart $cart, string $message = 'Success.', int $status = 200): JsonResponse
    {
        return $this->successResponse(
            ['cart' => CartResource::make($cart->load('items.product'))],
            $message,
            $status,
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
