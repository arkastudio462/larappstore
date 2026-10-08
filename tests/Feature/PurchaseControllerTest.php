<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_viewers_purchases(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->paid(15000)->create(['title' => 'Punyaku']);
        $otherProduct = Product::factory()->paid(20000)->create(['title' => 'Milik orang']);

        $buyer->purchases()->create(['product_id' => $product->id]);
        $other->purchases()->create(['product_id' => $otherProduct->id]);

        $this->actingAs($buyer)
            ->getJson('/api/me/purchases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.title', 'Punyaku')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/me/purchases')->assertUnauthorized();
    }

    public function test_index_returns_an_empty_list_for_users_without_purchases(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/me/purchases')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
