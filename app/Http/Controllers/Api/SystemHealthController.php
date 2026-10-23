<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * System Health & Telemetry Controller (Day 29 - Production Deployment).
 *
 * Provides real-time health telemetry across database connectivity,
 * cache subsystems, storage permissions, and container runtime metrics.
 */
class SystemHealthController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $start = microtime(true);
        $dbConnected = false;
        $dbLatencyMs = null;

        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbLatencyMs = round((microtime(true) - $dbStart) * 1000, 2);
            $dbConnected = true;
        } catch (Exception) {
            $dbConnected = false;
        }

        $cacheOperational = false;
        try {
            Cache::put('_health_probe', true, 10);
            $cacheOperational = Cache::get('_health_probe') === true;
            Cache::forget('_health_probe');
        } catch (Exception) {
            $cacheOperational = false;
        }

        $storageWritable = is_writable(storage_path('framework/cache'))
            && is_writable(storage_path('logs'));

        $isHealthy = $dbConnected && $storageWritable;
        $totalLatencyMs = round((microtime(true) - $start) * 1000, 2);

        $payload = [
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'latency_ms' => $totalLatencyMs,
            'runtime' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_timezone' => config('app.timezone'),
            ],
            'services' => [
                'database' => [
                    'driver' => config('database.default'),
                    'connected' => $dbConnected,
                    'latency_ms' => $dbLatencyMs,
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'operational' => $cacheOperational,
                ],
                'storage' => [
                    'writable' => $storageWritable,
                ],
            ],
        ];

        return $this->successResponse(
            $payload,
            $isHealthy ? 'System services are operating normally.' : 'One or more system services are degraded.',
            $isHealthy ? 200 : 503
        );
    }
}
