<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * System Health & Telemetry Feature Tests (Day 29 - Production Deployment).
 *
 * Verifies that the system health telemetry endpoint accurately audits
 * runtime subsystems: database connectivity, cache layer, and filesystem permissions.
 */
class SystemHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_endpoint_returns_operational_status_and_envelope(): void
    {
        $response = $this->getJson('/api/v1/system/health');

        $response->assertOk()
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-Request-Id')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'environment',
                    'timestamp',
                    'latency_ms',
                    'runtime' => [
                        'php_version',
                        'laravel_version',
                        'server_timezone',
                    ],
                    'services' => [
                        'database' => [
                            'driver',
                            'connected',
                            'latency_ms',
                        ],
                        'cache' => [
                            'driver',
                            'operational',
                        ],
                        'storage' => [
                            'writable',
                        ],
                    ],
                ],
                'message',
            ]);

        $response->assertJson([
            'data' => [
                'status' => 'healthy',
                'services' => [
                    'database' => [
                        'connected' => true,
                    ],
                    'cache' => [
                        'operational' => true,
                    ],
                    'storage' => [
                        'writable' => true,
                    ],
                ],
            ],
            'message' => 'System services are operating normally.',
        ]);
    }

    public function test_system_health_endpoint_is_accessible_via_unversioned_fallback(): void
    {
        $response = $this->getJson('/api/system/health');

        $response->assertOk()
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.services.database.connected', true);
    }
}
