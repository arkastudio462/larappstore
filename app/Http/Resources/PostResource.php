<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
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
            'type' => $this->type,
            'body' => $this->body,
            'media' => $this->media ?? [],
            'status' => $this->when(
                $this->user_id === $request->user()?->id || $request->user()?->isAdmin() === true,
                fn (): string => $this->status,
            ),
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'is_liked' => (bool) $this->resource->getAttribute('is_liked'),
            'author' => UserResource::make($this->whenLoaded('user')),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
