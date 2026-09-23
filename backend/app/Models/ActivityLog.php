<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'product_id', 'claim_id', 'action', 'description', 'meta'])]
class ActivityLog extends Model
{
    public $timestamps = false;

    // ── Action constants ──────────────────────────────────────────────────────

    const PRODUCT_CREATED    = 'PRODUCT_CREATED';
    const PRODUCT_UPDATED    = 'PRODUCT_UPDATED';
    const MINE_CLAIMED       = 'MINE_CLAIMED';
    const STEAL_CLAIMED      = 'STEAL_CLAIMED';
    const GRAB_CLAIMED       = 'GRAB_CLAIMED';
    const CLAIM_ACTIVATED    = 'CLAIM_ACTIVATED';
    const CLAIM_EXPIRED      = 'CLAIM_EXPIRED';
    const CLAIM_OVERRIDDEN   = 'CLAIM_OVERRIDDEN';
    const PAYMENT_CONFIRMED  = 'PAYMENT_CONFIRMED';
    const PRODUCT_SOLD       = 'PRODUCT_SOLD';

    protected function casts(): array
    {
        return [
            'meta'       => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
