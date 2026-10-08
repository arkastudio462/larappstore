<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->when($this->isPrivateToViewer($request), fn (): string => $this->email),
            'bio' => $this->bio,
            'avatar_path' => $this->avatar_path,
            'role' => $this->role,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'posts_count' => $this->posts_count,
            'is_following' => (bool) $this->resource->getAttribute('is_following'),
            'is_self' => $this->id === $request->user()?->id,
            'is_banned' => $this->when(
                $this->id === $request->user()?->id || $request->user()?->isAdmin() === true,
                fn (): bool => $this->isBanned(),
            ),
            'developer_profile' => $this->relationLoaded('developerProfile')
                ? DeveloperProfileResource::make($this->developerProfile)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Email hanya untuk pemilik akun dan admin (panel manajemen pengguna).
     */
    private function isPrivateToViewer(Request $request): bool
    {
        return $this->id === $request->user()?->id || $request->user()?->isAdmin() === true;
    }
}
