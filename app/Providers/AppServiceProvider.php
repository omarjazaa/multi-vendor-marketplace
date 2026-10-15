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
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\StoreRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentCartRepository;
use App\Repositories\EloquentCouponRepository;
use App\Repositories\EloquentInventoryRepository;
use App\Repositories\EloquentOrderRepository;
use App\Repositories\EloquentProductImageRepository;
use App\Repositories\EloquentStoreRepository;
use App\Repositories\EloquentUserRepository;
use App\Services\CatalogCacheService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
