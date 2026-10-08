<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Otorisasi postingan: author boleh mengubah/menerbitkan/menghapus,
 * admin boleh menghapus kapan saja (PRD §3).
 */
class PostPolicy
{
    /**
     * Postingan terbit boleh dilihat siapa saja (termasuk tamu); draft hanya
     * untuk author dan admin.
     */
    public function view(?User $user, Post $post): bool
    {
        if ($post->status === Post::STATUS_PUBLISHED) {
            return true;
        }

        return $user !== null && $this->isOwner($user, $post);
    }

    public function update(User $user, Post $post): bool
    {
        return $this->isOwner($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->isOwner($user, $post);
    }

    public function publish(User $user, Post $post): bool
    {
        return $this->isOwner($user, $post);
    }

    private function isOwner(User $user, Post $post): bool
    {
        return $user->role === User::ROLE_ADMIN || $post->user_id === $user->id;
    }
}
