<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'type', 'body', 'media', 'status', 'published_at'])]
class Post extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'published_at' => 'datetime',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function likers(): MorphToMany
    {
        return $this->morphToMany(User::class, 'likeable', 'likes', 'likeable_id', 'user_id');
    }

    /**
     * Like milik penonton yang sedang login.
     *
     * Dipakai bersama `withExists('likedByViewer as is_liked')` sehingga
     * status suka tidak butuh query tambahan per baris hasil.
     */
    public function likedByViewer(): MorphOne
    {
        return $this->morphOne(Like::class, 'likeable')->where('user_id', auth()->id());
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest('id');
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    /**
     * Terbitkan postingan: set status + `published_at`, catat aktivitas feed,
     * dan tambah penghitung postingan milik author.
     *
     * Idempoten — memanggil ulang pada postingan yang sudah terbit tidak
     * menggandakan baris feed maupun penghitung.
     */
    public function publish(): bool
    {
        if ($this->status === self::STATUS_PUBLISHED) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        FeedItem::query()->create([
            'actor_id' => $this->user_id,
            'subject_type' => self::class,
            'subject_id' => $this->id,
            'created_at' => $this->published_at,
        ]);

        User::query()->whereKey($this->user_id)->increment('posts_count');

        return true;
    }

    /**
     * Tarik postingan dari ruang publik: hapus jejak feed dan turunkan
     * penghitung postingan author. Aman dipanggil pada draft (tidak berefek).
     */
    public function withdrawFromFeed(): void
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return;
        }

        FeedItem::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->delete();

        User::query()
            ->whereKey($this->user_id)
            ->where('posts_count', '>', 0)
            ->decrement('posts_count');
    }
}
