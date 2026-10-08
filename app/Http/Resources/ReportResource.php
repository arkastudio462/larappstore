<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
class ReportResource extends JsonResource
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
            'reason' => $this->reason,
            'status' => $this->status,
            'reportable_type' => class_basename($this->reportable_type),
            'subject' => $this->when($this->relationLoaded('reportable'), fn (): ?array => $this->subject()),
            'reporter' => UserResource::make($this->whenLoaded('reporter')),
            'resolved_by' => $this->resolved_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Ringkasan singkat konten yang dilaporkan agar admin tahu konteksnya.
     *
     * @return array<string, mixed>|null
     */
    private function subject(): ?array
    {
        $model = $this->resource->reportable;

        if ($model === null) {
            return null;
        }

        return match (true) {
            $model instanceof Post => [
                'id' => $model->id,
                'label' => mb_strimwidth($model->body, 0, 90, '…'),
                'url' => '/postingan/'.$model->id,
            ],
            $model instanceof Comment => [
                'id' => $model->id,
                'label' => mb_strimwidth($model->body, 0, 90, '…'),
                'url' => '/postingan/'.$model->post_id,
            ],
            $model instanceof Product => [
                'id' => $model->id,
                'label' => $model->title,
                'url' => '/produk/'.$model->slug,
            ],
            $model instanceof Review => [
                'id' => $model->id,
                'label' => 'Ulasan '.$model->rating.'★ '.mb_strimwidth((string) $model->body, 0, 70, '…'),
                'url' => null,
            ],
            default => ['id' => $model->getKey(), 'label' => 'Konten', 'url' => null],
        };
    }
}
