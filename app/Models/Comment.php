<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'post_id', 'parent_id', 'body'])]
class Comment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'body' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest('id');
    }

    /**
     * Hapus komentar beserta balasan satu tingkat miliknya dan turunkan
     * penghitung komentar postingan. Mengembalikan jumlah yang terhapus.
     */
    public function purge(): int
    {
        $postId = $this->post_id;
        $removed = 1 + $this->replies()->delete();

        $this->delete();

        Post::query()
            ->whereKey($postId)
            ->where('comments_count', '>=', $removed)
            ->decrement('comments_count', $removed);

        return $removed;
    }
}
