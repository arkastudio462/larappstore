<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_users_with_emails_visible_to_the_admin(): void
    {
        $target = User::factory()->create(['username' => 'rina', 'email' => 'rina@example.com']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users?q=rina')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', $target->username)
            ->assertJsonPath('data.0.email', $target->email);
    }

    public function test_index_filters_by_role(): void
    {
        User::factory()->developer()->create();
        User::factory()->create();

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users?role=developer')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', User::ROLE_DEVELOPER);
    }

    public function test_index_searches_by_username(): void
    {
        User::factory()->create(['username' => 'arunika']);
        User::factory()->create(['username' => 'budi']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/users?q=arunika')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'arunika');
    }

    public function test_update_promotes_a_user_to_developer(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/users/{$target->id}", ['role' => User::ROLE_DEVELOPER])
            ->assertOk()
            ->assertJsonPath('data.role', User::ROLE_DEVELOPER);

        $this->assertSame(User::ROLE_DEVELOPER, $target->refresh()->role);
    }

    public function test_update_bans_and_unbans_a_user(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/users/{$target->id}", ['banned' => true])
            ->assertOk()
            ->assertJsonPath('data.is_banned', true);

        $this->assertNotNull($target->refresh()->banned_at);

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/users/{$target->id}", ['banned' => false])
            ->assertOk()
            ->assertJsonPath('data.is_banned', false);

        $this->assertNull($target->refresh()->banned_at);
    }

    public function test_an_admin_cannot_change_their_own_role_or_ban_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}", ['role' => User::ROLE_USER])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}", ['banned' => true])
            ->assertForbidden();

        $this->assertSame(User::ROLE_ADMIN, $admin->refresh()->role);
        $this->assertNull($admin->refresh()->banned_at);
    }

    public function test_update_rejects_an_unknown_role(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->patchJson("/api/admin/users/{$target->id}", ['role' => 'superadmin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_user_management_requires_the_admin_role(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }
}
