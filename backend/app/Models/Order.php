<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_number', 'user_id', 'product_id', 'claim_id',
    'amount', 'claim_type', 'payment_status', 'status',
    'paid_at', 'expires_at',
])]
class Order extends Model
{
    use HasFactory;

    // ── Status constants ──────────────────────────────────────────────────────

    const PAYMENT_PENDING   = 'pending';
    const PAYMENT_PAID      = 'paid';
    const PAYMENT_EXPIRED   = 'expired';
    const PAYMENT_CANCELLED = 'cancelled';

    const STATUS_PENDING   = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'paid_at'    => 'datetime',
            'expires_at' => 'datetime',
            'amount'     => 'integer',
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
