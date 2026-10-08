<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_published_post_to_a_guest(): void
    {
        $post = Post::factory()->create(['body' => 'Halo semua']);

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.body', 'Halo semua')
            ->assertJsonPath('data.is_liked', false)
            ->assertJsonMissingPath('data.status');
    }

    public function test_show_returns_draft_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->draft()->create();

        $this->actingAs($owner)
            ->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.status', Post::STATUS_DRAFT);
    }

    public function test_show_forbids_other_users_from_seeing_a_draft(): void
    {
        $post = Post::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/posts/{$post->id}")
            ->assertForbidden();
    }

    public function test_show_returns_404_for_a_soft_deleted_post(): void
    {
        $post = Post::factory()->create();
        $post->delete();

        $this->getJson("/api/posts/{$post->id}")->assertNotFound();
    }

    public function test_store_creates_a_published_post_with_feed_item_and_counter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', ['body' => 'Kabar baik', 'status' => Post::STATUS_PUBLISHED])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Kabar baik')
            ->assertJsonPath('data.status', Post::STATUS_PUBLISHED);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'status' => Post::STATUS_PUBLISHED,
        ]);
        $this->assertDatabaseHas('feed_items', [
            'actor_id' => $user->id,
            'subject_type' => Post::class,
        ]);
        $this->assertSame(1, $user->refresh()->posts_count);
    }

    public function test_store_creates_a_draft_without_feed_item_or_counter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', ['body' => 'Simpan dulu', 'status' => Post::STATUS_DRAFT])
            ->assertCreated()
            ->assertJsonPath('data.status', Post::STATUS_DRAFT);

        $this->assertDatabaseCount('feed_items', 0);
        $this->assertSame(0, $user->refresh()->posts_count);
    }

    public function test_store_defaults_to_a_draft(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', ['body' => 'Tanpa status'])
            ->assertCreated()
            ->assertJsonPath('data.status', Post::STATUS_DRAFT);

        $this->assertDatabaseCount('feed_items', 0);
    }

    public function test_store_requires_a_body(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/posts', ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', 'Tulisan postingan wajib diisi.');
    }

    public function test_store_rejects_more_than_four_media_items(): void
    {
        $media = array_map(fn (int $i): array => ['path' => "posts/{$i}.jpg"], range(1, 5));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/posts', ['body' => 'Dengan lampiran', 'media' => $media])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('media')
            ->assertJsonPath('errors.media.0', 'Maksimal 4 lampiran per postingan.');
    }

    public function test_store_marks_a_post_with_media_as_an_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'body' => 'Dengan gambar',
                'media' => [['path' => 'posts/1.jpg']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.type', Post::TYPE_IMAGE)
            ->assertJsonPath('data.media.0.path', 'posts/1.jpg');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/posts', ['body' => 'Halo'])->assertUnauthorized();
    }

    public function test_update_changes_the_body_for_its_owner(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create(['body' => 'Lama']);

        $this->actingAs($owner)
            ->putJson("/api/posts/{$post->id}", ['body' => 'Baru'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Baru');

        $this->assertSame('Baru', $post->refresh()->body);
    }

    public function test_update_forbids_non_owners(): void
    {
        $post = Post::factory()->create(['body' => 'Lama']);

        $this->actingAs(User::factory()->create())
            ->putJson("/api/posts/{$post->id}", ['body' => 'Bajakan'])
            ->assertForbidden();

        $this->assertSame('Lama', $post->refresh()->body);
    }

    public function test_destroy_soft_deletes_and_clears_feed_and_counter(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->draft()->create();
        $post->publish();

        $this->actingAs($owner)->deleteJson("/api/posts/{$post->id}")->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseCount('feed_items', 0);
        $this->assertSame(0, $owner->refresh()->posts_count);
    }

    public function test_destroy_forbids_non_owners(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/posts/{$post->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_someone_elses_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/posts/{$post->id}")
            ->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_publish_moves_a_draft_to_published(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->draft()->create();

        $this->actingAs($owner)
            ->postJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', Post::STATUS_PUBLISHED);

        $this->assertNotNull($post->refresh()->published_at);
        $this->assertDatabaseCount('feed_items', 1);
        $this->assertSame(1, $owner->refresh()->posts_count);
    }

    public function test_publish_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->draft()->create();
        $post->publish();

        $this->actingAs($owner)
            ->postJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('message', 'Postingan sudah terbit.');

        $this->assertDatabaseCount('feed_items', 1);
        $this->assertSame(1, $owner->refresh()->posts_count);
    }

    public function test_publish_forbids_non_owners(): void
    {
        $post = Post::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/posts/{$post->id}/publish")
            ->assertForbidden();

        $this->assertDatabaseCount('feed_items', 0);
    }

    public function test_feed_item_is_removed_when_a_post_is_deleted_directly(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        FeedItem::query()->create([
            'actor_id' => $owner->id,
            'subject_type' => Post::class,
            'subject_id' => $post->id,
            'created_at' => now(),
        ]);
        $owner->forceFill(['posts_count' => 1])->save();

        $post->withdrawFromFeed();

        $this->assertDatabaseCount('feed_items', 0);
        $this->assertSame(0, $owner->refresh()->posts_count);
    }
}
