<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleAccessController;
use App\Http\Controllers\Api\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('vendor/store', [StoreController::class, 'apply'])
        ->middleware('role:vendor');
    Route::get('vendor/access-check', [RoleAccessController::class, 'vendor'])
        ->middleware('role:vendor');
    Route::get('admin/access-check', [RoleAccessController::class, 'admin'])
        ->middleware('role:admin');
});
