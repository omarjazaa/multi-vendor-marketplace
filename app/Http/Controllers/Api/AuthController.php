<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\LogoutRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
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
        $payload = $this->auth->register($request->validated());

        return $this->successResponse(
            [
                'user' => UserResource::make($payload['user']),
                'token' => $payload['token'],
            ],
            'Registered successfully.',
            201,
        );
    }

    /**
     * Authenticate a user and return a Sanctum token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->auth->login($request->string('email')->toString(), $request->string('password')->toString());

        return $this->successResponse(
            [
                'user' => UserResource::make($payload['user']),
                'token' => $payload['token'],
            ],
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
