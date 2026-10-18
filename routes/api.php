<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Multi-Vendor Marketplace (Day 26)
|--------------------------------------------------------------------------
|
| Version 1 of the API is mounted under `/api/v1/...` (primary entrypoint)
| and simultaneously exposed at `/api/...` for full backward compatibility
| with legacy clients and existing test suites.
|
*/

// Versioned API v1 (Primary entrypoint)
Route::prefix('v1')->name('v1.')->group(base_path('routes/api/v1.php'));

// Backward compatibility alias: legacy unversioned routes map to v1
Route::group([], base_path('routes/api/v1.php'));
