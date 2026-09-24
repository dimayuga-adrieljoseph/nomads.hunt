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
 * Payment gate rules for the TWO-STAGE claim lifecycle.
 *
 * A customer may only pay while they are the CURRENT active claimant AND their
 * claim stage has finished (phase = payment). Claim-stage, queued, overridden,
 * expired and cancelled claims can never pay — whatever the browser shows.
 */
class PaymentGateTest extends TestCase
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
            'mine_price'  => 1000,
            'steal_price' => 1200,
            'grab_price'  => 1500,
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

    /** End the claim stage — exactly what a real claim timeout does. */
    private function endClaimStage(Claim $claim): Claim
    {
        $this->claimService->expireClaim($claim);

        return $claim->fresh();
    }

    private function passSeconds(int $seconds): void
    {
        Carbon::setTestNow(now()->addSeconds($seconds));
    }

    // ── Claim stage: no payment yet ───────────────────────────────────────────

    /** While the claim stage runs there is no payment window at all */
    public function test_claimant_has_no_payment_window_during_claim_stage(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $claim = $this->claimService->mine($product, $a);

        $this->assertEquals(Claim::PHASE_CLAIM, $claim->phase);
        $this->assertNull($claim->payment_starts_at);
        $this->assertNull($claim->payment_expires_at);
        $this->assertNull($claim->order);
        $this->assertFalse($claim->canPay());
    }

    /** A queued customer has no order and cannot touch the active claimant's one */
    public function test_waiting_claimant_has_no_order(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        $mineA = $this->claimService->mine($product, $a);
        $mineB = $this->claimService->mine($product, $b);

        $this->assertEquals(Claim::STATUS_WAITING, $mineB->status);
        $this->assertEquals(2, $mineB->position);
        $this->assertNull($mineB->order);

        // Even when A reaches the payment stage, B may not pay A's order
        $mineA = $this->endClaimStage($mineA);

        $this->actingAs($b, 'sanctum')
            ->postJson("/api/orders/{$mineA->order->id}/pay")
            ->assertForbidden();
    }

    /** Nobody can pay somebody else's order */
    public function test_customer_cannot_pay_another_customers_order(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        $claim = $this->claimService->mine($product, $a);
        $claim = $this->endClaimStage($claim);

        $this->actingAs($b, 'sanctum')
            ->postJson("/api/orders/{$claim->order->id}/pay")
            ->assertForbidden();
    }

    // ── Payment window ────────────────────────────────────────────────────────

    /** Inside the payment window payment succeeds and the product is SOLD */
    public function test_payment_succeeds_in_payment_window(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $claim = $this->claimService->mine($product, $a);
        $claim = $this->endClaimStage($claim);

        $this->assertEquals(Claim::PHASE_PAYMENT, $claim->phase);
        $this->assertNotNull($claim->payment_starts_at);
        $this->assertNotNull($claim->payment_expires_at);
        $this->assertTrue($claim->canPay());

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$claim->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals(Claim::STATUS_COMPLETED, $claim->fresh()->status);
        $this->assertEquals(Product::STATUS_SOLD, $product->fresh()->status);
    }

    /** Once the payment window lapses the order can no longer be paid */
    public function test_payment_refused_after_payment_window_lapses(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $claim = $this->claimService->mine($product, $a);
        $claim = $this->endClaimStage($claim);
        $order = $claim->order;

        $this->passSeconds((int) config('app.payment_seconds', 60) + 5);

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(422);

        $this->assertNotEquals(Product::STATUS_SOLD, $product->fresh()->status);
    }

    // ── Overridden claims ─────────────────────────────────────────────────────

    /** A Steal voids the overridden Mine's payment window immediately */
    public function test_overridden_mine_order_is_voided(): void
    {
        $product = $this->makeProduct();
        $miner = $this->makeCustomer('m@test.com');
        $stealer = $this->makeCustomer('s@test.com');

        $mine = $this->claimService->mine($product, $miner);
        $mine = $this->endClaimStage($mine);        // miner now holds a payment window
        $mineOrder = $mine->order;

        $steal = $this->claimService->steal($product, $stealer);

        $this->assertEquals(Claim::STATUS_OVERRIDDEN, $mine->fresh()->status);
        $this->assertEquals(Order::PAYMENT_CANCELLED, $mineOrder->fresh()->payment_status);
        // The stealer is still in its claim stage, so nothing is payable at all
        $this->assertNull($steal->order);
        $this->assertEquals(
            0,
            Order::where('product_id', $product->id)->where('payment_status', Order::PAYMENT_PENDING)->count()
        );
    }

    /** An overridden claimant can no longer proceed to payment */
    public function test_overridden_mine_claimant_cannot_pay(): void
    {
        $product = $this->makeProduct();
        $miner = $this->makeCustomer('m@test.com');
        $stealer = $this->makeCustomer('s@test.com');

        $mine = $this->claimService->mine($product, $miner);
        $mine = $this->endClaimStage($mine);
        $order = $mine->order;

        $this->claimService->steal($product, $stealer);

        $response = $this->actingAs($miner, 'sanctum')->postJson("/api/orders/{$order->id}/pay");

        $response->assertStatus(422);
        $this->assertStringContainsString('overridden', strtolower($response->json('message')));
        $this->assertNotEquals(Product::STATUS_SOLD, $product->fresh()->status);
    }

    /** The active Steal claimant pays inside its own payment window */
    public function test_active_steal_claimant_can_pay(): void
    {
        $product = $this->makeProduct();
        $miner = $this->makeCustomer('m@test.com');
        $stealer = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $steal = $this->claimService->steal($product, $stealer);

        $steal = $this->endClaimStage($steal);

        $this->actingAs($stealer, 'sanctum')
            ->postJson("/api/orders/{$steal->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals(Claim::STATUS_COMPLETED, $steal->fresh()->status);
    }

    // ── Sold product ──────────────────────────────────────────────────────────

    /** Sold products can never be claimed again */
    public function test_sold_product_cannot_be_claimed(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');
        $b = $this->makeCustomer('b@test.com');

        $mineA = $this->claimService->mine($product, $a);
        $mineB = $this->claimService->mine($product, $b);

        $mineA = $this->endClaimStage($mineA);

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$mineA->order->id}/pay")
            ->assertOk();

        // Losing queue members are cancelled, not left hanging as "active"
        $this->assertEquals(Claim::STATUS_CANCELLED, $mineB->fresh()->status);
        $this->assertNull($mineB->fresh()->order);

        $c = $this->makeCustomer('c@test.com');
        $this->actingAs($c, 'sanctum')
            ->postJson("/api/products/{$product->id}/mine")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PRODUCT_SOLD');
    }

    // ── Grab (buy now — skips the claim stage) ────────────────────────────────

    /** Grab opens the payment window immediately and creates no queue */
    public function test_grab_creates_no_queue_and_sells(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $grab = $this->claimService->grab($product, $a);

        $this->assertEquals(Claim::PHASE_PAYMENT, $grab->phase);
        $this->assertNull($grab->claim_expires_at);          // no claim stage
        $this->assertNotNull($grab->payment_expires_at);
        $this->assertEquals('grab_pending', $product->fresh()->status);
        $this->assertEquals(0, Claim::where('product_id', $product->id)->where('status', Claim::STATUS_WAITING)->count());
        $this->assertEquals(1500, $grab->order->amount);

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$grab->order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');
    }

    /** An expired Grab releases the product back to AVAILABLE */
    public function test_expired_grab_returns_product_to_available(): void
    {
        $product = $this->makeProduct();
        $a = $this->makeCustomer('a@test.com');

        $grab = $this->claimService->grab($product, $a);

        $this->passSeconds((int) config('app.payment_seconds', 60) + 5);

        // Any customer facing read flushes the overdue payment window
        $this->getJson("/api/products/{$product->id}")->assertOk();

        $this->assertEquals(Product::STATUS_AVAILABLE, $product->fresh()->status);
        $this->assertEquals(Claim::STATUS_EXPIRED, $grab->fresh()->status);
        $this->assertEquals(Order::PAYMENT_EXPIRED, $grab->order->fresh()->payment_status);

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$grab->order->id}/pay")
            ->assertStatus(422);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
