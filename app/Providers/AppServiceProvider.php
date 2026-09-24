<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Events\VendorApproved;
use App\Listeners\SendVendorWelcomeNotification;
use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\StoreRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentCategoryRepository;
use App\Repositories\EloquentInventoryRepository;
use App\Repositories\EloquentProductImageRepository;
use App\Repositories\EloquentProductRepository;
use App\Repositories\EloquentStoreRepository;
use App\Repositories\EloquentUserRepository;
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
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, EloquentStoreRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ProductImageRepositoryInterface::class, EloquentProductImageRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, EloquentInventoryRepository::class);
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
    }
}
