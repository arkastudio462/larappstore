<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'summary' => $this->summary,
            'description' => $this->description,
            'icon_path' => $this->icon_path,
            'banner_path' => $this->banner_path,
            'price' => $this->price,
            'currency' => $this->currency,
            'is_free' => $this->isFree(),
            'downloads_count' => $this->downloads_count,
            'rating_avg' => $this->whenHas(
                'reviews_avg_rating',
                fn (): float => round((float) $this->reviews_avg_rating, 1),
            ),
            'rating_count' => $this->whenCounted('reviews'),
            'my_review_id' => $this->whenHas('viewer_review_id', fn () => $this->viewer_review_id),
            'is_purchased' => $this->whenHas('viewer_has_purchased', fn (): bool => (bool) $this->viewer_has_purchased),
            'status' => $this->when(
                $viewer?->id === $this->developer_id || $viewer?->isAdmin() === true,
                fn (): ?string => $this->status,
            ),
            'category' => $this->relationLoaded('category') && $this->category
                ? ['name' => $this->category->name, 'slug' => $this->category->slug]
                : null,
            'developer' => UserResource::make($this->whenLoaded('developer')),
            'screenshots' => $this->whenLoaded('screenshots', fn () => $this->screenshots
                ->map(fn ($screenshot): array => [
                    'id' => $screenshot->id,
                    'path' => $screenshot->path,
                    'position' => $screenshot->position,
                ])
                ->values()
                ->all()),
            'latest_version' => $this->when(
                $this->relationLoaded('latestVersion') && $this->latestVersion !== null,
                fn (): ProductVersionResource => ProductVersionResource::make($this->latestVersion),
            ),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
