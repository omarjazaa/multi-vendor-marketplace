<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolesAndPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_gates_match_the_authenticated_users_role(): void
    {
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->assertTrue(Gate::forUser($customer)->allows('customer'));
        $this->assertFalse(Gate::forUser($customer)->allows('vendor'));
        $this->assertFalse(Gate::forUser($customer)->allows('admin'));

        $this->assertTrue(Gate::forUser($vendor)->allows('vendor'));
        $this->assertFalse(Gate::forUser($vendor)->allows('admin'));

        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
        $this->assertFalse(Gate::forUser($admin)->allows('vendor'));
    }

    public function test_vendor_routes_allow_vendors_and_reject_other_roles(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/vendor/access-check')
            ->assertOk()
            ->assertJsonPath('data.area', 'vendor');

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/vendor/access-check')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/vendor/access-check')
            ->assertForbidden();
    }

    public function test_admin_routes_allow_only_admins(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/access-check')
            ->assertOk()
            ->assertJsonPath('data.area', 'admin');

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/admin/access-check')
            ->assertForbidden();
    }

    public function test_role_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/vendor/access-check')->assertUnauthorized();
        $this->getJson('/api/admin/access-check')->assertUnauthorized();
    }
}
