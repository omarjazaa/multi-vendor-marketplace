<?php

namespace App\Repositories\Contracts;

use App\Enums\UserRole;
use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Create a user from validated registration attributes.
     *
     * @param  array{name: string, email: string, password: string, role: UserRole}  $attributes
     */
    public function create(array $attributes): User;

    /**
     * Find a user by their email address.
     */
    public function findByEmail(string $email): ?User;
}
