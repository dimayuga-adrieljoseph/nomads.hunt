<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function productData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Gallery Product',
            'condition' => 'good',
            'mine_price' => 5000,
            'steal_price' => 5300,
            'grab_price' => 5600,
        ], $overrides);
    }

    public function test_admin_can_create_a_product_with_multiple_ordered_images(): void
    {
        Storage::fake('public');
        $front = UploadedFile::fake()->image('front.jpg', 800, 800);
        $back = UploadedFile::fake()->image('back.png', 800, 800);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/admin/products', array_merge($this->productData(), [
                'images' => [$front, $back],
                'image_order' => ['new:1', 'new:2'],
                'primary_image' => 'new:2',
            ]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.is_primary', true)
            ->assertJsonPath('data.images.1.is_primary', false);

        $product = Product::findOrFail($response->json('data.id'));
        $images = $product->images()->get();

        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertSame(0, $images[0]->sort_order);
        $this->assertSame(1, $images[1]->sort_order);
        $this->assertSame(basename($images[0]->image_path), $product->image);
        Storage::disk('public')->assertExists($images[0]->image_path);
        Storage::disk('public')->assertExists($images[1]->image_path);
    }

    public function test_editing_can_reorder_add_and_change_the_primary_image(): void
    {
        Storage::fake('public');
        $product = Product::create($this->productData());
        $first = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/first.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $second = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/second.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        Storage::disk('public')->put('products/first.jpg', 'first');
        Storage::disk('public')->put('products/second.jpg', 'second');
        $new = UploadedFile::fake()->image('new.webp', 800, 800);

        $this->actingAs($this->admin(), 'sanctum')
            ->put("/api/admin/products/{$product->id}", array_merge($this->productData([
                'name' => 'Updated Gallery Product',
            ]), [
                'images' => [$new],
                'image_order' => ['new:1', 'existing:'.$second->id, 'existing:'.$first->id],
                'primary_image' => 'new:1',
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'data.images')
            ->assertJsonPath('data.images.0.is_primary', true);

        $images = $product->fresh()->images()->get();
        $this->assertCount(3, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertSame($second->id, $images[1]->id);
        $this->assertSame($first->id, $images[2]->id);
        $this->assertSame('Updated Gallery Product', $product->fresh()->name);
    }

    public function test_removing_the_primary_promotes_the_next_image_and_deletes_only_that_file(): void
    {
        Storage::fake('public');
        $product = Product::create($this->productData());
        $primary = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/primary.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $next = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/next.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        Storage::disk('public')->put($primary->image_path, 'primary');
        Storage::disk('public')->put($next->image_path, 'next');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/products/{$product->id}/images/{$primary->id}")
            ->assertOk()
            ->assertJsonPath('data.images.0.id', $next->id)
            ->assertJsonPath('data.images.0.is_primary', true);

        $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
        $this->assertTrue($next->fresh()->is_primary);
        $this->assertSame(0, $next->fresh()->sort_order);
        Storage::disk('public')->assertMissing($primary->image_path);
        Storage::disk('public')->assertExists($next->image_path);
    }

    public function test_catalog_returns_only_the_primary_url_but_detail_returns_the_gallery(): void
    {
        $product = Product::create($this->productData());
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/catalog.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/detail.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.image_url', url('/storage/products/catalog.jpg'))
            ->assertJsonMissingPath('data.0.images');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.url', url('/storage/products/catalog.jpg'))
            ->assertJsonPath('data.images.1.url', url('/storage/products/detail.jpg'));
    }

    public function test_migration_backfills_existing_legacy_product_images(): void
    {
        $product = Product::create($this->productData([
            'name' => 'Legacy Product',
            'image' => 'legacy.jpg',
        ]));

        $migration = require database_path('migrations/2026_09_25_000002_create_product_images_table.php');
        $migration->down();
        $migration->up();

        $image = ProductImage::where('product_id', $product->id)->sole();
        $this->assertSame('products/legacy.jpg', $image->image_path);
        $this->assertSame(0, $image->sort_order);
        $this->assertTrue($image->is_primary);
        $this->assertSame('legacy.jpg', $product->fresh()->image);
    }

    public function test_create_without_an_image_remains_supported(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/products', $this->productData())
            ->assertCreated()
            ->assertJsonPath('data.image_url', null)
            ->assertJsonCount(0, 'data.images');

        $this->assertDatabaseCount('product_images', 0);
    }

    public function test_invalid_image_uploads_are_rejected_without_creating_a_product(): void
    {
        $file = UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf');

        $this->actingAs($this->admin(), 'sanctum')
            ->post('/api/admin/products', $this->productData(['images' => [$file]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('images.0');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_editing_without_image_fields_preserves_existing_images(): void
    {
        $product = Product::create($this->productData());
        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/preserved.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", $this->productData(['name' => 'Renamed Product']))
            ->assertOk()
            ->assertJsonCount(1, 'data.images');

        $this->assertDatabaseHas('product_images', [
            'id' => $image->id,
            'image_path' => 'products/preserved.jpg',
            'is_primary' => true,
        ]);
    }
}
