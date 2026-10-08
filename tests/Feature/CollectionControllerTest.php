<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_active_collection_with_published_products(): void
    {
        $collection = Collection::create(['name' => 'Pilihan Editor', 'slug' => 'pilihan-editor']);
        $published = Product::factory()->create(['title' => 'Terbit']);
        $draft = Product::factory()->draft()->create(['title' => 'Draft']);

        $collection->products()->attach([$published->id, $draft->id]);

        $this->getJson('/api/collections/pilihan-editor')
            ->assertOk()
            ->assertJsonPath('data.slug', 'pilihan-editor')
            ->assertJsonPath('data.name', 'Pilihan Editor')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.title', 'Terbit')
            ->assertJsonPath('data.products_count', 1);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/collections/tidak-ada')->assertNotFound();
    }

    public function test_show_returns_404_for_inactive_collection(): void
    {
        Collection::create(['name' => 'Arsip', 'slug' => 'arsip', 'is_active' => false]);

        $this->getJson('/api/collections/arsip')->assertNotFound();
    }
}
