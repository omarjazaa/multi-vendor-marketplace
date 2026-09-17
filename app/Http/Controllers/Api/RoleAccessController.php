<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleAccessController extends Controller
{
    use ApiResponse;

    /**
     * Provide a minimal protected vendor route for authorization integration checks.
     */
    public function vendor(): JsonResponse
    {
        return $this->successResponse(['area' => 'vendor']);
    }

    /**
     * Provide a minimal protected admin route for authorization integration checks.
     */
    public function admin(): JsonResponse
    {
        return $this->successResponse(['area' => 'admin']);
    }
}
