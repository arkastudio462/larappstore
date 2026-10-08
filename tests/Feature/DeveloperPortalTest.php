<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_summarises_credits_products_and_prices(): void
    {
        $developer = $this->developer(credits: 3);
        Product::factory()->for($developer, 'developer')->create(['downloads_count' => 120]);
        Product::factory()->for($developer, 'developer')->draft()->create();

        $this->actingAs($developer)
            ->getJson('/api/developer/overview')
            ->assertOk()
            ->assertJsonPath('data.credits', 3)
            ->assertJsonPath('data.unlimited_uploads', false)
            ->assertJsonPath('data.products_count', 2)
            ->assertJsonPath('data.published_count', 1)
            ->assertJsonPath('data.downloads_count', 120)
            ->assertJsonPath('data.prices.upload_slot', 15000)
            ->assertJsonPath('data.prices.unlimited', 150000);
    }

    public function test_overview_includes_the_credit_ledger(): void
    {
        $developer = $this->developer(credits: 1);
        $developer->creditLedger()->create([
            'delta' => 1,
            'type' => 'signup_bonus',
            'balance_after' => 1,
            'description' => 'Bonus awal',
        ]);

        $this->actingAs($developer)
            ->getJson('/api/developer/overview')
            ->assertOk()
            ->assertJsonPath('data.ledger.0.type', 'signup_bonus')
            ->assertJsonPath('data.ledger.0.delta', 1);
    }

    public function test_overview_requires_authentication(): void
    {
        $this->getJson('/api/developer/overview')->assertUnauthorized();
    }

    public function test_overview_forbids_regular_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/developer/overview')
            ->assertForbidden();
    }

    public function test_overview_allows_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson('/api/developer/overview')
            ->assertOk();
    }

    private function developer(int $credits = 1, bool $unlimited = false): User
    {
        $user = User::factory()->developer()->create();

        $user->developerProfile()->create([
            'studio_name' => 'Studio '.$user->id,
            'slug' => 'studio-'.$user->id,
            'upload_credits' => $credits,
            'unlimited_uploads' => $unlimited,
        ]);

        return $user;
    }
}
