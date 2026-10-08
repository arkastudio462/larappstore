<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'product_id',
    'version',
    'file_path',
    'file_size',
    'file_type',
    'checksum_sha256',
    'changelog',
    'download_count',
    'status',
    'is_latest',
    'published_at',
])]
class ProductVersion extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'download_count' => 'integer',
            'is_latest' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    /**
     * Terbitkan versi: hanya satu versi terbaru per produk (`is_latest`),
     * lalu catat rilisnya ke feed sebagai `release` (PRD §4.1).
     *
     * Idempoten — memanggil ulang pada versi yang sudah terbit tidak
     * menggandakan baris feed.
     */
    public function publish(): bool
    {
        if ($this->status === self::STATUS_PUBLISHED) {
            return false;
        }

        DB::transaction(function (): void {
            self::query()
                ->where('product_id', $this->product_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_latest' => false]);

            $this->forceFill([
                'status' => self::STATUS_PUBLISHED,
                'is_latest' => true,
                'published_at' => $this->published_at ?? now(),
            ])->save();

            FeedItem::query()->create([
                'actor_id' => $this->product->developer_id,
                'subject_type' => FeedItem::SUBJECT_RELEASE,
                'subject_id' => $this->id,
                'created_at' => $this->published_at,
            ]);
        });

        return true;
    }
}
