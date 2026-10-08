<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_active_top_level_categories(): void
    {
        Category::factory()->create(['name' => 'Aktif', 'slug' => 'aktif']);
        Category::factory()->create(['name' => 'Nonaktif', 'slug' => 'nonaktif', 'is_active' => false]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'aktif');
    }

    public function test_index_counts_only_published_products(): void
    {
        $category = Category::factory()->create(['slug' => 'catatan']);
        Product::factory()->for($category)->create();
        Product::factory()->for($category)->draft()->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.products_count', 1);
    }

    public function test_index_nests_active_children_with_counts(): void
    {
        $parent = Category::factory()->create(['slug' => 'game', 'parent_id' => null]);
        $child = Category::factory()->create(['slug' => 'puzzle', 'parent_id' => $parent->id]);
        Category::factory()->create(['slug' => 'tidak-aktif', 'parent_id' => $parent->id, 'is_active' => false]);
        Product::factory()->for($child)->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'game')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.slug', 'puzzle')
            ->assertJsonPath('data.0.children.0.products_count', 1);
    }
}
