<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_account_and_returns_201(): void
    {
        $payload = [
            'name' => 'Rina Dewi',
            'username' => 'RinaDewi',
            'email' => 'RINA@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.username', 'rinadewi')
            ->assertJsonPath('data.email', 'rina@example.com')
            ->assertJsonPath('data.role', 'user');

        $this->assertModelExists(User::query()->where('username', 'rinadewi')->firstOrFail());
        $this->assertSame('Rina Dewi', $response->json('data.name'));
    }

    public function test_register_returns_422_when_username_is_taken(): void
    {
        User::factory()->create(['username' => 'rina']);

        $this->postJson('/api/auth/register', [
            'name' => 'Rina Lain',
            'username' => 'rina',
            'email' => 'lain@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);

        $this->assertSame(1, User::query()->count());
    }

    public function test_register_returns_422_when_password_confirmation_does_not_match(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'salah',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseMissing('users', ['username' => 'budi']);
    }

    public function test_login_returns_422_when_credentials_are_invalid(): void
    {
        User::factory()->create(['email' => 'rina@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'rina@example.com',
            'password' => 'bukan-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Email atau password salah.');
    }

    public function test_login_starts_a_session_that_me_can_read(): void
    {
        User::factory()->create([
            'email' => 'rina@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'rina@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'rina@example.com');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'rina@example.com');
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_logout_ends_the_session(): void
    {
        User::factory()->create([
            'email' => 'rina@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'rina@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/auth/me')->assertOk();

        $this->postJson('/api/auth/logout')->assertOk();

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_update_changes_profile_and_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->putJson('/api/auth/me', [
                'name' => 'Nama Baru',
                'username' => 'namabaru',
                'bio' => 'Halo dunia',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Baru')
            ->assertJsonPath('data.username', 'namabaru')
            ->assertJsonPath('data.bio', 'Halo dunia');

        $user->refresh();

        $this->assertTrue(Hash::check('password-baru', $user->password));
    }

    public function test_update_returns_422_when_email_belongs_to_another_user(): void
    {
        User::factory()->create(['email' => 'sudah@diambil.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/auth/me', ['email' => 'sudah@diambil.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
