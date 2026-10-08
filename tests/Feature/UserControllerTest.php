<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Notifications\FollowedYou;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_public_profile(): void
    {
        $user = User::factory()->create([
            'username' => 'rina',
            'name' => 'Rina Dewi',
            'bio' => 'Penggemar aplikasi warung',
        ]);

        $this->getJson('/api/users/rina')
            ->assertOk()
            ->assertJsonPath('data.username', 'rina')
            ->assertJsonPath('data.name', 'Rina Dewi')
            ->assertJsonPath('data.bio', 'Penggemar aplikasi warung')
            ->assertJsonPath('data.is_self', false)
            ->assertJsonPath('data.is_following', false);

        $this->assertModelExists($user);
    }

    public function test_show_hides_email_from_visitors(): void
    {
        User::factory()->create(['username' => 'rina', 'email' => 'rina@example.com']);

        $this->getJson('/api/users/rina')
            ->assertOk()
            ->assertJsonMissingPath('data.email');
    }

    public function test_show_returns_404_for_unknown_username(): void
    {
        $this->getJson('/api/users/tidak-ada')->assertNotFound();
    }

    public function test_show_marks_self_for_the_owner(): void
    {
        $user = User::factory()->create(['username' => 'rina']);

        $this->actingAs($user)
            ->getJson('/api/users/rina')
            ->assertOk()
            ->assertJsonPath('data.is_self', true)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_follow_creates_relation_and_increments_counters(): void
    {
        $follower = User::factory()->create(['username' => 'budi']);
        $target = User::factory()->create(['username' => 'rina']);

        $this->actingAs($follower)
            ->postJson('/api/users/rina/follow')
            ->assertOk()
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $follower->id,
            'followable_type' => User::class,
            'followable_id' => $target->id,
        ]);
        $this->assertSame(1, $target->refresh()->followers_count);
        $this->assertSame(1, $follower->refresh()->following_count);
    }

    public function test_follow_removes_relation_when_called_again(): void
    {
        $follower = User::factory()->create(['username' => 'budi']);
        $target = User::factory()->create(['username' => 'rina']);

        $this->actingAs($follower)->postJson('/api/users/rina/follow')->assertOk();

        $this->actingAs($follower)
            ->postJson('/api/users/rina/follow')
            ->assertOk()
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.followers_count', 0);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $follower->id,
            'followable_type' => User::class,
            'followable_id' => $target->id,
        ]);
        $this->assertSame(0, $target->refresh()->followers_count);
        $this->assertSame(0, $follower->refresh()->following_count);
    }

    public function test_follow_notifies_the_target(): void
    {
        Notification::fake();

        $follower = User::factory()->create(['username' => 'budi']);
        $target = User::factory()->create(['username' => 'rina']);

        $this->actingAs($follower)->postJson('/api/users/rina/follow')->assertOk();

        Notification::assertSentTo($target, FollowedYou::class);
    }

    public function test_unfollowing_does_not_send_another_notification(): void
    {
        Notification::fake();

        $follower = User::factory()->create(['username' => 'budi']);
        $target = User::factory()->create(['username' => 'rina']);

        $this->actingAs($follower)->postJson('/api/users/rina/follow')->assertOk();
        $this->actingAs($follower)->postJson('/api/users/rina/follow')->assertOk();

        Notification::assertSentToTimes($target, FollowedYou::class, 1);
    }

    public function test_follow_returns_422_when_following_self(): void
    {
        $user = User::factory()->create(['username' => 'rina']);

        $this->actingAs($user)
            ->postJson('/api/users/rina/follow')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kamu tidak bisa mengikuti diri sendiri.');

        $this->assertDatabaseCount('follows', 0);
    }

    public function test_follow_returns_401_when_unauthenticated(): void
    {
        User::factory()->create(['username' => 'rina']);

        $this->postJson('/api/users/rina/follow')->assertUnauthorized();
    }

    public function test_followers_lists_users_who_follow_the_profile(): void
    {
        $owner = User::factory()->create(['username' => 'rina']);
        $follower = User::factory()->create(['username' => 'budi']);

        $this->actingAs($follower)->postJson('/api/users/rina/follow')->assertOk();

        $this->getJson('/api/users/rina/followers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'budi')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_posts_hides_drafts_from_visitors(): void
    {
        $owner = User::factory()->create(['username' => 'rina']);
        Post::factory()->for($owner)->create();
        Post::factory()->for($owner)->draft()->create();

        $this->getJson('/api/users/rina/posts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.status');
    }

    public function test_posts_includes_drafts_for_the_owner(): void
    {
        $owner = User::factory()->create(['username' => 'rina']);
        Post::factory()->for($owner)->create();
        Post::factory()->for($owner)->draft()->create();

        $this->actingAs($owner)
            ->getJson('/api/users/rina/posts')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_products_lists_only_published_products_of_that_developer(): void
    {
        $developer = User::factory()->create(['username' => 'arka']);
        Product::factory()->for($developer, 'developer')->create();
        Product::factory()->for($developer, 'developer')->draft()->create();
        Product::factory()->create();

        $this->getJson('/api/users/arka/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }
}
