<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke author postingan saat orang lain menyukainya.
 */
class LikeReceived extends Notification
{
    public function __construct(public User $actor, public Post $post) {}

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
            'type' => 'like_received',
            'message' => $this->actor->name.' menyukai postinganmu.',
            'url' => '/postingan/'.$this->post->id,
            'actor' => [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
                'avatar_path' => $this->actor->avatar_path,
            ],
            'subject' => [
                'type' => 'post',
                'id' => $this->post->id,
                'excerpt' => $this->excerpt(),
            ],
        ];
    }

    private function excerpt(): string
    {
        return mb_strimwidth($this->post->body, 0, 80, '…');
    }
}
