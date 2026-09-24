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
        // Lazy expiration: a deadline that has already passed must advance the
        // queue even when the scheduler (cron / schedule:work) is not running.
        $this->expireOverdueClaimsForProduct($product->id);

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

            // A queue only exists while somebody holds the product. When there is
            // no active claim at all, leftover WAITING mines are orphaned (they can
            // never be activated again because their predecessor is gone), so clear
            // them instead of forcing this claim to wait behind a dead queue.
            if (! $this->hasActiveClaim($product)) {
                $orphans = Claim::where('product_id', $product->id)
                    ->where('type', Claim::TYPE_MINE)
                    ->where('status', Claim::STATUS_WAITING)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if ($orphans) {
                    Claim::whereIn('id', $orphans)->update(['status' => Claim::STATUS_CANCELLED]);
                    $this->voidPendingOrders($orphans);
                }
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

            $isFirst  = $existingMineCount === 0;
            $position = $existingMineCount + 1;

            $claim = Claim::create(array_merge([
                'product_id' => $product->id,
                'user_id'    => $user->id,
                'type'       => Claim::TYPE_MINE,
                'position'   => $position,
                'amount'     => $product->mine_price,
            ], $isFirst ? $this->claimStage() : $this->waitingStage()));

            // If this is the first mine, set product to MINE_PENDING
            if ($isFirst) {
                $product->update(['status' => Product::STATUS_MINE_PENDING]);
            }

            // NOTE: deliberately NO order here. The claimant must first survive
            // the claim stage; the payment window (and its order) only opens when
            // that stage ends without a successful Steal.

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
        // Lazy expiration — an overdue Mine must advance before we decide
        // whether a Steal is even allowed on this product.
        $this->expireOverdueClaimsForProduct($product->id);

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

            $claim = Claim::create(array_merge([
                'product_id' => $product->id,
                'user_id'    => $user->id,
                'type'       => Claim::TYPE_STEAL,
                'position'   => $position,
                'amount'     => $product->steal_price,
            ], $isFirstSteal ? $this->claimStage() : $this->waitingStage()));

            // First steal: override all waiting/active Mine claims, switch product status
            if ($isFirstSteal) {
                // Collect the affected mines first so their pending orders can be
                // voided as well — an overridden claim must never be able to pay.
                $overriddenMineIds = Claim::where('product_id', $product->id)
                    ->where('type', Claim::TYPE_MINE)
                    ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                Claim::whereIn('id', $overriddenMineIds)->update(['status' => Claim::STATUS_OVERRIDDEN]);
                $this->voidPendingOrders($overriddenMineIds);

                $product->update(['status' => Product::STATUS_STEAL_PENDING]);

                // The stealer gets a NEW claim stage — never an instant payment
                // window (§8/§9 of the two-stage lifecycle).

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
        // Lazy expiration — an overdue claim must release the product before a
        // Grab is allowed.
        $this->expireOverdueClaimsForProduct($product->id);

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

            // Grab is the buy-now path: it skips the claim stage entirely and
            // opens the payment window immediately (no queue, no claim timer).
            $paymentExpiresAt = now()->addSeconds($this->paymentSeconds());

            $claim = Claim::create([
                'product_id'         => $product->id,
                'user_id'            => $user->id,
                'type'               => Claim::TYPE_GRAB,
                'position'           => 1,
                'status'             => Claim::STATUS_ACTIVE,
                'phase'              => Claim::PHASE_PAYMENT,
                'amount'             => $product->grab_price,
                'claim_expires_at'   => null,
                'payment_starts_at'  => now(),
                'payment_expires_at' => $paymentExpiresAt,
                'expires_at'         => $paymentExpiresAt,
            ]);

            $product->update(['status' => Product::STATUS_GRAB_PENDING]);

            $this->createOrder($claim, $product, $user, $paymentExpiresAt);

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
    // PAYMENT (simulated)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Simulate payment for an order.
     *
     * This is the ONLY place a sale is completed and it re-validates everything
     * server-side. A customer may only pay while they are the CURRENT ACTIVE
     * claimant — waiting, overridden, expired or cancelled claims can never pay,
     * no matter what the browser shows.
     *
     * @throws ClaimException
     */
    public function pay(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id) {
            throw new ClaimException('You can only pay for your own orders.', 'NOT_ORDER_OWNER');
        }

        // Process any deadline that already passed first, so the customer gets an
        // accurate answer and the queue keeps moving even without the scheduler.
        $this->expireOverdueClaimsForProduct($order->product_id);

        return DB::transaction(function () use ($order, $user) {
            // Lock order, product and claim before re-validating
            $order   = Order::lockForUpdate()->findOrFail($order->id);
            $product = Product::lockForUpdate()->findOrFail($order->product_id);
            $claim   = $order->claim_id
                ? Claim::lockForUpdate()->findOrFail($order->claim_id)
                : null;

            $this->assertOrderPayable($order, $claim, $product, $user);

            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'status'         => Order::STATUS_COMPLETED,
                'paid_at'        => now(),
            ]);

            if ($claim) {
                $claim->update(['status' => Claim::STATUS_COMPLETED]);

                // Every other claim on this product is now dead — including the
                // orders they were holding, so nobody else can pay afterwards.
                $otherClaimIds = Claim::where('product_id', $product->id)
                    ->where('id', '!=', $claim->id)
                    ->pluck('id')
                    ->all();

                Claim::whereIn('id', $otherClaimIds)
                    ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                    ->update(['status' => Claim::STATUS_CANCELLED]);

                $this->voidPendingOrders($otherClaimIds, $order->id);
            }

            $product->update(['status' => Product::STATUS_SOLD]);

            ActivityLog::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim?->id,
                'action'      => ActivityLog::PAYMENT_CONFIRMED,
                'description' => "{$user->name} paid for \"{$product->name}\" via " . strtoupper($order->claim_type),
            ]);

            ActivityLog::create([
                'user_id'     => $user->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim?->id,
                'action'      => ActivityLog::PRODUCT_SOLD,
                'description' => "\"{$product->name}\" has been sold to {$user->name}",
            ]);

            return $order->fresh()->load(['product', 'claim', 'user:id,name,email']);
        });
    }

    /**
     * Re-validate that this order may still be paid right now.
     * The frontend countdown is cosmetic — this method is the authority.
     *
     * @throws ClaimException
     */
    private function assertOrderPayable(Order $order, ?Claim $claim, Product $product, User $user): void
    {
        if ($order->payment_status === Order::PAYMENT_PAID) {
            throw new ClaimException('This order has already been paid.', 'ALREADY_PAID');
        }

        if (! $claim) {
            throw new ClaimException('This order is no longer linked to an active claim.', 'NO_CLAIM');
        }

        if ($claim->user_id !== $user->id) {
            throw new ClaimException('This claim does not belong to you.', 'NOT_CLAIM_OWNER');
        }

        // The claim must be the CURRENT active claim — a queued or overridden
        // claim can never pay, whatever the browser may still be showing.
        if ($claim->status !== Claim::STATUS_ACTIVE) {
            throw new ClaimException(match ($claim->status) {
                Claim::STATUS_WAITING    => 'You are still in the queue. Payment opens once your claim becomes active.',
                Claim::STATUS_OVERRIDDEN => 'Your claim was overridden by a Steal claim, so it can no longer be paid.',
                Claim::STATUS_EXPIRED    => 'Your claim has expired.',
                Claim::STATUS_CANCELLED  => 'This claim has been cancelled.',
                Claim::STATUS_COMPLETED  => 'This claim has already been completed.',
                default                  => 'This claim is no longer active.',
            }, 'CLAIM_NOT_ACTIVE');
        }

        // Two-stage lifecycle: paying is only possible once the CLAIM stage has
        // finished and the payment window is open. Never trust a frontend button.
        if ($claim->phase !== Claim::PHASE_PAYMENT) {
            throw new ClaimException(
                'Payment is not open yet — you must survive your claim period first.',
                'CLAIM_PERIOD_ACTIVE'
            );
        }

        if ($claim->payment_expires_at === null) {
            throw new ClaimException('This payment window has not started yet.', 'PAYMENT_NOT_STARTED');
        }

        if (now()->isAfter($claim->payment_expires_at)) {
            throw new ClaimException('This payment window has expired.', 'PAYMENT_WINDOW_EXPIRED');
        }

        if ($order->payment_status === Order::PAYMENT_EXPIRED) {
            throw new ClaimException('This payment window has expired.', 'PAYMENT_WINDOW_EXPIRED');
        }

        if ($order->payment_status === Order::PAYMENT_CANCELLED) {
            throw new ClaimException('This order is no longer available.', 'ORDER_CANCELLED');
        }

        if ($product->isSold()) {
            throw new ClaimException('This product has already been sold.', 'PRODUCT_SOLD');
        }

        if ($claim->isExpired()) {
            throw new ClaimException('Your claim has expired.', 'CLAIM_EXPIRED');
        }

        if ($order->expires_at && now()->isAfter($order->expires_at)) {
            throw new ClaimException('This payment window has expired.', 'PAYMENT_WINDOW_EXPIRED');
        }

        // The product must currently be held for exactly this kind of claim.
        $expectedStatus = match ($claim->type) {
            Claim::TYPE_MINE  => Product::STATUS_MINE_PENDING,
            Claim::TYPE_STEAL => Product::STATUS_STEAL_PENDING,
            Claim::TYPE_GRAB  => Product::STATUS_GRAB_PENDING,
            default           => null,
        };

        if ($expectedStatus === null || $product->status !== $expectedStatus) {
            throw new ClaimException('This action is no longer available.', 'CLAIM_SUPERSEDED');
        }
    }

    /**
     * Repair drift between a product's status and its claims so the UI never
     * shows a status that no claim backs (e.g. a queue left behind by a claim
     * that was already resolved).
     */
    public function reconcileProductStatus(Product $product): void
    {
        if ($product->isSold()) {
            return;
        }

        $active = Claim::where('product_id', $product->id)
            ->where('status', Claim::STATUS_ACTIVE)
            ->first();

        $expected = match ($active?->type) {
            Claim::TYPE_MINE  => Product::STATUS_MINE_PENDING,
            Claim::TYPE_STEAL => Product::STATUS_STEAL_PENDING,
            Claim::TYPE_GRAB  => Product::STATUS_GRAB_PENDING,
            default           => Product::STATUS_AVAILABLE,
        };

        // Nothing holds the product, so no queue may still be waiting on it.
        if ($active === null) {
            $orphans = Claim::where('product_id', $product->id)
                ->where('status', Claim::STATUS_WAITING)
                ->pluck('id')
                ->all();

            if ($orphans) {
                Claim::whereIn('id', $orphans)->update(['status' => Claim::STATUS_CANCELLED]);
                $this->voidPendingOrders($orphans);
            }
        }

        if ($product->status !== $expected) {
            $product->update(['status' => $expected]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // EXPIRE (used by scheduler AND force-expire admin action)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Finish the current stage of an active claim.
     *
     * Two-stage lifecycle — this single entry point (used by the scheduler AND
     * the admin Force Expire button) advances exactly one stage:
     *
     *   phase = CLAIM   → the claim stage is over, open the PAYMENT window for
     *                     the SAME claimant. The queue does NOT move.
     *   phase = PAYMENT → the payment window lapsed unpaid: expire the claim and
     *                     activate the next queued claimant with a NEW claim stage.
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

            // ── Stage 1: CLAIM period finished → open the payment window ──────
            if ($claim->phase === Claim::PHASE_CLAIM) {
                $this->openPaymentWindow($claim, $product);
                $claim->refresh();

                // If the whole payment window already elapsed while nobody was
                // watching (closed browser / no requests), fall through and
                // resolve it in this same pass.
                if (! $claim->isPaymentStageOver()) {
                    return;
                }
            }

            // ── Stage 2: PAYMENT window finished → expire + next claimant ─────
            $this->expirePaymentStage($claim, $product);
        });
    }

    /**
     * Open the payment window for the CURRENT claimant (claim stage → payment
     * stage).
     *
     * Idempotent: a claim that already owns a pending order never gets a second
     * payment window. The window is anchored to the moment the CLAIM stage
     * actually ended, so a claimant who was offline still receives exactly the
     * window the clock gave them (§28 — no duplicate/pushed-back deadlines).
     */
    private function openPaymentWindow(Claim $claim, Product $product): Order
    {
        $existing = Order::where('claim_id', $claim->id)
            ->where('payment_status', Order::PAYMENT_PENDING)
            ->first();

        if ($existing) {
            return $existing;
        }

        $paymentStartsAt = ($claim->claim_expires_at && $claim->claim_expires_at->lessThanOrEqualTo(now()))
            ? $claim->claim_expires_at
            : now();

        $paymentExpiresAt = $paymentStartsAt->copy()->addSeconds($this->paymentSeconds());

        $claim->update([
            'phase'              => Claim::PHASE_PAYMENT,
            'payment_starts_at'  => $paymentStartsAt,
            'payment_expires_at' => $paymentExpiresAt,
            'expires_at'         => $paymentExpiresAt,
        ]);

        $order = $this->createOrder($claim, $product, $claim->user, $paymentExpiresAt);

        ActivityLog::create([
            'user_id'     => $claim->user_id,
            'product_id'  => $product->id,
            'claim_id'    => $claim->id,
            'action'      => ActivityLog::CLAIM_EXPIRED,
            'description' => "{$claim->user->name}'s " . strtoupper($claim->type) . " claim period on \"{$product->name}\" ended — payment window opened",
        ]);

        ActivityLog::create([
            'user_id'     => $claim->user_id,
            'product_id'  => $product->id,
            'claim_id'    => $claim->id,
            'action'      => ActivityLog::CLAIM_ACTIVATED,
            'description' => "{$claim->user->name}'s " . strtoupper($claim->type) . " claim on \"{$product->name}\" entered the PAYMENT window",
        ]);

        return $order;
    }

    /**
     * The payment window lapsed unpaid: the claimant loses the item and the next
     * valid queued claimant takes over with a BRAND NEW claim stage.
     */
    private function expirePaymentStage(Claim $claim, Product $product): void
    {
        $claim->update(['status' => Claim::STATUS_EXPIRED]);

        // The unpaid order must not stay payable
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
            'description' => "{$claim->user->name}'s " . strtoupper($claim->type) . " payment window on \"{$product->name}\" expired",
        ]);

        $this->advanceQueue($product, $claim->type);
    }

    /**
     * Hand the product to the next valid queued claimant.
     *
     * The new claimant ALWAYS starts with a fresh CLAIM stage — never an
     * immediate payment window — and no order is created yet.
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
            $next->update($this->claimStage());

            ActivityLog::create([
                'user_id'     => $next->user_id,
                'product_id'  => $product->id,
                'claim_id'    => $next->id,
                'action'      => ActivityLog::CLAIM_ACTIVATED,
                'description' => "{$next->user->name}'s " . strtoupper($type) . " claim on \"{$product->name}\" is now ACTIVE (claim period)",
            ]);

            return;
        }

        // No next claimant — return product to appropriate status
        $newStatus = Product::STATUS_AVAILABLE;

        // Safety net: if stealing is queued but the active steal is gone, keep
        // the product in the steal stage so the queue can still be served.
        if ($type === Claim::TYPE_MINE) {
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

    /**
     * Check and expire all claims whose expires_at timestamp has passed.
     * Called by the scheduler every minute.
     */
    public function expireOverdueClaims(): int
    {
        return $this->expireClaims(
            Claim::where('status', Claim::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->pluck('id')
                ->all()
        );
    }

    /**
     * Lazily expire the overdue active claim of a single product.
     *
     * The scheduler only runs when `php artisan schedule:work` (or cron) is up.
     * On plain XAMPP it usually is not, so every customer facing entry point
     * flushes expired claims itself — otherwise a finished countdown would never
     * release the product and the next claimant would wait forever.
     */
    public function expireOverdueClaimsForProduct(int $productId): int
    {
        return $this->expireClaims(
            Claim::where('product_id', $productId)
                ->where('status', Claim::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->pluck('id')
                ->all()
        );
    }

    /**
     * Lazily expire overdue claims that sit in front of this customer's claims
     * (their own active claim, or the one blocking their queue position).
     */
    public function expireOverdueClaimsForUser(User $user): int
    {
        $productIds = Claim::where('user_id', $user->id)
            ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
            ->pluck('product_id')
            ->unique()
            ->all();

        if (! $productIds) {
            return 0;
        }

        return $this->expireClaims(
            Claim::whereIn('product_id', $productIds)
                ->where('status', Claim::STATUS_ACTIVE)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->pluck('id')
                ->all()
        );
    }

    /**
     * Run the standard expiration flow (expire + void order + advance queue)
     * for the given claim ids.
     */
    private function expireClaims(array $claimIds): int
    {
        $expired = 0;

        foreach ($claimIds as $claimId) {
            $claim = Claim::find($claimId);

            if (! $claim) {
                continue;
            }

            $this->expireClaim($claim);
            $expired++;
        }

        return $expired;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /** Does this product currently have an ACTIVE claim (mine / steal / grab)? */
    private function hasActiveClaim(Product $product): bool
    {
        return Claim::where('product_id', $product->id)
            ->where('status', Claim::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Void (cancel) every pending order still attached to the given claims.
     * Superseded claims must never keep a payable order behind them.
     */
    private function voidPendingOrders(array $claimIds, ?int $exceptOrderId = null): void
    {
        if (empty($claimIds)) {
            return;
        }

        $query = Order::whereIn('claim_id', $claimIds)
            ->where('payment_status', Order::PAYMENT_PENDING);

        if ($exceptOrderId) {
            $query->where('id', '!=', $exceptOrderId);
        }

        $query->update([
            'payment_status' => Order::PAYMENT_CANCELLED,
            'status'         => Order::STATUS_CANCELLED,
        ]);
    }

    /** Duration of the CLAIM stage (the claimant must survive this first). */
    private function claimSeconds(): int
    {
        return (int) config('app.claim_seconds', 60);
    }

    /** Duration of the PAYMENT window (opened after the claim stage). */
    private function paymentSeconds(): int
    {
        return (int) config('app.payment_seconds', 60);
    }

    /**
     * Attributes for a fresh CLAIM stage: ACTIVE, holding the item, payment
     * closed. `expires_at` mirrors the deadline of the current phase so existing
     * timers keep working.
     */
    private function claimStage(): array
    {
        $claimExpiresAt = now()->addSeconds($this->claimSeconds());

        return [
            'status'             => Claim::STATUS_ACTIVE,
            'phase'              => Claim::PHASE_CLAIM,
            'claim_expires_at'   => $claimExpiresAt,
            'payment_starts_at'  => null,
            'payment_expires_at' => null,
            'expires_at'         => $claimExpiresAt,
        ];
    }

    /** Attributes for a queued claimant: no phase, no deadline, not payable. */
    private function waitingStage(): array
    {
        return [
            'status'             => Claim::STATUS_WAITING,
            'phase'              => null,
            'claim_expires_at'   => null,
            'payment_starts_at'  => null,
            'payment_expires_at' => null,
            'expires_at'         => null,
        ];
    }

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
