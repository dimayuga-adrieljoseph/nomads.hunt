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

    /** First Mine creates a pending Order */
    public function test_mine_creates_pending_order(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $claim = $this->claimService->mine($product, $customer);

        $this->assertNotNull($claim->order);
        $this->assertEquals('pending', $claim->order->payment_status);
        $this->assertEquals($customer->id, $claim->order->user_id);
        $this->assertEquals(1000, $claim->order->amount);
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

    /** Paying active Mine marks product SOLD and claim COMPLETED */
    public function test_payment_completes_mine_and_sells_product(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();
        $claim    = $this->claimService->mine($product, $customer);
        $order    = $claim->order;

        // Simulate payment via the HTTP endpoint
        $response = $this->actingAs($customer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pay");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.product.status', 'sold');

        $this->assertEquals('completed', $claim->fresh()->status);
    }

    /** When active Mine expires, next Mine in queue becomes ACTIVE */
    public function test_expiration_advances_mine_queue(): void
    {
        $product   = $this->makeProduct();
        $customer1 = $this->makeCustomer('c1@test.com');
        $customer2 = $this->makeCustomer('c2@test.com');

        $claim1 = $this->claimService->mine($product, $customer1);
        $claim2 = $this->claimService->mine($product, $customer2);

        // Force expire claim1
        $this->claimService->expireClaim($claim1);

        $this->assertEquals('expired', $claim1->fresh()->status);
        $this->assertEquals('active', $claim2->fresh()->status);
        $this->assertNotNull($claim2->fresh()->expires_at);
        // An order should now exist for claim2
        $this->assertNotNull($claim2->fresh()->order);
    }

    /** When the last Mine expires, product returns to AVAILABLE */
    public function test_last_mine_expiry_returns_product_to_available(): void
    {
        $product  = $this->makeProduct();
        $customer = $this->makeCustomer();

        $claim = $this->claimService->mine($product, $customer);
        $this->claimService->expireClaim($claim);

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
