<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;

#[Fillable([
    'title', 'label', 'description', 'event_date', 'event_time', 'location',
    'image', 'status', 'priority', 'starts_at', 'ends_at',
])]
class Announcement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const DISPLAY_ACTIVE = 'ACTIVE';

    public const DISPLAY_SCHEDULED = 'SCHEDULED';

    public const DISPLAY_EXPIRED = 'EXPIRED';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeEligible(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= Carbon::now();

        return $query->published()
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeActive(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query->eligible($now)
            ->orderByDesc('priority')
            ->orderByDesc('starts_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    public function scopeScheduled(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= Carbon::now();

        return $query->published()
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now);
    }

    public function scopeExpired(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= Carbon::now();

        return $query->published()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now);
    }

    public function isEligible(?CarbonInterface $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->status === self::STATUS_PUBLISHED
            && (! $this->starts_at || $this->starts_at->lessThanOrEqualTo($now))
            && (! $this->ends_at || $this->ends_at->greaterThanOrEqualTo($now));
    }

    public function displayStatus(?CarbonInterface $now = null): string
    {
        $now ??= Carbon::now();

        if ($this->status === self::STATUS_DRAFT) {
            return self::STATUS_DRAFT;
        }

        if ($this->status === self::STATUS_ARCHIVED) {
            return self::STATUS_ARCHIVED;
        }

        if ($this->starts_at && $this->starts_at->greaterThan($now)) {
            return self::DISPLAY_SCHEDULED;
        }

        if ($this->ends_at && $this->ends_at->lessThan($now)) {
            return self::DISPLAY_EXPIRED;
        }

        return self::DISPLAY_ACTIVE;
    }
}
