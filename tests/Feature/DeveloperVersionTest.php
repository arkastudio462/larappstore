<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_draft_version(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions", $this->payload('1.0.0'))
            ->assertCreated()
            ->assertJsonPath('data.version', '1.0.0')
            ->assertJsonPath('data.status', ProductVersion::STATUS_DRAFT)
            ->assertJsonPath('data.is_latest', false);

        $this->assertDatabaseHas('product_versions', [
            'product_id' => $product->id,
            'version' => '1.0.0',
            'status' => ProductVersion::STATUS_DRAFT,
        ]);
    }

    public function test_store_rejects_a_duplicate_version_number(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();
        $product->versions()->create($this->payload('1.0.0') + ['status' => 'draft']);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions", $this->payload('1.0.0'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Versi 1.0.0 sudah ada untuk produk ini.');

        $this->assertDatabaseCount('product_versions', 1);
    }

    public function test_store_validates_the_file_metadata(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions", ['version' => '1.0.0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file_path', 'file_size', 'file_type']);
    }

    public function test_store_forbids_other_developers(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->developer())
            ->postJson("/api/developer/products/{$product->slug}/versions", $this->payload('1.0.0'))
            ->assertForbidden();
    }

    public function test_publish_marks_the_version_latest_and_writes_a_release_feed_item(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();
        $old = $product->versions()->create($this->payload('1.0.0') + [
            'status' => 'published',
            'is_latest' => true,
            'published_at' => now()->subDay(),
        ]);
        $new = $product->versions()->create($this->payload('2.0.0') + ['status' => 'draft']);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions/{$new->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', ProductVersion::STATUS_PUBLISHED)
            ->assertJsonPath('data.is_latest', true);

        $this->assertFalse($old->refresh()->is_latest);
        $this->assertDatabaseHas('feed_items', [
            'subject_type' => FeedItem::SUBJECT_RELEASE,
            'subject_id' => $new->id,
        ]);
    }

    public function test_publish_is_idempotent(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();
        $version = $product->versions()->create($this->payload('1.0.0') + ['status' => 'draft']);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions/{$version->id}/publish")
            ->assertOk();

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions/{$version->id}/publish")
            ->assertOk()
            ->assertJsonPath('message', 'Versi sudah terbit.');

        $this->assertDatabaseCount('feed_items', 1);
    }

    public function test_publish_rejects_a_version_from_another_product(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create();
        $other = Product::factory()->for($developer, 'developer')->create();
        $version = $other->versions()->create($this->payload('1.0.0') + ['status' => 'draft']);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions/{$version->id}/publish")
            ->assertNotFound();
    }

    public function test_published_release_appears_in_the_feed(): void
    {
        $developer = $this->developer();
        $product = Product::factory()->for($developer, 'developer')->create(['slug' => 'catatan-kilat', 'title' => 'Catatan Kilat']);
        $version = $product->versions()->create($this->payload('1.0.0') + ['status' => 'draft']);

        $this->actingAs($developer)
            ->postJson("/api/developer/products/{$product->slug}/versions/{$version->id}/publish")
            ->assertOk();

        $this->getJson('/api/feed?type=product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_type', 'release')
            ->assertJsonPath('data.0.version.version', '1.0.0')
            ->assertJsonPath('data.0.version.product.title', 'Catatan Kilat');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $version): array
    {
        return [
            'version' => $version,
            'file_path' => "products/1/release-{$version}.apk",
            'file_size' => 1024,
            'file_type' => 'apk',
            'changelog' => 'Rilis '.$version,
        ];
    }

    private function developer(): User
    {
        $user = User::factory()->developer()->create();

        $user->developerProfile()->create([
            'studio_name' => 'Studio '.$user->id,
            'slug' => 'studio-'.$user->id,
            'upload_credits' => 1,
        ]);

        return $user;
    }
}
