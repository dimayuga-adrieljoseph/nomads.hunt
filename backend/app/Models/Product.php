<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name', 'description', 'category', 'brand', 'size', 'condition',
    // Transitional rollback mirror; product_images is the runtime source of truth.
    'image', 'mine_price', 'steal_price', 'grab_price', 'status',
])]
class Product extends Model
{
    use HasFactory;

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_AVAILABLE = 'available';

    const STATUS_MINE_PENDING = 'mine_pending';

    const STATUS_STEAL_PENDING = 'steal_pending';

    const STATUS_GRAB_PENDING = 'grab_pending';

    const STATUS_SOLD = 'sold';

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isSold(): bool
    {
        return $this->status === self::STATUS_SOLD;
    }

    public function hasActiveSteal(): bool
    {
        return $this->status === self::STATUS_STEAL_PENDING;
    }

    public function hasActiveMine(): bool
    {
        return $this->status === self::STATUS_MINE_PENDING;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ProductLike::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->where('is_primary', true)
            ->orderByDesc('sort_order')
            ->orderByDesc('id');
    }

    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_likes')->withTimestamps();
    }

    public function primaryImageUrl(): ?string
    {
        $image = match (true) {
            $this->relationLoaded('primaryImage') => $this->primaryImage,
            $this->relationLoaded('images') => $this->images
                ->firstWhere('is_primary', true) ?? $this->images->first(),
            default => $this->primaryImage,
        };

        return $image?->url;
    }

    // ── Scoped queries ────────────────────────────────────────────────────────

    public function activeClaim(): ?Claim
    {
        return $this->claims()
            ->whereIn('type', ['mine', 'steal', 'grab'])
            ->where('status', 'active')
            ->first();
    }

    public function activeMineClaim(): ?Claim
    {
        return $this->claims()
            ->where('type', 'mine')
            ->where('status', 'active')
            ->first();
    }

    public function activeStealClaim(): ?Claim
    {
        return $this->claims()
            ->where('type', 'steal')
            ->where('status', 'active')
            ->first();
    }
}
