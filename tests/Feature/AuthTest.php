<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receive_an_access_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Customer',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::CUSTOMER->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'ada@example.com')
            ->assertJsonPath('data.user.role', UserRole::CUSTOMER->value)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_vendor_can_register_with_the_vendor_role(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Vendor One',
            'email' => 'vendor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::VENDOR->value,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.role', UserRole::VENDOR->value);
    }

    public function test_user_can_login_and_logout(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Grace Customer',
            'email' => 'grace@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::CUSTOMER->value,
        ])->assertCreated();

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'grace@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
