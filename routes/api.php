<?php

use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminStoreController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\RoleAccessController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\VendorInventoryController;
use App\Http\Controllers\Api\VendorProductController;
use App\Http\Controllers\Api\VendorProductImageController;
use Illuminate\Support\Facades\Route;

// Public catalog — browsable by guests, no authentication required.
Route::get('products', [ProductCatalogController::class, 'index']);
Route::get('products/{product}', [ProductCatalogController::class, 'show'])->whereNumber('product');

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('admin/categories', AdminCategoryController::class)
        ->except(['show'])
        ->middleware('role:admin');
    Route::post('admin/stores/{store}/approve', [AdminStoreController::class, 'approve'])
        ->middleware('role:admin');
    Route::post('admin/stores/{store}/reject', [AdminStoreController::class, 'reject'])
        ->middleware('role:admin');
    Route::post('vendor/store', [StoreController::class, 'apply'])
        ->middleware('role:vendor');
    Route::apiResource('vendor/products', VendorProductController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('role:vendor');
    Route::prefix('vendor/products/{product}')->middleware('role:vendor')->group(function (): void {
        Route::get('images', [VendorProductImageController::class, 'index']);
        Route::post('images', [VendorProductImageController::class, 'store']);
        Route::delete('images/{image}', [VendorProductImageController::class, 'destroy']);
        Route::get('inventory', [VendorInventoryController::class, 'show']);
        Route::put('inventory', [VendorInventoryController::class, 'update']);
    });
    Route::get('vendor/access-check', [RoleAccessController::class, 'vendor'])
        ->middleware('role:vendor');
    Route::get('admin/access-check', [RoleAccessController::class, 'admin'])
        ->middleware('role:admin');
});
