<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    /**
     * Register a customer or vendor and return a Sanctum token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->auth->register($request->validated()),
            'Registered successfully.',
            201,
        );
    }

    /**
     * Authenticate a user and return a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->auth->login($request->string('email')->toString(), $request->string('password')->toString()),
            'Logged in successfully.',
        );
    }

    /**
     * Revoke the current Sanctum token.
     */
    public function logout(LogoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->auth->logout($user);

        return $this->successResponse(message: 'Logged out successfully.');
    }
}
