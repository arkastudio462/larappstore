<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\LikeReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LikeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_adds_a_like_and_increments_the_counter(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.is_liked', true)
            ->assertJsonPath('data.likes_count', 1);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => Post::class,
            'likeable_id' => $post->id,
        ]);
        $this->assertSame(1, $post->refresh()->likes_count);
    }

    public function test_toggle_removes_the_like_on_a_second_call(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.is_liked', false)
            ->assertJsonPath('data.likes_count', 0);

        $this->assertDatabaseCount('likes', 0);
        $this->assertSame(0, $post->refresh()->likes_count);
    }

    public function test_like_counter_never_becomes_negative(): void
    {
        $post = Post::factory()->create(['likes_count' => 0]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.likes_count', 0);
    }

    public function test_toggle_notifies_the_post_author(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk();

        Notification::assertSentTo($author, LikeReceived::class);
    }

    public function test_toggle_does_not_notify_when_liking_your_own_post(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs($author)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_toggle_rejects_an_unknown_likeable_type(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/likes', ['likeable_type' => 'comment', 'likeable_id' => $post->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('likeable_type');
    }

    public function test_toggle_returns_404_for_a_missing_target(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => 9999])
            ->assertNotFound();
    }

    public function test_toggle_forbids_liking_someone_elses_draft(): void
    {
        $post = Post::factory()->draft()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertForbidden();

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_toggle_requires_authentication(): void
    {
        $post = Post::factory()->create();

        $this->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertUnauthorized();
    }
}
