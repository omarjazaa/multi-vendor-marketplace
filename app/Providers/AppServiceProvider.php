<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\StoreRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\EloquentStoreRepository;
use App\Repositories\EloquentUserRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('customer', fn (User $user): bool => $user->hasRole(UserRole::CUSTOMER->value));
        Gate::define('vendor', fn (User $user): bool => $user->hasRole(UserRole::VENDOR->value));
        Gate::define('admin', fn (User $user): bool => $user->hasRole(UserRole::ADMIN->value));
    }
}
