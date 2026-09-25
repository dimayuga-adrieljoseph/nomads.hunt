<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLike;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLikeTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Test Product',
            'condition' => 'good',
            'mine_price' => 5000,
            'steal_price' => 5300,
            'grab_price' => 5600,
            'status' => Product::STATUS_AVAILABLE,
        ], $overrides));
    }

    public function test_likes_require_authentication_and_a_customer_account(): void
    {
        $product = $this->makeProduct();
        $admin = User::factory()->admin()->create();

        $this->getJson('/api/likes')->assertUnauthorized();
        $this->postJson("/api/products/{$product->id}/like")->assertUnauthorized();
        $this->deleteJson("/api/products/{$product->id}/like")->assertUnauthorized();
        $this->actingAs($admin, 'sanctum')->getJson('/api/likes')->assertForbidden();
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")->assertForbidden();
    }

    public function test_customer_can_like_and_unlike_a_product(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")
            ->assertCreated()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.is_liked', true);

        $this->assertDatabaseHas('product_likes', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/products/{$product->id}/like")
            ->assertOk()
            ->assertJsonPath('data.is_liked', false);

        $this->assertDatabaseMissing('product_likes', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_duplicate_user_product_likes_are_prevented(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")->assertCreated();
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")->assertOk();

        $this->assertSame(1, ProductLike::where([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ])->count());

        $this->expectException(QueryException::class);
        ProductLike::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_like_state_is_specific_to_the_authenticated_user(): void
    {
        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        $product = $this->makeProduct();

        $this->actingAs($customerA, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")->assertCreated();

        $this->actingAs($customerA, 'sanctum')
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.is_liked', true);
        $this->actingAs($customerA, 'sanctum')
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked', true);
        $this->actingAs($customerA, 'sanctum')
            ->getJson('/api/likes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.is_liked', true);

        $this->actingAs($customerB, 'sanctum')
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.is_liked', false);
        $this->actingAs($customerB, 'sanctum')
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked', false);
        $this->actingAs($customerB, 'sanctum')
            ->getJson('/api/likes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_guest_product_responses_report_an_unliked_state(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct();
        ProductLike::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.is_liked', false);
        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.is_liked', false);
    }

    public function test_likes_work_for_every_product_status_including_sold(): void
    {
        $customer = User::factory()->customer()->create();
        $statuses = [
            Product::STATUS_AVAILABLE,
            Product::STATUS_MINE_PENDING,
            Product::STATUS_STEAL_PENDING,
            Product::STATUS_GRAB_PENDING,
            Product::STATUS_SOLD,
        ];

        foreach ($statuses as $status) {
            $product = $this->makeProduct(['status' => $status]);
            $this->actingAs($customer, 'sanctum')
                ->postJson("/api/products/{$product->id}/like")
                ->assertCreated()
                ->assertJsonPath('data.status', $status)
                ->assertJsonPath('data.is_liked', true);
        }

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/likes')
            ->assertOk()
            ->assertJsonCount(count($statuses), 'data');

        $this->assertEqualsCanonicalizing(
            $statuses,
            array_column($response->json('data'), 'status'),
        );
    }

    public function test_liking_never_creates_or_changes_a_claim_or_order(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct();

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")
            ->assertCreated();

        $this->assertSame(Product::STATUS_AVAILABLE, $product->fresh()->status);
        $this->assertSame(0, Claim::where('product_id', $product->id)->count());
        $this->assertSame(0, Order::where('product_id', $product->id)->count());

        $claim = app(ClaimService::class)->mine($product->fresh(), $customer);
        $claimSnapshot = $claim->only(['id', 'status', 'phase', 'amount', 'position']);

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/products/{$product->id}/like")
            ->assertOk();

        $this->assertSame($claimSnapshot, $claim->fresh()->only(array_keys($claimSnapshot)));
        $this->assertSame(Product::STATUS_MINE_PENDING, $product->fresh()->status);
        $this->assertSame(0, Order::where('claim_id', $claim->id)->count());
    }
}
