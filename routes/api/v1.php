<?php

use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminCouponController;
use App\Http\Controllers\Api\AdminDisputeController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminStoreController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\ProductReviewController;
use App\Http\Controllers\Api\RoleAccessController;
use App\Http\Controllers\Api\ShipmentTrackingController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\VendorAnalyticsController;
use App\Http\Controllers\Api\VendorCouponController;
use App\Http\Controllers\Api\VendorInventoryController;
use App\Http\Controllers\Api\VendorOrderController;
use App\Http\Controllers\Api\VendorProductController;
use App\Http\Controllers\Api\VendorProductImageController;
use App\Http\Controllers\Api\VendorReportController;
use App\Http\Controllers\Api\VendorShipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (Day 26 — PRD §5 Week 4)
|--------------------------------------------------------------------------
|
| Version 1 routes with full resource scoping, role authorization,
| and targeted rate limiting on sensitive actions.
|
*/

// System health & telemetry endpoint (Docker production deployment & orchestration monitoring)
Route::get('system/health', [SystemHealthController::class, 'index'])->name('system.health');

// Public catalog & categories — browsable by guests, no authentication required.
Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('products', [ProductCatalogController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductCatalogController::class, 'show'])->whereNumber('product')->name('products.show');
Route::get('products/{product}/reviews', [ProductReviewController::class, 'index'])->whereNumber('product')->name('products.reviews.index');

// Public parcel tracking endpoint (simulated carrier tracking portal)
Route::get('shipments/track/{trackingNumber}', [ShipmentTrackingController::class, 'track'])->name('shipments.track');

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('admin/categories', AdminCategoryController::class)
        ->except(['show'])
        ->middleware('role:admin');
    Route::post('admin/stores/{store}/approve', [AdminStoreController::class, 'approve'])
        ->middleware('role:admin')
        ->name('admin.stores.approve');
    Route::post('admin/stores/{store}/reject', [AdminStoreController::class, 'reject'])
        ->middleware('role:admin')
        ->name('admin.stores.reject');
    Route::post('admin/coupons', [AdminCouponController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.coupons.store');
    Route::post('vendor/store', [StoreController::class, 'apply'])
        ->middleware('role:vendor')
        ->name('vendor.store.apply');
    Route::post('vendor/coupons', [VendorCouponController::class, 'store'])
        ->middleware('role:vendor')
        ->name('vendor.coupons.store');
    Route::prefix('vendor/orders')->name('vendor.orders.')->middleware('role:vendor')->group(function (): void {
        Route::get('', [VendorOrderController::class, 'index'])->name('index');
        Route::patch('{order}/status', [VendorOrderController::class, 'updateStatus'])->name('update-status');
        Route::post('{order}/items/{item}/ship', [VendorShipmentController::class, 'ship'])->name('ship');
    });
    Route::patch('vendor/shipments/{shipment}/status', [VendorShipmentController::class, 'updateStatus'])
        ->whereNumber('shipment')
        ->middleware('role:vendor')
        ->name('vendor.shipments.update-status');
    Route::prefix('vendor/analytics')->name('vendor.analytics.')->middleware('role:vendor')->group(function (): void {
        Route::get('dashboard', [VendorAnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('summary', [VendorAnalyticsController::class, 'summary'])->name('summary');
        Route::get('best-sellers', [VendorAnalyticsController::class, 'bestSellers'])->name('best-sellers');
        Route::get('revenue', [VendorAnalyticsController::class, 'revenue'])->name('revenue');
        Route::get('reports', [VendorReportController::class, 'index'])->name('reports.index');
        Route::post('reports', [VendorReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
        Route::get('reports/{report}', [VendorReportController::class, 'show'])->whereNumber('report')->name('reports.show');
        Route::get('reports/{report}/download', [VendorReportController::class, 'download'])->whereNumber('report')->name('reports.download');
    });
    Route::prefix('admin/orders')->name('admin.orders.')->middleware('role:admin')->group(function (): void {
        Route::get('', [AdminOrderController::class, 'index'])->name('index');
        Route::patch('{order}/status', [AdminOrderController::class, 'updateStatus'])->name('update-status');
        Route::post('{order}/refund', [AdminOrderController::class, 'refund'])->whereNumber('order')->name('refund');
    });
    Route::prefix('admin/disputes')->name('admin.disputes.')->middleware('role:admin')->group(function (): void {
        Route::get('', [AdminDisputeController::class, 'index'])->name('index');
        Route::get('{dispute}', [AdminDisputeController::class, 'show'])->whereNumber('dispute')->name('show');
        Route::post('{dispute}/refund', [AdminDisputeController::class, 'refund'])->whereNumber('dispute')->name('refund');
        Route::post('{dispute}/reject', [AdminDisputeController::class, 'reject'])->whereNumber('dispute')->name('reject');
    });
    Route::middleware('role:customer')->group(function (): void {
        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');
        Route::post('cart/items', [CartController::class, 'store'])->name('cart.items.store');
        Route::put('cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
        // Checkout is customer only; rate-limited to avoid duplicate submission spam
        Route::post('checkout', [OrderController::class, 'checkout'])->middleware('throttle:checkout')->name('checkout');
        Route::post('products/{product}/reviews', [ProductReviewController::class, 'store'])->whereNumber('product')->name('products.reviews.store');
        Route::post('orders/{order}/disputes', [DisputeController::class, 'store'])->whereNumber('order')->name('orders.disputes.store');
        Route::get('orders/{order}/disputes', [DisputeController::class, 'orderDisputes'])->whereNumber('order')->name('orders.disputes.index');
        Route::get('disputes', [DisputeController::class, 'index'])->name('disputes.index');
    });
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/shipments', [ShipmentTrackingController::class, 'forOrder'])->whereNumber('order')->name('orders.shipments');
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::patch('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });
    Route::apiResource('vendor/products', VendorProductController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('role:vendor');
    Route::prefix('vendor/products/{product}')->name('vendor.products.')->middleware('role:vendor')->group(function (): void {
        Route::get('images', [VendorProductImageController::class, 'index'])->name('images.index');
        Route::post('images', [VendorProductImageController::class, 'store'])->name('images.store');
        Route::delete('images/{image}', [VendorProductImageController::class, 'destroy'])->name('images.destroy');
        Route::get('inventory', [VendorInventoryController::class, 'show'])->name('inventory.show');
        Route::put('inventory', [VendorInventoryController::class, 'update'])->name('inventory.update');
    });
    Route::get('vendor/access-check', [RoleAccessController::class, 'vendor'])
        ->middleware('role:vendor')
        ->name('vendor.access-check');
    Route::get('admin/access-check', [RoleAccessController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.access-check');
});
