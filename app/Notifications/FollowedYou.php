<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke pengguna saat ada orang mulai mengikutinya.
 */
class FollowedYou extends Notification
{
    public function __construct(public User $follower) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'followed_you',
            'message' => $this->follower->name.' mulai mengikuti kamu.',
            'url' => '/u/'.$this->follower->username,
            'actor' => [
                'id' => $this->follower->id,
                'username' => $this->follower->username,
                'name' => $this->follower->name,
                'avatar_path' => $this->follower->avatar_path,
            ],
            'subject' => null,
        ];
    }
}
