<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CreditLedger;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_lists_the_developers_own_products(): void
    {
        $developer = $this->developer();
        Product::factory()->for($developer, 'developer')->create(['title' => 'Punyaku']);
        Product::factory()->create(['title' => 'Punya Orang']);

        $this->actingAs($developer)
            ->getJson('/api/developer/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Punyaku')
            ->assertJsonPath('data.0.status', Product::STATUS_PUBLISHED);
    }

    public function test_index_includes_drafts(): void
    {
        $developer = $this->developer();
        Product::factory()->for($developer, 'developer')->draft()->create();

        $this->actingAs($developer)
            ->getJson('/api/developer/products')
            ->assertOk()
            ->assertJsonPath('data.0.status', Product::STATUS_DRAFT);
    }

    public function test_store_consumes_a_credit_and_writes_the_ledger(): void
    {
        $developer = $this->developer(credits: 2);
        $category = Category::factory()->create();

        $this->actingAs($developer)
            ->postJson('/api/developer/products', $this->payload($category))
            ->assertCreated()
            ->assertJsonPath('data.status', Product::STATUS_DRAFT)
            ->assertJsonPath('data.slug', 'catatan-kilat');

        $this->assertSame(1, $developer->developerProfile->refresh()->upload_credits);
        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $developer->id,
            'delta' => -1,
            'type' => CreditLedger::TYPE_USAGE,
            'balance_after' => 1,
        ]);
    }

    public function test_store_returns_402_when_credits_run_out(): void
    {
        $developer = $this->developer(credits: 0);
        $category = Category::factory()->create();

        $this->actingAs($developer)
            ->postJson('/api/developer/products', $this->payload($category))
            ->assertStatus(402)
            ->assertJsonPath('data.credits', 0);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_store_does_not_consume_credits_for_unlimited_developers(): void
    {
        $developer = $this->developer(credits: 0, unlimited: true);
        $category = Category::factory()->create();

        $this->actingAs($developer)
            ->postJson('/api/developer/products', $this->payload($category))
            ->assertCreated();

        $this->assertSame(0, $developer->developerProfile->refresh()->upload_credits);
        $this->assertDatabaseCount('credit_ledger', 0);
    }

    public function test_store_validates_its_input(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/developer/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'type', 'title', 'price'])
            ->assertJsonPath('errors.title.0', 'Judul produk wajib diisi.');
    }

    public function test_store_requires_the_developer_role(): void
    {
        $category = Category::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/developer/products', $this->payload($category))
            ->assertForbidden();
    }

    public function test_update_changes_the_owners_product(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create(['title' => 'Lama']);

        $this->actingAs($developer)
            ->putJson("/api/developer/products/{$product->slug}", ['title' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Baru');

        $this->assertSame('Baru', $product->refresh()->title);
    }

    public function test_update_forbids_other_developers(): void
    {
        $product = Product::factory()->create(['title' => 'Lama']);

        $this->actingAs($this->developer())
            ->putJson("/api/developer/products/{$product->slug}", ['title' => 'Bajakan'])
            ->assertForbidden();
    }

    public function test_publish_creates_a_feed_item_once(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->draft()->create();

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Product::STATUS_PUBLISHED);

        $this->assertNotNull($product->refresh()->published_at);
        $this->assertDatabaseHas('feed_items', [
            'subject_type' => Product::class,
            'subject_id' => $product->id,
        ]);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/publish")
            ->assertOk()
            ->assertJsonPath('message', 'Produk sudah terbit.');

        $this->assertDatabaseCount('feed_items', 1);
    }

    public function test_archive_removes_the_product_and_its_release_feed_items(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->draft()->create();
        $product->publish();

        $version = $product->versions()->create([
            'version' => '1.0.0',
            'file_path' => 'products/x/release.apk',
            'file_size' => 10,
            'file_type' => 'apk',
            'status' => 'draft',
        ]);
        $version->publish();

        $this->assertDatabaseCount('feed_items', 2);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', Product::STATUS_ARCHIVED);

        $this->assertDatabaseCount('feed_items', 0);
    }

    public function test_stats_returns_download_totals(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create(['downloads_count' => 42]);
        $version = $product->versions()->create([
            'version' => '1.0.0',
            'file_path' => 'products/x/release.apk',
            'file_size' => 10,
            'file_type' => 'apk',
            'status' => 'published',
            'is_latest' => true,
            'published_at' => now(),
        ]);
        $product->dailyStats()->create([
            'version_id' => $version->id,
            'date' => now()->toDateString(),
            'count' => 7,
        ]);

        $this->actingAs($developer)
            ->getJson("/api/developer/products/{$product->slug}/stats")
            ->assertOk()
            ->assertJsonPath('data.downloads_count', 42)
            ->assertJsonPath('data.versions_count', 1)
            ->assertJsonPath('data.daily.0.count', 7)
            ->assertJsonPath('data.daily.0.version', '1.0.0');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Category $category): array
    {
        return [
            'category_id' => $category->id,
            'type' => 'aplikasi',
            'title' => 'Catatan Kilat',
            'summary' => 'Catatan cepat',
            'price' => 0,
        ];
    }

    private function developer(int $credits = 1, bool $unlimited = false): User
    {
        $user = User::factory()->developer()->create();

        $user->developerProfile()->create([
            'studio_name' => 'Studio '.$user->id,
            'slug' => 'studio-'.$user->id,
            'upload_credits' => $credits,
            'unlimited_uploads' => $unlimited,
        ]);

        return $user;
    }
}
