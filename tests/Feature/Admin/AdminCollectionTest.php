<?php

namespace Tests\Feature\Admin;

use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_collections_with_product_counts(): void
    {
        $collection = Collection::create(['name' => 'Pilihan Editor', 'slug' => 'pilihan-editor']);
        $collection->products()->attach(Product::factory()->create()->id);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/collections')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.products_count', 1);
    }

    public function test_store_creates_a_collection_with_products_in_order(): void
    {
        $first = Product::factory()->create();
        $second = Product::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/admin/collections', [
                'name' => 'Terbaik 2026',
                'description' => 'Pilihan redaksi',
                'product_ids' => [$second->id, $first->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'terbaik-2026')
            ->assertJsonPath('data.products.0.id', $second->id)
            ->assertJsonPath('data.products.1.id', $first->id);

        $collection = Collection::query()->where('slug', 'terbaik-2026')->firstOrFail();
        $this->assertSame(0, (int) $collection->products()->first()->pivot->position);
    }

    public function test_update_replaces_the_product_list(): void
    {
        $collection = Collection::create(['name' => 'Awal', 'slug' => 'awal']);
        $old = Product::factory()->create();
        $new = Product::factory()->create();
        $collection->products()->attach($old->id);

        $this->actingAs($this->admin())
            ->putJson("/api/admin/collections/{$collection->id}", ['product_ids' => [$new->id]])
            ->assertOk();

        $ids = $collection->refresh()->products()->pluck('products.id')->all();
        $this->assertSame([$new->id], $ids);
    }

    public function test_store_validates_its_input(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/collections', ['name' => '', 'product_ids' => [9999]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'product_ids.0']);
    }

    public function test_destroy_deletes_the_collection(): void
    {
        $collection = Collection::create(['name' => 'Hapus', 'slug' => 'hapus']);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/collections/{$collection->id}")
            ->assertOk();

        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
    }

    public function test_collection_management_requires_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/collections')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
