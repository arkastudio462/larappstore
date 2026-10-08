<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_another_users_post(): void
    {
        $post = Post::factory()->create();
        $post->publish();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/posts/{$post->id}")
            ->assertOk();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->assertDatabaseCount('feed_items', 0);
    }

    public function test_admin_can_delete_a_comment_with_its_replies(): void
    {
        $post = Post::factory()->create();
        $parent = $post->allComments()->create(['user_id' => User::factory()->create()->id, 'body' => 'Induk']);
        $reply = $post->allComments()->create([
            'user_id' => User::factory()->create()->id,
            'parent_id' => $parent->id,
            'body' => 'Balasan',
        ]);
        $post->forceFill(['comments_count' => 2])->save();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/comments/{$parent->id}")
            ->assertOk();

        $this->assertSoftDeleted('comments', ['id' => $parent->id]);
        $this->assertSoftDeleted('comments', ['id' => $reply->id]);
        $this->assertSame(0, $post->refresh()->comments_count);
    }

    public function test_admin_can_delete_a_product_and_its_feed_items(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();
        $product->publish();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/products/{$product->slug}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseCount('feed_items', 0);
    }

    public function test_admin_can_delete_a_review(): void
    {
        $review = Review::query()->create([
            'product_id' => Product::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'rating' => 1,
            'status' => Review::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_admin_actions_require_the_admin_role(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->developer()->create())
            ->deleteJson("/api/admin/posts/{$post->id}")
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->deleteJson("/api/admin/posts/{$post->id}")
            ->assertForbidden();
    }

    public function test_admin_actions_require_authentication(): void
    {
        $post = Post::factory()->create();

        $this->deleteJson("/api/admin/posts/{$post->id}")->assertUnauthorized();
    }

    public function test_admin_delete_routes_return_404_for_soft_deleted_content(): void
    {
        $comment = Comment::query()->create([
            'post_id' => Post::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Sudah dihapus',
        ]);
        $comment->delete();

        $this->actingAs($this->admin())
            ->deleteJson("/api/admin/comments/{$comment->id}")
            ->assertNotFound();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
