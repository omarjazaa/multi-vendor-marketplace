<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a successful, consistently shaped API response.
     *
     * @param  array<string, mixed>  $data
     */
    protected function successResponse(array $data = [], string $message = 'Success.', int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    /**
     * Return a consistently shaped API error response.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function errorResponse(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
