<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_posts_newest_first(): void
    {
        $author = User::factory()->create();
        $this->feedPost($author, 'Lebih lama', now()->subDay());
        $this->feedPost($author, 'Paling baru', now());

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.subject_type', 'post')
            ->assertJsonPath('data.0.post.body', 'Paling baru')
            ->assertJsonPath('data.1.post.body', 'Lebih lama');
    }

    public function test_index_includes_the_author_and_viewer_like_state(): void
    {
        $author = User::factory()->create();
        $post = $this->feedPost($author, 'Halo', now());

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.post.author.username', $author->username)
            ->assertJsonPath('data.0.post.is_liked', false);

        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->postJson('/api/likes', ['likeable_type' => 'post', 'likeable_id' => $post->id])
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson('/api/feed')
            ->assertOk()
            ->assertJsonPath('data.0.post.is_liked', true);
    }

    public function test_index_excludes_posts_that_have_no_published_feed_item(): void
    {
        $author = User::factory()->create();
        $this->feedPost($author, 'Terbit', now());
        Post::factory()->for($author)->draft()->create();

        $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_post_type(): void
    {
        $author = User::factory()->create();
        $this->feedPost($author, 'Sebuah postingan', now());
        $this->feedProduct($author, now()->subHour());

        $this->getJson('/api/feed?type=post')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_type', 'post');

        $this->getJson('/api/feed?type=product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_type', 'product');
    }

    public function test_following_scope_only_returns_followed_actors(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create(['username' => 'diikuti']);
        $stranger = User::factory()->create(['username' => 'asing']);

        $this->feedPost($followed, 'Dari yang diikuti', now());
        $this->feedPost($stranger, 'Dari orang asing', now()->subMinute());

        $this->actingAs($viewer)->postJson('/api/users/diikuti/follow')->assertOk();

        $this->actingAs($viewer)
            ->getJson('/api/feed?scope=following')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.post.body', 'Dari yang diikuti');
    }

    public function test_following_scope_is_empty_for_guests(): void
    {
        $this->feedPost(User::factory()->create(), 'Apa saja', now());

        $this->getJson('/api/feed?scope=following')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_paginates_with_a_cursor(): void
    {
        $author = User::factory()->create();

        foreach (range(1, 16) as $i) {
            $this->feedPost($author, "Postingan {$i}", now()->subMinutes($i));
        }

        $first = $this->getJson('/api/feed')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.has_more', true);

        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $this->getJson('/api/feed?cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    private function feedPost(User $actor, string $body, Carbon $at): Post
    {
        $post = Post::factory()->for($actor)->create([
            'body' => $body,
            'published_at' => $at,
        ]);

        $this->feedItem($actor, Post::class, $post->id, $at);

        return $post;
    }

    private function feedProduct(User $actor, Carbon $at): Product
    {
        $product = Product::factory()->for($actor, 'developer')->create();

        $this->feedItem($actor, Product::class, $product->id, $at);

        return $product;
    }

    /**
     * @param  class-string  $type
     */
    private function feedItem(User $actor, string $type, int $subjectId, Carbon $at): void
    {
        FeedItem::query()->create([
            'actor_id' => $actor->id,
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'created_at' => $at,
        ]);
    }
}
