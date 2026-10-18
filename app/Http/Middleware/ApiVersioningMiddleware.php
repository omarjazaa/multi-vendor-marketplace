<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Versioning & Tracing Middleware (Day 26 — PRD §5 Week 4).
 *
 * Ensures all API responses include standardized versioning and correlation headers
 * (X-API-Version, X-Request-Id) and validates client version constraints.
 */
class ApiVersioningMiddleware
{
    /** Supported API versions on the marketplace platform. */
    public const SUPPORTED_VERSIONS = ['v1'];

    /** Current default API version. */
    public const DEFAULT_VERSION = 'v1';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestedVersion = $this->resolveRequestedVersion($request);

        if ($requestedVersion !== null && ! in_array(strtolower($requestedVersion), self::SUPPORTED_VERSIONS, true)) {
            return new JsonResponse([
                'message' => "Unsupported API version requested: '{$requestedVersion}'. Supported versions: ".implode(', ', self::SUPPORTED_VERSIONS).'.',
                'errors' => [
                    'api_version' => ["Unsupported API version '{$requestedVersion}'."],
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-API-Version', self::DEFAULT_VERSION);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /**
     * Resolve the requested version from headers or URL path.
     */
    private function resolveRequestedVersion(Request $request): ?string
    {
        if ($request->hasHeader('X-API-Version')) {
            return (string) $request->header('X-API-Version');
        }

        $accept = (string) $request->header('Accept');
        if (preg_match('/application\/vnd\.marketplace\.(v[0-9]+)\+json/i', $accept, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
