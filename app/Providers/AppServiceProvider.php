<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Events\VendorApproved;
use App\Listeners\SendVendorWelcomeNotification;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use App\Observers\CategoryObserver;
use App\Observers\InventoryObserver;
use App\Observers\ProductImageObserver;
use App\Observers\ProductObserver;
use App\Observers\ReviewObserver;
use App\Observers\StoreObserver;
use App\Policies\CartPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Repositories\CachedCategoryRepository;
use App\Repositories\CachedProductRepository;
use App\Repositories\Contracts\CartRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\StoreRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentCartRepository;
use App\Repositories\EloquentCouponRepository;
use App\Repositories\EloquentDisputeRepository;
use App\Repositories\EloquentInventoryRepository;
use App\Repositories\EloquentOrderRepository;
use App\Repositories\EloquentProductImageRepository;
use App\Repositories\EloquentStoreRepository;
use App\Repositories\EloquentUserRepository;
use App\Services\CatalogCacheService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CatalogCacheService::class);

        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, EloquentStoreRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CachedCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, CachedProductRepository::class);
        $this->app->bind(ProductImageRepositoryInterface::class, EloquentProductImageRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, EloquentInventoryRepository::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, EloquentCouponRepository::class);
        $this->app->bind(
            DisputeRepositoryInterface::class,
            EloquentDisputeRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(VendorApproved::class, SendVendorWelcomeNotification::class);
        Gate::define('customer', fn (User $user): bool => $user->hasRole(UserRole::CUSTOMER->value));
        Gate::define('vendor', fn (User $user): bool => $user->hasRole(UserRole::VENDOR->value));
        Gate::define('admin', fn (User $user): bool => $user->hasRole(UserRole::ADMIN->value));
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Cart::class, CartPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Inventory::observe(InventoryObserver::class);
        ProductImage::observe(ProductImageObserver::class);
        Review::observe(ReviewObserver::class);
        Store::observe(StoreObserver::class);

        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for the marketplace API (Day 26).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request): Limit {
            $email = (string) $request->input('email');
            $key = $email !== '' ? Str::transliterate(Str::lower($email).'|'.$request->ip()) : (string) $request->ip();

            return Limit::perMinute(10)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Too many authentication attempts. Please try again later.',
                    'errors' => [
                        'rate_limit' => ['Too many authentication attempts. Please try again later.'],
                    ],
                ], 429, $headers);
            });
        });

        RateLimiter::for('checkout', function (Request $request): Limit {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return Limit::perMinute(10)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Too many checkout attempts. Please try again later.',
                    'errors' => [
                        'rate_limit' => ['Too many checkout attempts. Please wait before retrying.'],
                    ],
                ], 429, $headers);
            });
        });

        RateLimiter::for('reports', function (Request $request): Limit {
            $key = (string) ($request->user()?->id ?? $request->ip());

            return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Too many report generation requests. Please wait a moment.',
                    'errors' => [
                        'rate_limit' => ['Too many report generation requests. Please wait a moment.'],
                    ],
                ], 429, $headers);
            });
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }
}
