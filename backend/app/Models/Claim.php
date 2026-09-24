<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'product_id', 'user_id', 'type', 'position', 'status', 'amount', 'expires_at',
    'phase', 'claim_expires_at', 'payment_starts_at', 'payment_expires_at',
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

    // ── Phase constants ───────────────────────────────────────────────────────
    // An ACTIVE claim is always in exactly one of two stages:
    //   CLAIM   → holds the item, others may Steal, payment NOT allowed yet
    //   PAYMENT → claim stage finished, only now may the claimant pay

    const PHASE_CLAIM   = 'claim';
    const PHASE_PAYMENT = 'payment';

    // ── Type constants ────────────────────────────────────────────────────────

    const TYPE_MINE  = 'mine';
    const TYPE_STEAL = 'steal';
    const TYPE_GRAB  = 'grab';

    protected function casts(): array
    {
        return [
            'expires_at'         => 'datetime',
            'claim_expires_at'   => 'datetime',
            'payment_starts_at'  => 'datetime',
            'payment_expires_at' => 'datetime',
            'amount'             => 'integer',
            'position'           => 'integer',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** ACTIVE + still inside the claim stage (payment not open yet) */
    public function isClaimPhase(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->phase === self::PHASE_CLAIM;
    }

    /** ACTIVE + claim stage finished: the payment window is open */
    public function isPaymentPhase(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->phase === self::PHASE_PAYMENT;
    }

    /**
     * `expires_at` always mirrors the deadline of the CURRENT phase:
     * the claim deadline while phase = CLAIM, the payment deadline while
     * phase = PAYMENT.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return now()->isAfter($this->expires_at);
    }

    /** Has the claim stage run out (so the payment window must open)? */
    public function isClaimStageOver(): bool
    {
        return $this->isClaimPhase()
            && $this->claim_expires_at !== null
            && now()->isAfter($this->claim_expires_at);
    }

    /** Has the payment window run out unanswered? */
    public function isPaymentStageOver(): bool
    {
        return $this->isPaymentPhase()
            && $this->payment_expires_at !== null
            && now()->isAfter($this->payment_expires_at);
    }

    /** Server-side truth for "show an enabled Pay button" */
    public function canPay(): bool
    {
        return $this->isPaymentPhase() && ! $this->isPaymentStageOver();
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
