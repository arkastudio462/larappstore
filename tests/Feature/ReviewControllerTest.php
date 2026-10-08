<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])
            ->assertUnauthorized();
    }

    public function test_store_requires_a_rating(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/products/{$product->slug}/reviews", ['body' => 'Tanpa bintang'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating')
            ->assertJsonPath('errors.rating.0', 'Pilih dulu bintang penilaianmu.');
    }

    public function test_store_rejects_a_rating_outside_one_to_five(): void
    {
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 9])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_store_creates_a_published_review(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 4, 'body' => 'Mantap'])
            ->assertCreated()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.author.username', $user->username);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 4,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }

    public function test_store_rejects_a_second_review_from_the_same_user(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", ['rating' => 5])->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/products/{$product->slug}/reviews", ['rating' => 3])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kamu sudah memberi ulasan untuk produk ini.');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_store_shows_up_in_the_product_rating(): void
    {
        $product = Product::factory()->create(['slug' => 'catatan']);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/products/catatan/reviews', ['rating' => 5])
            ->assertCreated();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/products/catatan/reviews', ['rating' => 4])
            ->assertCreated();

        $this->getJson('/api/products/catatan')
            ->assertOk()
            ->assertJsonPath('data.rating_avg', 4.5)
            ->assertJsonPath('data.rating_count', 2);
    }

    public function test_update_changes_the_review_for_its_author(): void
    {
        $product = Product::factory()->create();
        $author = User::factory()->create();
        $review = $this->review($product, $author, 3);

        $this->actingAs($author)
            ->putJson("/api/reviews/{$review->id}", ['rating' => 5, 'body' => 'Ternyata bagus'])
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.body', 'Ternyata bagus');
    }

    public function test_update_forbids_non_authors(): void
    {
        $review = $this->review(Product::factory()->create(), User::factory()->create(), 3);

        $this->actingAs(User::factory()->create())
            ->putJson("/api/reviews/{$review->id}", ['rating' => 1])
            ->assertForbidden();

        $this->assertSame(3, $review->refresh()->rating);
    }

    public function test_admin_can_update_someone_elses_review(): void
    {
        $review = $this->review(Product::factory()->create(), User::factory()->create(), 2);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson("/api/reviews/{$review->id}", ['rating' => 1])
            ->assertOk();

        $this->assertSame(1, $review->refresh()->rating);
    }

    private function review(Product $product, User $user, int $rating): Review
    {
        return $product->reviews()->create([
            'user_id' => $user->id,
            'rating' => $rating,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }
}
