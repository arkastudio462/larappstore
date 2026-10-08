<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'studio_name',
    'slug',
    'bio',
    'website',
    'upload_credits',
    'unlimited_uploads',
])]
class DeveloperProfile extends Model
{
    protected function casts(): array
    {
        return [
            'upload_credits' => 'integer',
            'unlimited_uploads' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
