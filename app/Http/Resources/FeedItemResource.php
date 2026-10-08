<?php

namespace App\Http\Resources;

use App\Models\FeedItem;
use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FeedItem
 */
class FeedItemResource extends JsonResource
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
            'subject_type' => $this->label(),
            'post' => $this->when(
                $this->subject_type === FeedItem::SUBJECT_POST && $this->resource->relationLoaded('subject'),
                fn (): PostResource => PostResource::make($this->resource->subject),
            ),
            'product' => $this->when(
                $this->resource->relationLoaded('subject') && $this->resource->subject instanceof Product,
                fn (): ProductResource => ProductResource::make($this->resource->subject),
            ),
            'version' => $this->when(
                $this->resource->relationLoaded('subject') && $this->resource->subject instanceof ProductVersion,
                fn (): ProductVersionResource => ProductVersionResource::make($this->resource->subject),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function label(): string
    {
        return match ($this->subject_type) {
            FeedItem::SUBJECT_POST => 'post',
            FeedItem::SUBJECT_PRODUCT => 'product',
            FeedItem::SUBJECT_RELEASE => 'release',
            default => 'unknown',
        };
    }
}
