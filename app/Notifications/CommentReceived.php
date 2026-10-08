<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke author postingan saat ada komentar baru.
 */
class CommentReceived extends Notification
{
    public function __construct(public User $actor, public Comment $comment) {}

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
            'type' => 'comment_received',
            'message' => $this->actor->name.' mengomentari postinganmu.',
            'url' => '/postingan/'.$this->comment->post_id,
            'actor' => [
                'id' => $this->actor->id,
                'username' => $this->actor->username,
                'name' => $this->actor->name,
                'avatar_path' => $this->actor->avatar_path,
            ],
            'subject' => [
                'type' => 'comment',
                'id' => $this->comment->id,
                'post_id' => $this->comment->post_id,
                'excerpt' => mb_strimwidth($this->comment->body, 0, 80, '…'),
            ],
        ];
    }
}
