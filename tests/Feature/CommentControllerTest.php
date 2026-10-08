<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Notifications\CommentReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_top_level_comments_with_their_replies(): void
    {
        $post = Post::factory()->create();
        $author = User::factory()->create();
        $parent = $this->comment($author, $post, 'Komentar utama');
        $this->comment($author, $post, 'Balasan', $parent->id);

        $this->getJson("/api/posts/{$post->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Komentar utama')
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonPath('data.0.replies.0.body', 'Balasan')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_requires_the_post_to_be_visible(): void
    {
        $post = Post::factory()->draft()->create();

        $this->getJson("/api/posts/{$post->id}/comments")->assertForbidden();
    }

    public function test_store_creates_a_comment_and_increments_the_counter(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Mantap'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Mantap')
            ->assertJsonPath('data.author.username', $user->username);

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => null,
        ]);
        $this->assertSame(1, $post->refresh()->comments_count);
    }

    public function test_store_creates_a_reply_under_a_top_level_comment(): void
    {
        $post = Post::factory()->create();
        $parent = $this->comment(User::factory()->create(), $post, 'Induk');

        $this->actingAs(User::factory()->create())
            ->postJson("/api/posts/{$post->id}/comments", [
                'body' => 'Balasan',
                'parent_id' => $parent->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);

        $this->assertSame(1, $post->refresh()->comments_count);
    }

    public function test_store_rejects_a_parent_from_another_post(): void
    {
        $post = Post::factory()->create();
        $parent = $this->comment(User::factory()->create(), Post::factory()->create(), 'Induk lain');

        $this->actingAs(User::factory()->create())
            ->postJson("/api/posts/{$post->id}/comments", [
                'body' => 'Salah tempat',
                'parent_id' => $parent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_store_rejects_replies_to_a_reply(): void
    {
        $post = Post::factory()->create();
        $parent = $this->comment(User::factory()->create(), $post, 'Induk');
        $reply = $this->comment(User::factory()->create(), $post, 'Balasan', $parent->id);

        $this->actingAs(User::factory()->create())
            ->postJson("/api/posts/{$post->id}/comments", [
                'body' => 'Terlalu dalam',
                'parent_id' => $reply->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_store_requires_a_body(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/posts/{$post->id}/comments", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body')
            ->assertJsonPath('errors.body.0', 'Komentar wajib diisi.');
    }

    public function test_store_requires_authentication(): void
    {
        $post = Post::factory()->create();

        $this->postJson("/api/posts/{$post->id}/comments", ['body' => 'Halo'])->assertUnauthorized();
    }

    public function test_store_notifies_the_post_author(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();
        $commenter = User::factory()->create();

        $this->actingAs($commenter)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Bagus'])
            ->assertCreated();

        Notification::assertSentTo($author, CommentReceived::class);
    }

    public function test_store_does_not_notify_the_commenter_about_their_own_comment(): void
    {
        Notification::fake();

        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs($author)
            ->postJson("/api/posts/{$post->id}/comments", ['body' => 'Catatan sendiri'])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_store_notifies_the_parent_comment_author_on_a_reply(): void
    {
        Notification::fake();

        $post = Post::factory()->create();
        $parentAuthor = User::factory()->create();
        $parent = $this->comment($parentAuthor, $post, 'Induk');
        $replier = User::factory()->create();

        $this->actingAs($replier)
            ->postJson("/api/posts/{$post->id}/comments", [
                'body' => 'Balasan',
                'parent_id' => $parent->id,
            ])
            ->assertCreated();

        Notification::assertSentTo($parentAuthor, CommentReceived::class);
    }

    public function test_destroy_removes_replies_and_decrements_the_counter(): void
    {
        $post = Post::factory()->create();
        $author = User::factory()->create();
        $parent = $this->comment($author, $post, 'Induk');
        $reply = $this->comment(User::factory()->create(), $post, 'Balasan', $parent->id);
        $post->forceFill(['comments_count' => 2])->save();

        $this->actingAs($author)
            ->deleteJson("/api/comments/{$parent->id}")
            ->assertOk();

        $this->assertSoftDeleted('comments', ['id' => $parent->id]);
        $this->assertSoftDeleted('comments', ['id' => $reply->id]);
        $this->assertSame(0, $post->refresh()->comments_count);
    }

    public function test_destroy_forbids_non_owners(): void
    {
        $post = Post::factory()->create();
        $comment = $this->comment(User::factory()->create(), $post, 'Milik orang');

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_someone_elses_comment(): void
    {
        $post = Post::factory()->create();
        $comment = $this->comment(User::factory()->create(), $post, 'Dilaporkan');

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson("/api/comments/{$comment->id}")
            ->assertOk();

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    private function comment(User $author, Post $post, string $body, ?int $parentId = null): Comment
    {
        return $post->allComments()->create([
            'user_id' => $author->id,
            'parent_id' => $parentId,
            'body' => $body,
        ]);
    }
}
