<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Otorisasi komentar: author boleh menghapus, admin boleh menghapus kapan saja.
 */
class CommentPolicy
{
    public function delete(User $user, Comment $comment): bool
    {
        return $user->role === User::ROLE_ADMIN || $comment->user_id === $user->id;
    }
}
