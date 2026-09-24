<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MineClaimTest extends TestCase
{
    use RefreshDatabase;

    private ClaimService $claimService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claimService = app(ClaimService::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function makeCustomer(string $email = 'test@test.com'): User
    {
        return User::create([
            'name'     => 'Test User',
            'email'    => $email,
            'password' => bcrypt('password'),
            'role'     => 'customer',
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /** First Mine on an available product becomes ACTIVE */
    public function test_first_mine_becomes_active(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $claim = $this->claimService->mine($product, $customer);

        $this->assertEquals('active', $claim->status);
        $this->assertEquals(1, $claim->position);
        $this->assertNotNull($claim->expires_at);
        $this->assertEquals('mine_pending', $product->fresh()->status);
    }

    /** Second Mine enters queue as position 2, status WAITING */
    public function test_second_mine_enters_queue(): void
    {
        $product  = $this->makeProduct();
        $customer1 = $this->makeCustomer('c1@test.com');
        $customer2 = $this->makeCustomer('c2@test.com');

        $this->claimService->mine($product, $customer1);
        $claim2 = $this->claimService->mine($product, $customer2);

        $this->assertEquals('waiting', $claim2->status);
        $this->assertEquals(2, $claim2->position);
        $this->assertNull($claim2->expires_at);
    }

    /** Third Mine enters queue as position 3 */
    public function test_third_mine_enters_queue(): void
    {
        $product   = $this->makeProduct();
        $customer1 = $this->makeCustomer('c1@test.com');
        $customer2 = $this->makeCustomer('c2@test.com');
        $customer3 = $this->makeCustomer('c3@test.com');

        $this->claimService->mine($product, $customer1);
        $this->claimService->mine($product, $customer2);
        $claim3 = $this->claimService->mine($product, $customer3);

        $this->assertEquals('waiting', $claim3->status);
        $this->assertEquals(3, $claim3->position);
    }

    /** A user cannot Mine the same product twice while already in queue */
    public function test_duplicate_mine_throws(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $this->claimService->mine($product, $customer);

        $this->expectException(\App\Exceptions\ClaimException::class);
        $this->claimService->mine($product, $customer);
    }

    /** Cannot Mine a SOLD product */
    public function test_cannot_mine_sold_product(): void
    {
        $product  = $this->makeProduct(['status' => 'sold']);
        $customer = $this->makeCustomer();

        $this->expectException(\App\Exceptions\ClaimException::class);
        $this->claimService->mine($product, $customer);
    }

    /** First Mine starts a CLAIM stage — no payment opportunity yet */
    public function test_mine_starts_claim_stage_without_order(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $claim = $this->claimService->mine($product, $customer);

        $this->assertEquals('active', $claim->status);
        $this->assertEquals('claim', $claim->phase);
        $this->assertNotNull($claim->claim_expires_at);
        $this->assertNull($claim->payment_starts_at);
        $this->assertNull($claim->payment_expires_at);
        // The payment window (and its order) must NOT exist yet
        $this->assertNull($claim->order);

        // Paying during the claim stage is rejected server-side
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/mine")
            ->assertStatus(422); // already in the queue
    }

    /** When the claim stage ends the SAME claimant gets the payment window */
    public function test_claim_stage_end_opens_payment_window(): void
    {
        $product  = $this->makeProduct();
        $a        = $this->makeCustomer('c1@test.com');
        $b        = $this->makeCustomer('c2@test.com');

        $claimA = $this->claimService->mine($product, $a);
        $claimB = $this->claimService->mine($product, $b);

        // End the claim stage of A
        $this->claimService->expireClaim($claimA);

        $claimA->refresh();
        $this->assertEquals('active', $claimA->status);
        $this->assertEquals('payment', $claimA->phase);
        $this->assertNotNull($claimA->payment_starts_at);
        $this->assertNotNull($claimA->payment_expires_at);
        $this->assertEquals('pending', $claimA->order->payment_status);

        // A is still the owner — the queue must NOT have moved to B
        $this->assertEquals('waiting', $claimB->fresh()->status);
        $this->assertEquals($a->id, $product->fresh()->activeClaim()?->user_id);
    }

    /** Paying during the CLAIM stage is refused even with a forged order */
    public function test_cannot_pay_during_claim_stage(): void
    {
        $product  = $this->makeProduct();
        $a        = $this->makeCustomer('a@test.com');

        $claim = $this->claimService->mine($product, $a);

        // A pending order created by hand (e.g. stale UI / API abuse) is refused
        $order = \App\Models\Order::create([
            'order_number'   => '00099',
            'user_id'        => $a->id,
            'product_id'     => $product->id,
            'claim_id'       => $claim->id,
            'amount'         => 1000,
            'claim_type'     => 'mine',
            'payment_status' => 'pending',
            'status'         => 'pending',
            'expires_at'     => now()->addMinutes(10),
        ]);

        $this->actingAs($a, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'CLAIM_PERIOD_ACTIVE');

        $this->assertNotEquals('sold', $product->fresh()->status);
    }

    /** Waiting Mine does NOT create an Order (order created only on activation) */
    public function test_waiting_mine_has_no_order(): void
    {
        $product   = $this->makeProduct();
        $customer1 = $this->makeCustomer('c1@test.com');
        $customer2 = $this->makeCustomer('c2@test.com');

        $this->claimService->mine($product, $customer1);
        $claim2 = $this->claimService->mine($product, $customer2);

        $this->assertNull($claim2->order);
    }

    /** Paying inside the PAYMENT window marks product SOLD and claim COMPLETED */
    public function test_payment_completes_mine_and_sells_product(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();
        $claim    = $this->claimService->mine($product, $customer);

        // Claim stage must finish first (a real timeout does the same thing)
        $this->claimService->expireClaim($claim);
        $claim->refresh();
        $order = $claim->order;

        // Simulate payment via the HTTP endpoint
        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals('completed', $claim->fresh()->status);
    }

    /** Payment expiry (not claim expiry) hands the item to the next in queue */
    public function test_payment_expiry_advances_mine_queue(): void
    {
        $product   = $this->makeProduct();
        $customer1 = $this->makeCustomer('c1@test.com');
        $customer2 = $this->makeCustomer('c2@test.com');

        $claim1 = $this->claimService->mine($product, $customer1);
        $claim2 = $this->claimService->mine($product, $customer2);

        // Stage 1: claim period ends → claimant 1 enters PAYMENT, queue untouched
        $this->claimService->expireClaim($claim1);
        $this->assertEquals('active', $claim1->fresh()->status);
        $this->assertEquals('payment', $claim1->fresh()->phase);
        $this->assertEquals('waiting', $claim2->fresh()->status);

        // Stage 2: the payment window lapses → claimant 2 takes over
        $this->claimService->expireClaim($claim1);

        $this->assertEquals('expired', $claim1->fresh()->status);
        $this->assertEquals('active', $claim2->fresh()->status);
        // Claimant 2 gets a NEW claim stage — not an instant payment window
        $this->assertEquals('claim', $claim2->fresh()->phase);
        $this->assertNotNull($claim2->fresh()->claim_expires_at);
        $this->assertNull($claim2->fresh()->order);
    }

    /** When the last claimant's payment lapses, product returns to AVAILABLE */
    public function test_last_mine_expiry_returns_product_to_available(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $claim = $this->claimService->mine($product, $customer);

        $this->claimService->expireClaim($claim); // claim stage → payment window
        $this->assertEquals('payment', $claim->fresh()->phase);
        $this->assertEquals('mine_pending', $product->fresh()->status);

        $this->claimService->expireClaim($claim); // payment window → expired
        $this->assertEquals('expired', $claim->fresh()->status);
        $this->assertEquals('available', $product->fresh()->status);
    }

    /** Mine via HTTP endpoint requires auth */
    public function test_mine_requires_authentication(): void
    {
        $product = $this->makeProduct();
        $this->postJson("/api/products/{$product->id}/mine")
            ->assertUnauthorized();
    }

    /** Mine via HTTP endpoint requires customer role */
    public function test_mine_requires_customer_role(): void
    {
        $product = $this->makeProduct();
        $admin   = User::create([
            'name' => 'Admin', 'email' => 'a@a.com',
            'password' => bcrypt('p'), 'role' => 'admin',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/products/{$product->id}/mine")
            ->assertForbidden();
    }
}
