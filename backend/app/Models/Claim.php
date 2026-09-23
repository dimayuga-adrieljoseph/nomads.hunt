<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'product_id', 'user_id', 'type', 'position', 'status', 'amount', 'expires_at',
])]
class Claim extends Model
{
    use HasFactory;

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_WAITING    = 'waiting';
    const STATUS_ACTIVE     = 'active';
    const STATUS_EXPIRED    = 'expired';
    const STATUS_COMPLETED  = 'completed';
    const STATUS_OVERRIDDEN = 'overridden';
    const STATUS_CANCELLED  = 'cancelled';

    // ── Type constants ────────────────────────────────────────────────────────

    const TYPE_MINE  = 'mine';
    const TYPE_STEAL = 'steal';
    const TYPE_GRAB  = 'grab';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'amount'     => 'integer',
            'position'   => 'integer',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return now()->isAfter($this->expires_at);
    }

    public function isStillValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }
}
