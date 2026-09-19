<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_hierarchical_categories(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $parentResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Home',
            'slug' => 'home',
        ]);

        $parentResponse->assertCreated();
        $parent = Category::firstOrFail();

        $childResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/categories', [
            'name' => 'Decor',
            'slug' => 'decor',
            'parent_id' => $parent->id,
        ]);

        $childResponse
            ->assertCreated()
            ->assertJsonPath('data.category.parent_id', $parent->id);

        $child = Category::where('slug', 'decor')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/categories/{$child->id}", ['name' => 'Decor Items'])
            ->assertOk()
            ->assertJsonPath('data.category.name', 'Decor Items');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$child->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $child->id]);
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_only_admins_can_manage_categories(): void
    {
        $vendor = User::factory()->create(['role' => UserRole::VENDOR]);
        $customer = User::factory()->create(['role' => UserRole::CUSTOMER]);

        foreach ([$vendor, $customer] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/admin/categories', [
                    'name' => 'Blocked',
                    'slug' => 'blocked-'.$user->id,
                ])
                ->assertForbidden();
        }
    }

    public function test_category_validation_rejects_duplicate_slugs_and_invalid_parents(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $category = Category::factory()->create(['slug' => 'existing']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Duplicate',
                'slug' => 'existing',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Invalid Parent',
                'slug' => 'invalid-parent',
                'parent_id' => 999999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_product_belongs_to_a_store_and_category(): void
    {
        $store = Store::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
        ]);

        $this->assertTrue($product->store->is($store));
        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->contains($product));
    }
}
