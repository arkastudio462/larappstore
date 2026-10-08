<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'name', 'email', 'password', 'bio', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_USER = 'user';

    public const ROLE_DEVELOPER = 'developer';

    public const ROLE_ADMIN = 'admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    public function developerProfile(): HasOne
    {
        return $this->hasOne(DeveloperProfile::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'developer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ProductPurchase::class);
    }

    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    /**
     * Pengguna yang diikuti oleh pengguna ini.
     */
    public function following(): MorphToMany
    {
        return $this->morphToMany(User::class, 'followable', 'follows', 'follower_id', 'followable_id');
    }

    /**
     * Pengguna yang mengikuti pengguna ini.
     */
    public function followers(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'followable', 'follows', 'followable_id', 'follower_id');
    }

    /**
     * Menandai apakah penonton (user login) mengikuti pengguna ini.
     *
     * Dipakai bersama `withExists('followedByViewer as is_following')` sehingga
     * pengecekan follow tidak butuh query tambahan per baris hasil.
     */
    public function followedByViewer(): MorphOne
    {
        return $this->morphOne(Follow::class, 'followable')->where('follower_id', auth()->id());
    }

    public function feedItems(): HasMany
    {
        return $this->hasMany(FeedItem::class, 'actor_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Akun yang diblokir admin tidak bisa masuk atau memakai API (PRD §3).
     */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isDeveloper(): bool
    {
        return $this->role === self::ROLE_DEVELOPER || $this->isAdmin();
    }

    public function hasUnlimitedUploads(): bool
    {
        return $this->developerProfile?->unlimited_uploads ?? false;
    }

    public function uploadCredits(): int
    {
        return $this->developerProfile?->upload_credits ?? 0;
    }

    /**
     * Konsumsi satu kredit upload.
     *
     * Pengurangan dilakukan sebagai satu statement atomik yang hanya cocok
     * selama saldo masih positif, sehingga permintaan serentak tidak bisa
     * menghabiskan kredit berlebih. Method ini mengembalikan false bila
     * kredit habis agar pemanggil bisa meminta pembelian slot lebih dulu.
     */
    public function consumeUploadCredit(): bool
    {
        if ($this->hasUnlimitedUploads()) {
            return true;
        }

        $affected = DeveloperProfile::query()
            ->where('user_id', $this->id)
            ->where('upload_credits', '>', 0)
            ->decrement('upload_credits');

        return $affected > 0;
    }
}
