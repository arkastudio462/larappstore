<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_product_by_title_prefix(): void
    {
        Product::factory()->create(['title' => 'Catatan Kilat']);
        Product::factory()->create(['title' => 'Puzzle Batu']);

        $this->getJson('/api/search?q=catatan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Catatan Kilat')
            ->assertJsonPath('meta.query', 'catatan');
    }

    public function test_search_matches_words_from_the_description(): void
    {
        Product::factory()->create([
            'title' => 'Dompet Pintar',
            'description' => 'Pelacakan pengeluaran harian dengan grafik sederhana.',
        ]);
        Product::factory()->create(['title' => 'Aplikasi Lain', 'description' => 'Tidak berkaitan.']);

        $this->getJson('/api/search?q=pengeluaran')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Dompet Pintar');
    }

    public function test_search_excludes_drafts(): void
    {
        Product::factory()->draft()->create(['title' => 'Catatan Rahasia']);

        $this->getJson('/api/search?q=catatan')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_with_empty_query_returns_nothing(): void
    {
        Product::factory()->create(['title' => 'Catatan Kilat']);

        $this->getJson('/api/search?q=')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_search_does_not_break_on_fts_syntax_characters(): void
    {
        Product::factory()->create(['title' => 'Catatan Kilat']);

        $this->getJson('/api/search?q='.urlencode('catatan OR " *'))
            ->assertOk();
    }
}
