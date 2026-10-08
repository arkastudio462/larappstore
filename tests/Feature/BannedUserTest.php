<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_banned_user_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'banned@example.com',
            'password' => 'rahasia123',
            'banned_at' => now(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'banned@example.com',
            'password' => 'rahasia123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Akun kamu sedang diblokir. Hubungi admin.');

        $this->assertGuest();
    }

    public function test_a_banned_user_cannot_use_authenticated_endpoints(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Akun kamu sedang diblokir. Hubungi admin.');
    }

    public function test_a_banned_user_can_still_log_out(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    public function test_an_unbanned_user_regains_access(): void
    {
        $user = User::factory()->create([
            'banned_at' => now(),
        ]);

        $this->actingAs($user)->getJson('/api/auth/me')->assertForbidden();

        $user->forceFill(['banned_at' => null])->save();

        $this->actingAs($user->refresh())
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.is_banned', false);
    }

    public function test_a_banned_user_cannot_create_content(): void
    {
        $user = User::factory()->create(['banned_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/posts', ['body' => 'Halo', 'status' => 'published'])
            ->assertForbidden();
    }
}
