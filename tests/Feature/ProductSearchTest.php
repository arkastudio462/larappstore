<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private ProductSearchService $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->search = app(ProductSearchService::class);
    }

    public function test_creating_a_product_indexes_it(): void
    {
        $product = Product::factory()->create(['title' => 'Laracast Companion']);

        $row = DB::table('product_search')->where('product_id', $product->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('Laracast Companion', $row->title);
    }

    public function test_updating_a_product_rewrites_its_index_row(): void
    {
        $product = Product::factory()->create(['title' => 'Nama Lama']);

        $product->update(['title' => 'Nama Baru']);

        $rows = DB::table('product_search')->where('product_id', $product->id)->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Nama Baru', $rows->first()->title);
    }

    public function test_soft_deleting_a_product_removes_it_from_the_index(): void
    {
        $product = Product::factory()->create(['title' => 'Akan Dihapus']);

        $product->delete();

        $this->assertSame(
            0,
            DB::table('product_search')->where('product_id', $product->id)->count()
        );
    }

    public function test_search_returns_published_products_by_relevance(): void
    {
        Product::factory()->create(['title' => 'Pengedit Foto Cahaya']);
        Product::factory()->create(['title' => 'Kalkulator Sederhana']);

        $results = $this->search->search('foto');

        $this->assertCount(1, $results);
        $this->assertSame('Pengedit Foto Cahaya', $results->first()->title);
    }

    public function test_search_excludes_draft_products(): void
    {
        Product::factory()->draft()->create(['title' => 'Konsep Aplikasi Rahasia']);

        $this->assertCount(0, $this->search->search('rahasia'));
    }

    public function test_search_handles_fts5_special_characters_without_failing(): void
    {
        Product::factory()->create(['title' => 'Aplikasi Canggih']);

        $match = $this->search->toMatchQuery('canggih AND OR NOT - ^ :');

        $this->assertStringContainsString('"AND"*', $match);
        $this->assertStringContainsString('"NOT"*', $match);
        $this->assertStringContainsString('"^"*', $match);

        $this->search->search('canggih AND OR NOT - ^ :');

        $this->assertTrue(true);
    }

    public function test_empty_query_returns_no_results(): void
    {
        Product::factory()->create(['title' => 'Aplikasi']);

        $this->assertCount(0, $this->search->search('   '));
    }

    public function test_search_matches_summary_and_description(): void
    {
        Product::factory()->create([
            'title' => 'Utilitas Sistem',
            'summary' => 'Membersihkan file sampah',
            'description' => 'Menghapus cache yang tidak terpakai',
        ]);

        $this->assertCount(1, $this->search->search('cache'));
    }

    public function test_match_query_escapes_embedded_quotes(): void
    {
        $this->assertSame(
            '"kata"* AND """aneh"""* AND "lain"*',
            $this->search->toMatchQuery('kata "aneh" lain')
        );
    }

    public function test_search_result_is_limited(): void
    {
        User::factory()->create();
        Product::factory()->count(5)->create(['title' => 'Aplikasi Serbaguna']);

        $this->assertCount(2, $this->search->search('serbaguna', 2));
    }
}
