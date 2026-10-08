<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'developer_id',
    'category_id',
    'type',
    'title',
    'slug',
    'summary',
    'description',
    'icon_path',
    'banner_path',
    'price',
    'currency',
    'status',
    'is_featured',
    'published_at',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const TYPES = ['aplikasi', 'game', 'software', 'file', 'lainnya'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'downloads_count' => 'integer',
            'description' => 'string',
        ];
    }

    /**
     * Produk dialamatkan lewat slug di URL (`/produk/{slug}`).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function screenshots(): HasMany
    {
        return $this->hasMany(ProductScreenshot::class)->orderBy('position');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class)->latest('id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ProductVersion::class)->where('is_latest', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(DownloadStatsDaily::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_items')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeFree(Builder $query): Builder
    {
        return $query->where('price', 0);
    }

    public function isFree(): bool
    {
        return $this->price === 0;
    }

    /**
     * Terbitkan produk: set status + `published_at`, lalu catat ke feed
     * **hanya sekali** saat pertama kali terbit (PRD §4.1).
     *
     * Idempoten — memanggil ulang pada produk yang sudah terbit tidak
     * menggandakan baris feed.
     */
    public function publish(): bool
    {
        if ($this->status === self::STATUS_PUBLISHED) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $this->published_at ?? now(),
        ])->save();

        $alreadyInFeed = FeedItem::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->exists();

        if (! $alreadyInFeed) {
            FeedItem::query()->create([
                'actor_id' => $this->developer_id,
                'subject_type' => self::class,
                'subject_id' => $this->id,
                'created_at' => $this->published_at,
            ]);
        }

        return true;
    }

    /**
     * Arsipkan produk dan tarik seluruh jejaknya dari feed: baris produk
     * maupun rilis versinya.
     */
    public function archive(): void
    {
        $this->forceFill(['status' => self::STATUS_ARCHIVED])->save();

        FeedItem::query()
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->delete();

        FeedItem::query()
            ->where('subject_type', ProductVersion::class)
            ->whereIn('subject_id', $this->versions()->select('id'))
            ->delete();
    }
}
