<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_lists_published_products(): void
    {
        Product::factory()->create(['title' => 'Terbit']);
        Product::factory()->draft()->create(['title' => 'Draft']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Terbit')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_index_filters_by_category_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'catatan']);
        Product::factory()->for($category)->create(['title' => 'Catatan Kilat']);
        Product::factory()->create(['title' => 'Game Lain']);

        $this->getJson('/api/products?category=catatan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Catatan Kilat');
    }

    public function test_index_filters_by_type(): void
    {
        Product::factory()->create(['type' => 'game', 'title' => 'Sebuah Game']);
        Product::factory()->create(['type' => 'aplikasi', 'title' => 'Sebuah Aplikasi']);

        $this->getJson('/api/products?type=game')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sebuah Game');
    }

    public function test_index_filters_by_price(): void
    {
        Product::factory()->create(['title' => 'Gratis']);
        Product::factory()->paid(15000)->create(['title' => 'Berbayar']);

        $this->getJson('/api/products?price=free')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gratis');

        $this->getJson('/api/products?price=paid')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Berbayar');
    }

    public function test_index_filters_by_featured(): void
    {
        Product::factory()->featured()->create(['title' => 'Pilihan']);
        Product::factory()->create(['title' => 'Biasa']);

        $this->getJson('/api/products?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Pilihan');
    }

    public function test_index_sorts_by_popular_and_price(): void
    {
        Product::factory()->create(['title' => 'Sepi', 'downloads_count' => 5, 'price' => 30000]);
        Product::factory()->create(['title' => 'Populer', 'downloads_count' => 900, 'price' => 10000]);

        $this->getJson('/api/products?sort=popular')
            ->assertJsonPath('data.0.title', 'Populer');

        $this->getJson('/api/products?sort=price_low')
            ->assertJsonPath('data.0.title', 'Populer');
    }

    public function test_index_includes_rating_summary(): void
    {
        $product = Product::factory()->create();
        $this->review($product, User::factory()->create(), 4);
        $this->review($product, User::factory()->create(), 5);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.rating_avg', 4.5)
            ->assertJsonPath('data.0.rating_count', 2);
    }

    public function test_index_paginates_with_meta(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_show_returns_published_product_with_screenshots_and_version(): void
    {
        $product = Product::factory()->create(['slug' => 'catatan-kilat', 'title' => 'Catatan Kilat']);
        $product->screenshots()->create(['path' => 'products/1/shot-1.jpg', 'position' => 1]);
        $this->version($product, ['version' => '1.2.0']);

        $this->getJson('/api/products/catatan-kilat')
            ->assertOk()
            ->assertJsonPath('data.slug', 'catatan-kilat')
            ->assertJsonPath('data.screenshots.0.path', 'products/1/shot-1.jpg')
            ->assertJsonPath('data.latest_version.version', '1.2.0')
            ->assertJsonPath('data.rating_count', 0);
    }

    public function test_show_hides_draft_from_other_users(): void
    {
        Product::factory()->draft()->create(['slug' => 'rahasia']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/products/rahasia')
            ->assertForbidden();
    }

    public function test_show_returns_draft_to_its_developer(): void
    {
        $developer = User::factory()->developer()->create();
        Product::factory()->for($developer, 'developer')->draft()->create(['slug' => 'milikku']);

        $this->actingAs($developer)
            ->getJson('/api/products/milikku')
            ->assertOk()
            ->assertJsonPath('data.status', Product::STATUS_DRAFT);
    }

    public function test_show_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/products/tidak-ada')->assertNotFound();
    }

    public function test_show_exposes_the_viewers_own_review_id(): void
    {
        $product = Product::factory()->create(['slug' => 'catatan']);
        $reviewer = User::factory()->create();
        $review = $this->review($product, $reviewer, 5);

        $this->actingAs($reviewer)
            ->getJson('/api/products/catatan')
            ->assertOk()
            ->assertJsonPath('data.my_review_id', $review->id);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/products/catatan')
            ->assertOk()
            ->assertJsonPath('data.my_review_id', null);
    }

    public function test_versions_hides_drafts_from_visitors(): void
    {
        $product = Product::factory()->create(['slug' => 'catatan']);
        $this->version($product, ['version' => '1.0.0']);
        $this->version($product, ['version' => '2.0.0', 'status' => ProductVersion::STATUS_DRAFT, 'is_latest' => false, 'published_at' => null]);

        $this->getJson('/api/products/catatan/versions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.version', '1.0.0');
    }

    public function test_versions_shows_drafts_to_the_owner(): void
    {
        $developer = User::factory()->developer()->create();
        $product = Product::factory()->for($developer, 'developer')->create(['slug' => 'catatan']);
        $this->version($product, ['version' => '1.0.0']);
        $this->version($product, ['version' => '2.0.0', 'status' => ProductVersion::STATUS_DRAFT, 'is_latest' => false, 'published_at' => null]);

        $this->actingAs($developer)
            ->getJson('/api/products/catatan/versions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_reviews_lists_reviews_of_the_product(): void
    {
        $product = Product::factory()->create(['slug' => 'catatan']);
        $author = User::factory()->create(['username' => 'rina']);
        $this->review($product, $author, 5, 'Bagus sekali');

        $this->getJson('/api/products/catatan/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.body', 'Bagus sekali')
            ->assertJsonPath('data.0.author.username', 'rina');
    }

    private function version(Product $product, array $attributes = []): ProductVersion
    {
        return $product->versions()->create(array_merge([
            'version' => '1.0.0',
            'file_path' => "products/{$product->id}/release.apk",
            'file_size' => 2048,
            'file_type' => 'apk',
            'checksum_sha256' => str_repeat('a', 64),
            'status' => ProductVersion::STATUS_PUBLISHED,
            'is_latest' => true,
            'published_at' => now(),
        ], $attributes));
    }

    private function review(Product $product, User $user, int $rating, ?string $body = null): Review
    {
        return $product->reviews()->create([
            'user_id' => $user->id,
            'rating' => $rating,
            'body' => $body,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }
}
