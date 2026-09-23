<?php

namespace Tests\Feature;

use App\Exceptions\ClaimException;
use App\Models\Claim;
use App\Models\Product;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StealClaimTest extends TestCase
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

    private function makeCustomer(string $email = 'test@test.com'): User
    {
        return User::create([
            'name'     => 'Customer',
            'email'    => $email,
            'password' => bcrypt('password'),
            'role'     => 'customer',
        ]);
    }

    // ── Steal overrides Mine ──────────────────────────────────────────────────

    /** First Steal overrides the active Mine and all waiting Mines */
    public function test_steal_overrides_all_mine_claims(): void
    {
        $product   = $this->makeProduct();
        $miner1    = $this->makeCustomer('m1@test.com');
        $miner2    = $this->makeCustomer('m2@test.com');
        $stealer   = $this->makeCustomer('s1@test.com');

        $mine1 = $this->claimService->mine($product, $miner1);
        $mine2 = $this->claimService->mine($product, $miner2);

        $this->claimService->steal($product, $stealer);

        $this->assertEquals('overridden', $mine1->fresh()->status);
        $this->assertEquals('overridden', $mine2->fresh()->status);
    }

    /** Steal sets product status to STEAL_PENDING */
    public function test_steal_sets_product_steal_pending(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $this->claimService->steal($product, $stealer);

        $this->assertEquals('steal_pending', $product->fresh()->status);
    }

    /** First Steal becomes ACTIVE, not WAITING */
    public function test_first_steal_is_active(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $stealClaim = $this->claimService->steal($product, $stealer);

        $this->assertEquals('active', $stealClaim->status);
        $this->assertNotNull($stealClaim->expires_at);
    }

    /** Mine becomes unavailable after Steal is active */
    public function test_mine_blocked_when_steal_active(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');
        $latecomer = $this->makeCustomer('l@test.com');

        $this->claimService->mine($product, $miner);
        $this->claimService->steal($product, $stealer);

        $this->expectException(ClaimException::class);
        $this->claimService->mine($product, $latecomer);
    }

    /** Second Steal queues at position 2 with WAITING status */
    public function test_multiple_steals_queue(): void
    {
        $product   = $this->makeProduct();
        $miner     = $this->makeCustomer('m@test.com');
        $stealer1  = $this->makeCustomer('s1@test.com');
        $stealer2  = $this->makeCustomer('s2@test.com');

        $this->claimService->mine($product, $miner);
        $this->claimService->steal($product, $stealer1);
        $steal2 = $this->claimService->steal($product, $stealer2);

        $this->assertEquals('waiting', $steal2->status);
        $this->assertEquals(2, $steal2->position);
    }

    /** Steal expiration advances to next steal in queue */
    public function test_steal_expiration_advances_steal_queue(): void
    {
        $product   = $this->makeProduct();
        $miner     = $this->makeCustomer('m@test.com');
        $stealer1  = $this->makeCustomer('s1@test.com');
        $stealer2  = $this->makeCustomer('s2@test.com');

        $this->claimService->mine($product, $miner);
        $steal1 = $this->claimService->steal($product, $stealer1);
        $steal2 = $this->claimService->steal($product, $stealer2);

        $this->claimService->expireClaim($steal1);

        $this->assertEquals('expired', $steal1->fresh()->status);
        $this->assertEquals('active',  $steal2->fresh()->status);
        $this->assertNotNull($steal2->fresh()->expires_at);
    }

    /** When last Steal expires, product returns to AVAILABLE */
    public function test_last_steal_expiry_returns_product_to_available(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $steal = $this->claimService->steal($product, $stealer);
        $this->claimService->expireClaim($steal);

        $this->assertEquals('available', $product->fresh()->status);
    }

    /** Steal cannot be placed on an AVAILABLE product (no active Mine) */
    public function test_steal_requires_active_mine(): void
    {
        $product = $this->makeProduct();
        $stealer = $this->makeCustomer('s@test.com');

        $this->expectException(ClaimException::class);
        $this->claimService->steal($product, $stealer);
    }

    /** Steal creates a pending Order for the active claimant */
    public function test_steal_creates_pending_order(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $steal = $this->claimService->steal($product, $stealer);

        $this->assertNotNull($steal->order);
        $this->assertEquals('pending', $steal->order->payment_status);
        $this->assertEquals(1200, $steal->order->amount);
    }

    /** Successful Steal payment marks product SOLD */
    public function test_steal_payment_sells_product(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $steal = $this->claimService->steal($product, $stealer);
        $order = $steal->order;

        $this->actingAs($stealer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay")
            ->assertOk()
            ->assertJsonPath('data.product.status', 'sold');
    }

    /** Duplicate Steal from same user throws */
    public function test_duplicate_steal_throws(): void
    {
        $product  = $this->makeProduct();
        $miner    = $this->makeCustomer('m@test.com');
        $stealer  = $this->makeCustomer('s@test.com');

        $this->claimService->mine($product, $miner);
        $this->claimService->steal($product, $stealer);

        $this->expectException(ClaimException::class);
        $this->claimService->steal($product, $stealer);
    }
}
