<?php

namespace App\Http\Resources;

use App\Models\ProductVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVersion
 */
class ProductVersionResource extends JsonResource
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
            'version' => $this->version,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'changelog' => $this->changelog,
            'download_count' => $this->download_count,
            'status' => $this->status,
            'is_latest' => $this->is_latest,
            'product' => ProductResource::make($this->whenLoaded('product')),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
