<?php

namespace App\Http\Resources;

use App\Models\DeveloperProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeveloperProfile
 */
class DeveloperProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'studio_name' => $this->studio_name,
            'slug' => $this->slug,
            'bio' => $this->bio,
            'website' => $this->website,
            'upload_credits' => $this->upload_credits,
            'unlimited_uploads' => $this->unlimited_uploads,
        ];
    }
}
