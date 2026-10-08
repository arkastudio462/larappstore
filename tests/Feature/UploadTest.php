<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_an_unknown_kind(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/developer/uploads', [
                'kind' => 'document',
                'file' => UploadedFile::fake()->create('film.mp4', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }

    public function test_store_rejects_a_disallowed_extension(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/developer/uploads', [
                'kind' => 'product_file',
                'file' => UploadedFile::fake()->create('malicious.php', 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Berkas produk harus berformat: apk, ipa, exe, msi, dmg, zip, pdf, dll.');
    }

    public function test_store_rejects_an_oversized_image(): void
    {
        $this->actingAs($this->developer())
            ->postJson('/api/developer/uploads', [
                'kind' => 'image',
                'file' => UploadedFile::fake()->create('gambar.png', 5_121),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Ukuran maksimal 5 MB.');
    }

    public function test_store_uploads_a_valid_file(): void
    {
        Storage::fake('r2');

        $response = $this->actingAs($this->developer())
            ->postJson('/api/developer/uploads', [
                'kind' => 'image',
                'file' => UploadedFile::fake()->create('gambar.png', 100, 'image/png'),
            ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['key', 'url', 'size', 'content_type'],
            ]);

        Storage::disk('r2')->assertExists($response->json('data.key'));
    }

    public function test_store_requires_the_developer_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/developer/uploads', [
                'kind' => 'image',
                'file' => UploadedFile::fake()->create('gambar.png', 100),
            ])
            ->assertForbidden();
    }

    public function test_uploads_require_authentication(): void
    {
        $this->postJson('/api/developer/uploads', [
            'kind' => 'image',
            'file' => UploadedFile::fake()->create('gambar.png', 100),
        ])->assertUnauthorized();
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
