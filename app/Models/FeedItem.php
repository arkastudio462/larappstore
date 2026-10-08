<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['actor_id', 'subject_type', 'subject_id', 'created_at'])]
class FeedItem extends Model
{
    public $timestamps = false;

    /*
     * Nilai disimpan sebagai nama kelas penuh agar konsisten dengan kolom
     * polimorfik lain di aplikasi (`follows.followable_type`,
     * `likes.likeable_type`, `reports.reportable_type`) sehingga relasi
     * `subject()` bawaan Eloquent langsung bekerja tanpa morph map.
     */
    public const SUBJECT_POST = Post::class;

    public const SUBJECT_PRODUCT = Product::class;

    public const SUBJECT_RELEASE = ProductVersion::class;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'subject_id' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
