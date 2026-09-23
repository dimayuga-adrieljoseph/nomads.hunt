<?php

namespace App\Services;

use App\Exceptions\ClaimException;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClaimService
{
    // ──────────────────────────────────────────────────────────────────────────
    // MINE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Place a Mine claim on a product.
     *
     * Rules:
     * - Product must not be SOLD or GRAB_PENDING.
     * - Mine is unavailable once a Steal is active (STEAL_PENDING).
     * - A user cannot have more than one active/waiting Mine on the same product.
     * - The first Mine becomes ACTIVE; subsequent ones WAIT.
     */
    public function mine(Product $product, User $user): Claim
    {
        return DB::transaction(function () use ($product, $user) {
            // Lock the product row to prevent concurrent Mine/Steal/Grab races
            $product = Product::lockForUpdate()->findOrFail($product->id);

            if ($product->isSold()) {
                throw new ClaimException(
                    'This product has already been sold.',
                    'PRODUCT_SOLD'
                );
            }

            if ($product->status === Product::STATUS_GRAB_PENDING) {
                throw new ClaimException(
                    'A Grab claim is in progress. Mine is not available.',
                    'GRAB_ACTIVE'
                );
            }

            if ($product->hasActiveSteal()) {
                throw new ClaimException(
                    'Mine is no longer available because a Steal claim is active.',
                    'STEAL_ACTIVE'
                );
            }

            // Check if this user already has an active/waiting mine on this product
            $existing = Claim::where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->where('type', Claim::TYPE_MINE)
                ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new ClaimException(
                    'You are already in the Mine queue for this product.',
                    'ALREADY_IN_QUEUE'
                );
            }

            // Count how many active/waiting mines exist
            $existingMineCount = Claim::where('product_id', $product->id)
                ->where('type', Claim::TYPE_MINE)
                ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                ->lockForUpdate()
                ->count();

            $isFirst = $existingMineCount === 0;
            $position = $existingMineCount + 1;

            $holdSeconds = (int) config('app.claim_hold_seconds', 300);
            $expiresAt   = $isFirst ? now()->addSeconds($holdSeconds) : null;

            $claim = Claim::create([
                'product_id' => $product->id,
                'user_id'    => $user->id,
                'type'       => Claim::TYPE_MINE,
                'position'   => $position,
                'status'     => $isFirst ? Claim::STATUS_ACTIVE : Claim::STATUS_WAITING,
                'amount'     => $product->mine_price,
                'expires_at' => $expiresAt,
            ]);

            // If this is the first mine, set product to MINE_PENDING
            if ($isFirst) {
                $product->update(['status' => Product::STATUS_MINE_PENDING]);
            }

            // Create order record for the active claimant
            if ($isFirst) {
                $this->createOrder($claim, $product, $user, $expiresAt);
            }

            ActivityLog::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim->id,
                'action'      => ActivityLog::MINE_CLAIMED,
                'description' => "{$user->name} placed a Mine claim on \"{$product->name}\" (position #{$position})",
            ]);

            if ($isFirst) {
                ActivityLog::create([
                    'user_id'     => $user->id,
                    'product_id'  => $product->id,
                    'claim_id'    => $claim->id,
                    'action'      => ActivityLog::CLAIM_ACTIVATED,
                    'description' => "{$user->name}'s Mine claim on \"{$product->name}\" is now ACTIVE",
                ]);
            }

            return $claim->fresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // STEAL
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Place a Steal claim on a product.
     *
     * Rules:
     * - Product must be MINE_PENDING or STEAL_PENDING (active mine OR existing steal queue).
     * - A user cannot have more than one active/waiting Steal on the same product.
     * - The first Steal overrides the current Mine; subsequent Steals join the Steal queue.
     */
    public function steal(Product $product, User $user): Claim
    {
        return DB::transaction(function () use ($product, $user) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            if ($product->isSold()) {
                throw new ClaimException(
                    'This product has already been sold.',
                    'PRODUCT_SOLD'
                );
            }

            if ($product->isAvailable()) {
                throw new ClaimException(
                    'Steal is only available when a Mine claim is active.',
                    'NO_MINE_ACTIVE'
                );
            }

            if ($product->status === Product::STATUS_GRAB_PENDING) {
                throw new ClaimException(
                    'A Grab claim is in progress.',
                    'GRAB_ACTIVE'
                );
            }

            // User already in steal queue
            $existing = Claim::where('product_id', $product->id)
                ->where('user_id', $user->id)
                ->where('type', Claim::TYPE_STEAL)
                ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new ClaimException(
                    'You are already in the Steal queue for this product.',
                    'ALREADY_IN_QUEUE'
                );
            }

            // Count existing steal queue
            $existingStealCount = Claim::where('product_id', $product->id)
                ->where('type', Claim::TYPE_STEAL)
                ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                ->lockForUpdate()
                ->count();

            $isFirstSteal = $existingStealCount === 0;
            $position     = $existingStealCount + 1;

            $holdSeconds = (int) config('app.claim_hold_seconds', 300);
            $expiresAt   = $isFirstSteal ? now()->addSeconds($holdSeconds) : null;

            $claim = Claim::create([
                'product_id' => $product->id,
                'user_id'    => $user->id,
                'type'       => Claim::TYPE_STEAL,
                'position'   => $position,
                'status'     => $isFirstSteal ? Claim::STATUS_ACTIVE : Claim::STATUS_WAITING,
                'amount'     => $product->steal_price,
                'expires_at' => $expiresAt,
            ]);

            // First steal: override all waiting/active Mine claims, switch product status
            if ($isFirstSteal) {
                Claim::where('product_id', $product->id)
                    ->where('type', Claim::TYPE_MINE)
                    ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                    ->update(['status' => Claim::STATUS_OVERRIDDEN]);

                $product->update(['status' => Product::STATUS_STEAL_PENDING]);

                $this->createOrder($claim, $product, $user, $expiresAt);

                ActivityLog::create([
                    'user_id'     => $user->id,
                    'product_id'  => $product->id,
                    'claim_id'    => $claim->id,
                    'action'      => ActivityLog::CLAIM_OVERRIDDEN,
                    'description' => "All Mine claims on \"{$product->name}\" were overridden by a Steal",
                ]);
            }

            ActivityLog::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim->id,
                'action'      => ActivityLog::STEAL_CLAIMED,
                'description' => "{$user->name} placed a Steal claim on \"{$product->name}\" (position #{$position})",
            ]);

            if ($isFirstSteal) {
                ActivityLog::create([
                    'user_id'     => $user->id,
                    'product_id'  => $product->id,
                    'claim_id'    => $claim->id,
                    'action'      => ActivityLog::CLAIM_ACTIVATED,
                    'description' => "{$user->name}'s Steal claim on \"{$product->name}\" is now ACTIVE",
                ]);
            }

            return $claim->fresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GRAB
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Place a Grab (buy-now) claim on a product.
     *
     * Rules:
     * - Product must be AVAILABLE (no mine/steal in progress).
     * - No queue — creates a single ACTIVE claim and an order immediately.
     * - If payment expires, product reverts to AVAILABLE.
     */
    public function grab(Product $product, User $user): Claim
    {
        return DB::transaction(function () use ($product, $user) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            if ($product->isSold()) {
                throw new ClaimException(
                    'This product has already been sold.',
                    'PRODUCT_SOLD'
                );
            }

            if (! $product->isAvailable()) {
                throw new ClaimException(
                    'Grab is only available for products with no active claims.',
                    'PRODUCT_NOT_AVAILABLE'
                );
            }

            $holdSeconds = (int) config('app.claim_hold_seconds', 300);
            $expiresAt   = now()->addSeconds($holdSeconds);

            $claim = Claim::create([
                'product_id' => $product->id,
                'user_id'    => $user->id,
                'type'       => Claim::TYPE_GRAB,
                'position'   => 1,
                'status'     => Claim::STATUS_ACTIVE,
                'amount'     => $product->grab_price,
                'expires_at' => $expiresAt,
            ]);

            $product->update(['status' => Product::STATUS_GRAB_PENDING]);

            $this->createOrder($claim, $product, $user, $expiresAt);

            ActivityLog::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim->id,
                'action'      => ActivityLog::GRAB_CLAIMED,
                'description' => "{$user->name} placed a Grab claim on \"{$product->name}\"",
            ]);

            return $claim->fresh();
        });
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EXPIRE (used by scheduler AND force-expire admin action)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Expire a specific active claim and advance the queue.
     * This is called both by the scheduler and by the admin Force Expire action.
     */
    public function expireClaim(Claim $claim): void
    {
        DB::transaction(function () use ($claim) {
            // Re-load with lock to avoid stale data
            $claim = Claim::lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== Claim::STATUS_ACTIVE) {
                return; // Nothing to do
            }

            $product = Product::lockForUpdate()->findOrFail($claim->product_id);

            $claim->update(['status' => Claim::STATUS_EXPIRED]);

            // Cancel the pending order tied to this claim
            Order::where('claim_id', $claim->id)
                ->where('payment_status', Order::PAYMENT_PENDING)
                ->update([
                    'payment_status' => Order::PAYMENT_EXPIRED,
                    'status'         => Order::STATUS_CANCELLED,
                ]);

            ActivityLog::create([
                'user_id'     => $claim->user_id,
                'product_id'  => $product->id,
                'claim_id'    => $claim->id,
                'action'      => ActivityLog::CLAIM_EXPIRED,
                'description' => "{$claim->user->name}'s " . strtoupper($claim->type) . " claim on \"{$product->name}\" expired",
            ]);

            // Advance the queue
            $this->advanceQueue($product, $claim->type);
        });
    }

    /**
     * Advance the queue for a given product + claim type after an expiration.
     */
    public function advanceQueue(Product $product, string $type): void
    {
        $next = Claim::where('product_id', $product->id)
            ->where('type', $type)
            ->where('status', Claim::STATUS_WAITING)
            ->orderBy('position')
            ->lockForUpdate()
            ->first();

        if ($next) {
            $holdSeconds = (int) config('app.claim_hold_seconds', 300);
            $expiresAt   = now()->addSeconds($holdSeconds);

            $next->update([
                'status'     => Claim::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);

            // Create an order for the newly activated claimant
            $this->createOrder($next, $product, $next->user, $expiresAt);

            ActivityLog::create([
                'user_id'     => $next->user_id,
                'product_id'  => $product->id,
                'claim_id'    => $next->id,
                'action'      => ActivityLog::CLAIM_ACTIVATED,
                'description' => "{$next->user->name}'s " . strtoupper($type) . " claim on \"{$product->name}\" is now ACTIVE",
            ]);
        } else {
            // No next claimant — return product to appropriate status
            $newStatus = Product::STATUS_AVAILABLE;

            // If there are still waiting steals but we expired the active steal,
            // this branch shouldn't happen since we checked above. But as a safety net:
            if ($type === Claim::TYPE_MINE) {
                // After mine queue empties, check if there are still steals
                $stealsWaiting = Claim::where('product_id', $product->id)
                    ->where('type', Claim::TYPE_STEAL)
                    ->where('status', Claim::STATUS_WAITING)
                    ->exists();
                $newStatus = $stealsWaiting
                    ? Product::STATUS_STEAL_PENDING
                    : Product::STATUS_AVAILABLE;
            }

            $product->update(['status' => $newStatus]);
        }
    }

    /**
     * Check and expire all claims whose expires_at timestamp has passed.
     * Called by the scheduler every minute.
     */
    public function expireOverdueClaims(): int
    {
        $overdue = Claim::where('status', Claim::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($overdue as $claim) {
            $this->expireClaim($claim);
        }

        return $overdue->count();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function createOrder(Claim $claim, Product $product, User $user, ?\DateTimeInterface $expiresAt): Order
    {
        return Order::create([
            'order_number'   => $this->generateOrderNumber(),
            'user_id'        => $user->id,
            'product_id'     => $product->id,
            'claim_id'       => $claim->id,
            'amount'         => $claim->amount,
            'claim_type'     => $claim->type,
            'payment_status' => Order::PAYMENT_PENDING,
            'status'         => Order::STATUS_PENDING,
            'expires_at'     => $expiresAt,
        ]);
    }

    private function generateOrderNumber(): string
    {
        $last = Order::max('id') ?? 0;
        return str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
