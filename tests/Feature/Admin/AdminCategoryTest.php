<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_active_and_inactive_categories_with_count(): void
    {
        $category = Category::factory()->create(['name' => 'Catatan', 'slug' => 'catatan', 'position' => 1]);
        Category::factory()->create(['name' => 'Lama', 'slug' => 'lama', 'is_active' => false, 'position' => 2]);
        Product::factory()->for($category)->create();

        $this->actingAs($this->admin())
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_store_creates_a_category_and_generates_a_slug(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/categories', ['name' => 'Media Sosial', 'icon' => 'chat'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'media-sosial')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('categories', ['slug' => 'media-sosial', 'name' => 'Media Sosial']);
    }

    public function test_store_validates_its_input(): void
    {
        Category::factory()->create(['slug' => 'catatan']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/categories', ['name' => '', 'slug' => 'catatan'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'slug'])
            ->assertJsonPath('errors.name.0', 'Nama kategori wajib diisi.');
    }

    public function test_update_changes_the_category(): void
    {
        $category = Category::factory()->create(['name' => 'Lama', 'slug' => 'lama']);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/categories/{$category->id}", ['name' => 'Baru', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame('lama', $category->refresh()->slug);
    }

    public function test_destroy_refuses_a_category_that_still_has_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori masih dipakai produk. Pindahkan produknya dulu.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_destroy_refuses_a_category_with_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/categories/{$parent->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori masih punya sub-kategori. Pindahkan dulu.');
    }

    public function test_destroy_deletes_an_empty_category(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_management_requires_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/categories')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
