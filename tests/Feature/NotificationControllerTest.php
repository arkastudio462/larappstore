<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\FollowedYou;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_notifications_with_an_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowedYou(User::factory()->create()));

        $this->actingAs($user)
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'followed_you')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_read_all_marks_every_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new FollowedYou(User::factory()->create()));
        $user->notify(new FollowedYou(User::factory()->create()));

        $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_read_all_requires_authentication(): void
    {
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }
}
