<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Two-stage claim lifecycle:
 *
 *   CLAIM stage (hold + others may Steal)
 *        ↓ claim_expires_at
 *   PAYMENT window (same claimant may now pay)
 *        ↓ payment_expires_at unpaid
 *   next queued claimant → NEW CLAIM STAGE (never an instant payment window)
 *
 * A claim expiration is NOT a failure — it only means the claimant survived and
 * is now allowed to pay.
 */
class ClaimLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ClaimService $claimService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claimService = app(ClaimService::class);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name'        => 'Test Product',
            'condition'   => 'good',
            'mine_price'  => 5000,
            'steal_price' => 5300,
            'grab_price'  => 5600,
            'status'      => 'available',
        ], $overrides));
    }

    private function makeCustomer(string $email): User
    {
        return User::create([
            'name'     => 'Customer',
            'email'    => $email,
            'password' => bcrypt('password'),
            'role'     => 'customer',
        ]);
    }

    /** Deterministic stage durations for the timeline tests. */
    private function useStageDurations(int $claimSeconds, int $paymentSeconds): void
    {
        config(['app.claim_seconds' => $claimSeconds, 'app.payment_seconds' => $paymentSeconds]);
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time));
    }

    /** Run the backend stage machine for this product (what a timeout would do). */
    private function tick(Product $product): void
    {
        $this->claimService->expireOverdueClaimsForProduct($product->id);
    }

    // ── §22 exact timeline: MINE → claim → payment → next claimant ────────────

    public function test_spec_timeline_claim_payment_then_next_claimant(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');
        $c = $this->makeCustomer('c@test.com');

        // 10:00 — A mines. A holds the item but CANNOT pay yet.
        $this->at('2026-01-01 10:00:00');
        $claimA = $this->claimService->mine($product, $a);

        $this->assertEquals(Claim::PHASE_CLAIM, $claimA->phase);
        $this->assertEquals('2026-01-01 10:01:00', $claimA->claim_expires_at->toDateTimeString());
        $this->assertNull($claimA->payment_expires_at);
        $this->assertNull($claimA->order);
        $this->assertFalse($claimA->canPay());

        // 10:00:20 — B mines and is queued behind A
        $this->at('2026-01-01 10:00:20');
        $claimB = $this->claimService->mine($product, $b);
        $this->assertEquals(Claim::STATUS_WAITING, $claimB->status);
        $this->assertEquals(2, $claimB->position);

        // 10:01 — A's claim stage ends. A enters PAYMENT. B must NOT move.
        $this->at('2026-01-01 10:01:00');
        $this->tick($product);

        $claimA->refresh();
        $this->assertEquals(Claim::STATUS_ACTIVE, $claimA->status);
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimA->phase);
        $this->assertEquals('2026-01-01 10:01:00', $claimA->payment_starts_at->toDateTimeString());
        $this->assertEquals('2026-01-01 10:02:00', $claimA->payment_expires_at->toDateTimeString());
        $this->assertNotNull($claimA->order);
        $this->assertEquals(Claim::STATUS_WAITING, $claimB->fresh()->status);

        // 10:01:30 — A still hasn't paid; B still waits. A owns the window.
        $this->at('2026-01-01 10:01:30');
        $this->tick($product);
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimA->fresh()->phase);
        $this->assertEquals(Claim::STATUS_WAITING, $claimB->fresh()->status);

        // 10:02 — A's payment lapses. NOW B becomes active with a NEW claim stage.
        $this->at('2026-01-01 10:02:00');
        $this->tick($product);

        $this->assertEquals(Claim::STATUS_EXPIRED, $claimA->fresh()->status);
        $this->assertEquals(Order::PAYMENT_EXPIRED, $claimA->order->fresh()->payment_status);

        $claimB->refresh();
        $this->assertEquals(Claim::STATUS_ACTIVE, $claimB->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $claimB->phase);
        $this->assertEquals('2026-01-01 10:03:00', $claimB->claim_expires_at->toDateTimeString());
        $this->assertNull($claimB->order);                       // no instant payment
        $this->assertFalse($claimB->canPay());

        // 10:03 — B's claim stage ends → B enters PAYMENT (not C)
        $this->at('2026-01-01 10:03:00');
        $this->tick($product);
        $claimB->refresh();
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimB->phase);
        $this->assertEquals('2026-01-01 10:04:00', $claimB->payment_expires_at->toDateTimeString());
        $this->assertTrue($claimB->canPay());

        // 10:04 — B fails to pay → no more claimants, product returns to AVAILABLE
        $this->at('2026-01-01 10:04:00');
        $this->tick($product);

        $this->assertEquals(Claim::STATUS_EXPIRED, $claimB->fresh()->status);
        $this->assertEquals(Product::STATUS_AVAILABLE, $product->fresh()->status);
        $this->assertEquals(Order::PAYMENT_EXPIRED, $claimB->order->fresh()->payment_status);
    }

    // ── §7 / §20: every queued user gets their own claim period, in order ─────

    public function test_three_user_chain_rotates_in_order(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');
        $c = $this->makeCustomer('c@test.com');

        $this->at('2026-01-01 10:00:00');
        $claimA = $this->claimService->mine($product, $a);
        $claimB = $this->claimService->mine($product, $b);
        $claimC = $this->claimService->mine($product, $c);

        $this->assertEquals([1, 2, 3], [
            $claimA->position, $claimB->position, $claimC->position,
        ], 'queue order must be preserved');

        // A → claim ends → payment → lapses → B active with a new claim stage
        $this->at('2026-01-01 10:01:00');
        $this->tick($product);
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimA->fresh()->phase);

        $this->at('2026-01-01 10:02:00');
        $this->tick($product);
        $this->assertEquals(Claim::STATUS_EXPIRED, $claimA->fresh()->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $claimB->fresh()->phase);
        $this->assertEquals(Claim::STATUS_WAITING, $claimC->fresh()->status);

        // B → claim ends → payment → lapses → C active with a new claim stage
        $this->at('2026-01-01 10:03:00');
        $this->tick($product);
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimB->fresh()->phase);

        $this->at('2026-01-01 10:04:00');
        $this->tick($product);
        $this->assertEquals(Claim::STATUS_EXPIRED, $claimB->fresh()->status);

        $claimC->refresh();
        $this->assertEquals(Claim::STATUS_ACTIVE, $claimC->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $claimC->phase);
        $this->assertNull($claimC->order);

        // C eventually pays inside its own payment window
        $this->at('2026-01-01 10:05:00');
        $this->tick($product);
        $this->assertTrue($claimC->fresh()->canPay());

        $this->actingAs($c, 'sanctum')
            ->postJson("/api/orders/{$claimC->fresh()->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals(Claim::STATUS_COMPLETED, $claimC->fresh()->status);
        // ...and the earlier claims keep their history
        $this->assertEquals(Claim::STATUS_EXPIRED, $claimA->fresh()->status);
        $this->assertEquals(Claim::STATUS_EXPIRED, $claimB->fresh()->status);
    }

    // ── Claim stage → payment window (Tests 1 & 2) ────────────────────────────

    public function test_claim_expiry_opens_payment_and_payment_sells(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $this->at('2026-01-01 10:00:00');
        $claim = $this->claimService->mine($product, $a);

        // Paying before the claim stage ends is impossible (no order exists)
        $this->assertNull($claim->order);

        $this->at('2026-01-01 10:01:00');
        $this->tick($product);
        $claim->refresh();

        $this->assertEquals(Claim::PHASE_PAYMENT, $claim->phase);
        $this->assertTrue($claim->canPay());

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$claim->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals(Claim::STATUS_COMPLETED, $claim->fresh()->status);
        $this->assertEquals(Product::STATUS_SOLD, $product->fresh()->status);
    }

    // ── §23 Steal timeline ────────────────────────────────────────────────────

    public function test_steal_gets_own_claim_period_then_payment(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        // 10:00 — A mines, claim expires 10:01
        $this->at('2026-01-01 10:00:00');
        $mineA = $this->claimService->mine($product, $a);

        // 10:00:20 — B steals during A's claim period
        $this->at('2026-01-01 10:00:20');
        $stealB = $this->claimService->steal($product, $b);

        $this->assertEquals(Claim::STATUS_OVERRIDDEN, $mineA->fresh()->status);
        $this->assertEquals(Claim::STATUS_ACTIVE, $stealB->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $stealB->phase);
        $this->assertEquals('2026-01-01 10:01:20', $stealB->claim_expires_at->toDateTimeString());
        // §9 — a Steal must NOT open a payment window immediately
        $this->assertNull($stealB->order);
        $this->assertNull($stealB->payment_expires_at);
        $this->assertFalse($stealB->canPay());

        // 10:01:20 — B's claim period ends → B's payment window opens
        $this->at('2026-01-01 10:01:20');
        $this->tick($product);
        $stealB->refresh();

        $this->assertEquals(Claim::PHASE_PAYMENT, $stealB->phase);
        $this->assertEquals('2026-01-01 10:02:20', $stealB->payment_expires_at->toDateTimeString());
        $this->assertEquals('pending', $stealB->order->payment_status);

        // B pays and wins the piece
        $this->actingAs($b, 'sanctum')
            ->postJson("/api/orders/{$stealB->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals(Claim::STATUS_COMPLETED, $stealB->fresh()->status);
    }

    // ── §11/Test 7: steal payment failure → next stealer gets a claim period ──

    public function test_steal_payment_failure_gives_next_stealer_a_claim_period(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $miner = $this->makeCustomer('m@test.com');
        $b = $this->makeCustomer('b@test.com');
        $c = $this->makeCustomer('c@test.com');

        $this->at('2026-01-01 10:00:00');
        $this->claimService->mine($product, $miner);
        $stealB = $this->claimService->steal($product, $b);
        $stealC = $this->claimService->steal($product, $c);

        $this->assertEquals(Claim::STATUS_WAITING, $stealC->status);

        // B's claim stage → payment window
        $this->at('2026-01-01 10:01:00');
        $this->tick($product);
        $this->assertEquals(Claim::PHASE_PAYMENT, $stealB->fresh()->phase);

        // B fails to pay → C takes over with a NEW claim stage
        $this->at('2026-01-01 10:02:00');
        $this->tick($product);

        $this->assertEquals(Claim::STATUS_EXPIRED, $stealB->fresh()->status);
        $stealC->refresh();
        $this->assertEquals(Claim::STATUS_ACTIVE, $stealC->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $stealC->phase);
        $this->assertNotNull($stealC->claim_expires_at);
        $this->assertNull($stealC->order);
        $this->assertFalse($stealC->canPay());
    }

    // ── §13/§32 (Test 8): Grab skips the claim stage entirely ────────────────

    public function test_grab_skips_claim_stage_and_goes_straight_to_payment(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        $this->at('2026-01-01 10:00:00');
        $this->claimService->mine($product, $a);

        // Grab needs an AVAILABLE product, so free it first (payment lapses)
        $this->at('2026-01-01 10:01:00');
        $this->tick($product);
        $this->at('2026-01-01 10:02:00');
        $this->tick($product);
        $this->assertEquals(Product::STATUS_AVAILABLE, $product->fresh()->status);

        $grab = $this->claimService->grab($product, $b);

        $this->assertEquals(Claim::PHASE_PAYMENT, $grab->phase);
        $this->assertNull($grab->claim_expires_at);
        $this->assertNotNull($grab->payment_expires_at);
        $this->assertTrue($grab->canPay());
        $this->assertEquals(0, Claim::where('product_id', $product->id)->where('status', Claim::STATUS_WAITING)->count());

        $this->actingAs($b, 'sanctum')
            ->postJson("/api/orders/{$grab->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');
    }

    // ── §33 Tests 9 & 10: browser refresh / close must not break state ───────

    public function test_state_survives_no_requests_then_recovery(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        $this->at('2026-01-01 10:00:00');
        $claimA = $this->claimService->mine($product, $a);
        $this->claimService->mine($product, $b);

        // Nobody touches the app for 5 minutes (browser closed). The very first
        // read afterwards must resolve BOTH of A's stages in one pass.
        $this->at('2026-01-01 10:05:00');
        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json('data');

        $this->assertEquals(Claim::STATUS_EXPIRED, $claimA->fresh()->status);

        // A's full lifecycle is done, so B now holds a BRAND NEW claim stage
        // (§30) — it is not an instant payment window.
        $claimB = Claim::where('product_id', $product->id)
            ->where('user_id', $b->id)->first();
        $this->assertEquals(Claim::STATUS_ACTIVE, $claimB->status);
        $this->assertEquals(Claim::PHASE_CLAIM, $claimB->phase);
        $this->assertEquals('2026-01-01 10:06:00', $claimB->claim_expires_at->toDateTimeString());
        $this->assertNull($claimB->order);
        $this->assertEquals('mine_pending', $data['status']);
        $this->assertEquals($b->id, $data['active_claim']['user_id']);

        // Then a second look, after B's own claim stage has passed, opens B's
        // payment window — again without any browser involvement.
        $this->at('2026-01-01 10:06:00');
        $this->getJson("/api/products/{$product->id}")->assertOk();

        $claimB->refresh();
        $this->assertEquals(Claim::PHASE_PAYMENT, $claimB->phase);
        $this->assertEquals('2026-01-01 10:07:00', $claimB->payment_expires_at->toDateTimeString());
        $this->assertTrue($claimB->canPay());

        // And a third look after B also failed settles the product back to AVAILABLE
        $this->at('2026-01-01 10:07:00');
        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json('data');

        $this->assertEquals(Claim::STATUS_EXPIRED, $claimB->fresh()->status);
        $this->assertEquals(Product::STATUS_AVAILABLE, $data['status']);
        $this->assertNull($data['active_claim']);
    }

    // ── §28: the payment window is never created twice ───────────────────────

    public function test_payment_window_is_idempotent(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $this->at('2026-01-01 10:00:00');
        $claim = $this->claimService->mine($product, $a);

        $this->at('2026-01-01 10:01:00');
        // Hammer the stage machine the way repeated refreshes would
        for ($i = 0; $i < 5; $i++) {
            $this->tick($product);
        }

        $claim->refresh();
        $this->assertEquals(Claim::PHASE_PAYMENT, $claim->phase);
        $this->assertEquals('2026-01-01 10:02:00', $claim->payment_expires_at->toDateTimeString());
        $this->assertEquals(1, Order::where('claim_id', $claim->id)->count(), 'exactly one payment window');
    }

    // ── §19: concurrent claims produce a single active claimant ─────────────

    public function test_only_one_active_claimant_per_stage(): void
    {
        $this->useStageDurations(60, 60);
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');
        $c = $this->makeCustomer('c@test.com');

        $this->at('2026-01-01 10:00:00');
        $this->claimService->mine($product, $a);
        $this->claimService->mine($product, $b);
        $this->claimService->mine($product, $c);

        $this->assertEquals(1, Claim::where('product_id', $product->id)
            ->where('status', Claim::STATUS_ACTIVE)->count());

        // Move into the payment stage — still exactly one active claimant
        $this->at('2026-01-01 10:01:00');
        $this->tick($product);

        $this->assertEquals(1, Claim::where('product_id', $product->id)
            ->where('status', Claim::STATUS_ACTIVE)->count());

        // Advance to the next claimant — again exactly one
        $this->at('2026-01-01 10:02:00');
        $this->tick($product);

        $this->assertEquals(1, Claim::where('product_id', $product->id)
            ->where('status', Claim::STATUS_ACTIVE)->count());
        $this->assertEquals($b->id, $product->fresh()->activeClaim()?->user_id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
