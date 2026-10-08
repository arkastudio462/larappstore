<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_promotes_user_and_grants_one_free_credit(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($user)
            ->postJson('/api/developer/upgrade')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'developer')
            ->assertJsonPath('data.user.developer_profile.upload_credits', 1);

        $user->refresh();

        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertSame(1, $user->uploadCredits());
        $this->assertModelExists($user->developerProfile);
    }

    public function test_upgrade_writes_a_signup_bonus_to_the_ledger(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/developer/upgrade')->assertOk();

        $this->assertDatabaseHas('credit_ledger', [
            'user_id' => $user->id,
            'delta' => 1,
            'type' => CreditLedger::TYPE_SIGNUP_BONUS,
            'balance_after' => 1,
        ]);
    }

    public function test_upgrade_is_idempotent_when_called_twice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/developer/upgrade')->assertOk();
        $this->actingAs($user)->postJson('/api/developer/upgrade')->assertOk();

        $this->assertSame(1, DeveloperProfile::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, CreditLedger::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, $user->refresh()->uploadCredits());
    }

    public function test_upgrade_leaves_admin_role_unchanged(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)
            ->postJson('/api/developer/upgrade')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertSame(User::ROLE_ADMIN, $user->refresh()->role);
        $this->assertDatabaseMissing('developer_profiles', ['user_id' => $user->id]);
    }

    public function test_upgrade_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/developer/upgrade')->assertUnauthorized();
    }
}
