<?php

namespace App\Policies;

use App\Models\User;

/**
 * Otorisasi manajemen pengguna oleh admin (PRD §3 & §7):
 * ubah role dan ban. Admin tidak bisa men ban diri sendiri.
 */
class UserPolicy
{
    public function assignRole(User $user, User $target): bool
    {
        return $this->isAdminNotSelf($user, $target);
    }

    public function ban(User $user, User $target): bool
    {
        return $this->isAdminNotSelf($user, $target);
    }

    private function isAdminNotSelf(User $user, User $target): bool
    {
        return $user->role === User::ROLE_ADMIN && $target->id !== $user->id;
    }
}
