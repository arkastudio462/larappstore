<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * Otorisasi ulasan: author boleh mengubah/menghapus, admin boleh menghapus kapan saja.
 */
class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $user->role === User::ROLE_ADMIN || $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $user->role === User::ROLE_ADMIN || $review->user_id === $user->id;
    }
}
