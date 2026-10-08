<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Otorisasi produk: developer pemilik boleh mengubah/menerbitkan/mengarsipkan/
 * menghapus, admin boleh menghapus kapan saja (PRD §3).
 */
class ProductPolicy
{
    /**
     * Produk terbit boleh dilihat siapa saja (termasuk tamu); draft/arsip hanya
     * untuk developer pemilik dan admin.
     */
    public function view(?User $user, Product $product): bool
    {
        if ($product->status === Product::STATUS_PUBLISHED) {
            return true;
        }

        return $user !== null && $this->isOwner($user, $product);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function publish(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    public function archive(User $user, Product $product): bool
    {
        return $this->isOwner($user, $product);
    }

    private function isOwner(User $user, Product $product): bool
    {
        return $user->role === User::ROLE_ADMIN || $product->developer_id === $user->id;
    }
}
