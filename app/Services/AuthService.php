<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Hashing\HashManager;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly HashManager $hash,
    ) {}

    /**
     * Register a user and issue their first personal access token.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, token: string}
     */
    public function register(array $attributes): array
    {
        $user = $this->users->create($attributes);

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Authenticate a user with their email and password and issue a token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);

        if (! $user || ! $this->hash->check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    /**
     * Revoke the token used for the current authenticated request.
     */
    public function logout(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        $token?->delete();
    }
}
