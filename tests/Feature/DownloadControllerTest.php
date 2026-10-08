<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_product_returns_a_download_url_and_counts_the_download(): void
    {
        config(['filesystems.disks.r2.url' => 'https://files.example.com']);

        $product = Product::factory()->create(['slug' => 'catatan-kilat']);
        $version = $this->version($product);

        $this->postJson('/api/products/catatan-kilat/download')
            ->assertOk()
            ->assertJsonPath('data.version', '1.0.0')
            ->assertJsonPath('data.url', "https://files.example.com/products/{$product->id}/release.apk")
            ->assertJsonPath('data.expires_in', null);

        $this->assertSame(1, $product->refresh()->downloads_count);
        $this->assertSame(1, $version->refresh()->download_count);
    }

    public function test_download_url_falls_back_to_the_raw_path_without_r2_config(): void
    {
        config(['filesystems.disks.r2.url' => null]);

        $product = Product::factory()->create(['slug' => 'catatan']);
        $this->version($product);

        $this->postJson('/api/products/catatan/download')
            ->assertOk()
            ->assertJsonPath('data.url', "products/{$product->id}/release.apk");
    }

    public function test_paid_product_requires_a_purchase(): void
    {
        $product = Product::factory()->paid(15000)->create(['slug' => 'berbayar']);
        $this->version($product);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/products/berbayar/download')
            ->assertStatus(402)
            ->assertJsonPath('message', 'Produk berbayar. Selesaikan pembelian dulu untuk mengunduh.');

        $this->assertSame(0, $product->refresh()->downloads_count);
    }

    public function test_paid_product_requires_authentication(): void
    {
        $product = Product::factory()->paid(15000)->create(['slug' => 'berbayar']);
        $this->version($product);

        $this->postJson('/api/products/berbayar/download')->assertStatus(402);
    }

    public function test_purchased_product_returns_a_signed_url(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->paid(15000)->create(['slug' => 'berbayar']);
        $version = $this->version($product);
        $product->purchases()->create(['user_id' => $buyer->id]);

        $this->mock(UploadService::class, function ($mock) use ($version): void {
            $mock->shouldReceive('downloadUrl')
                ->once()
                ->with($version->file_path, true)
                ->andReturn('https://r2.example.com/signed?token=abc');
        });

        $this->actingAs($buyer)
            ->postJson('/api/products/berbayar/download')
            ->assertOk()
            ->assertJsonPath('data.url', 'https://r2.example.com/signed?token=abc')
            ->assertJsonPath('data.expires_in', 300);

        $this->assertSame(1, $product->refresh()->downloads_count);
    }

    public function test_product_detail_exposes_the_purchase_state(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->paid(15000)->create(['slug' => 'berbayar']);

        $this->actingAs($buyer)
            ->getJson('/api/products/berbayar')
            ->assertOk()
            ->assertJsonPath('data.is_purchased', false);

        $product->purchases()->create(['user_id' => $buyer->id]);

        $this->actingAs($buyer)
            ->getJson('/api/products/berbayar')
            ->assertOk()
            ->assertJsonPath('data.is_purchased', true);
    }

    public function test_product_detail_hides_the_purchase_state_from_guests(): void
    {
        Product::factory()->paid(15000)->create(['slug' => 'berbayar']);

        $this->getJson('/api/products/berbayar')
            ->assertOk()
            ->assertJsonMissingPath('data.is_purchased');
    }

    public function test_download_returns_404_when_there_is_no_published_version(): void
    {
        Product::factory()->create(['slug' => 'kosong']);

        $this->postJson('/api/products/kosong/download')
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk ini belum punya versi yang bisa diunduh.');
    }

    public function test_download_ignores_draft_versions(): void
    {
        $product = Product::factory()->create(['slug' => 'draft-versi']);
        $this->version($product, [
            'status' => ProductVersion::STATUS_DRAFT,
            'published_at' => null,
        ]);

        $this->postJson('/api/products/draft-versi/download')->assertNotFound();
    }

    public function test_download_of_a_draft_product_is_forbidden_for_guests(): void
    {
        $product = Product::factory()->draft()->create(['slug' => 'rahasia']);
        $this->version($product);

        $this->postJson('/api/products/rahasia/download')->assertForbidden();
    }

    private function version(Product $product, array $attributes = []): ProductVersion
    {
        return $product->versions()->create(array_merge([
            'version' => '1.0.0',
            'file_path' => "products/{$product->id}/release.apk",
            'file_size' => 4096,
            'file_type' => 'apk',
            'checksum_sha256' => str_repeat('b', 64),
            'status' => ProductVersion::STATUS_PUBLISHED,
            'is_latest' => true,
            'published_at' => now(),
        ], $attributes));
    }
}
