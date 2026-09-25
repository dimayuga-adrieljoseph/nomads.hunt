<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private readonly ProductImageService $productImages) {}

    /**
     * List all products with full pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with('primaryImage');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return ProductResource::collection($query->latest()->paginate(20));
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): ProductResource
    {
        $product->load('images');

        return new ProductResource($product);
    }

    /**
     * Create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);
        // The existing application permits products without an image, so preserve
        // that behavior while making the first uploaded image primary.
        $imageData = $this->validateImages($request, false);
        $storedPaths = $this->productImages->storeUploads($imageData['files']);

        try {
            $product = DB::transaction(function () use ($request, $validated, $imageData, $storedPaths): Product {
                $product = Product::create($validated);
                $this->productImages->sync(
                    $product,
                    $storedPaths,
                    $imageData['order'],
                    $imageData['primary'],
                );

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'action' => ActivityLog::PRODUCT_CREATED,
                    'description' => "{$request->user()->name} created product \"{$product->name}\"",
                ]);

                return $product->fresh()->load('images');
            });
        } catch (Throwable $exception) {
            $this->productImages->deleteFiles($storedPaths);

            throw $exception;
        }

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validateProduct($request);
        $imageData = $this->validateImages($request, false);
        $storedPaths = $this->productImages->storeUploads($imageData['files']);

        try {
            [$product, $removedPaths] = DB::transaction(function () use (
                $request,
                $product,
                $validated,
                $imageData,
                $storedPaths,
            ): array {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                $product->update($validated);
                $removedPaths = $imageData['sync']
                    ? $this->productImages->sync(
                        $product,
                        $storedPaths,
                        $imageData['order'],
                        $imageData['primary'],
                    )
                    : [];

                ActivityLog::create([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                    'action' => ActivityLog::PRODUCT_UPDATED,
                    'description' => "{$request->user()->name} updated product \"{$product->name}\"",
                ]);

                return [$product->fresh()->load('images'), $removedPaths];
            });
        } catch (Throwable $exception) {
            $this->productImages->deleteFiles($storedPaths);

            throw $exception;
        }

        $this->productImages->deleteFiles($removedPaths);

        return response()->json(['data' => new ProductResource($product)]);
    }

    /**
     * Upload a product image. Returns the filename stored.
     */
    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $storedPaths = $this->productImages->storeUploads([$request->file('image')]);

        try {
            $product = DB::transaction(function () use ($product, $storedPaths): Product {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                $this->productImages->sync($product, $storedPaths);

                return $product->fresh()->load('images');
            });
        } catch (Throwable $exception) {
            $this->productImages->deleteFiles($storedPaths);

            throw $exception;
        }

        return response()->json([
            'data' => new ProductResource($product),
            'image_url' => $product->primaryImageUrl(),
        ]);
    }

    public function destroyImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        if ($image->product_id !== $product->id) {
            abort(404);
        }

        $path = DB::transaction(function () use ($request, $product, $image): string {
            Product::lockForUpdate()->findOrFail($product->id);
            $image = ProductImage::lockForUpdate()->findOrFail($image->id);
            $path = $this->productImages->delete($product, $image);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'action' => ActivityLog::PRODUCT_UPDATED,
                'description' => "{$request->user()->name} deleted product image \"".basename($path).'"',
            ]);

            return $path;
        });

        $this->productImages->deleteFiles([$path]);

        return response()->json([
            'message' => 'Product image removed.',
            'data' => new ProductResource($product->fresh()->load('images')),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:50'],
            'condition' => ['required', 'in:excellent,good,fair,poor'],
            'mine_price' => ['required', 'integer', 'min:1'],
            'steal_price' => ['required', 'integer', 'min:1'],
            'grab_price' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:available,mine_pending,steal_pending,grab_pending,sold'],
        ]);
    }

    /**
     * @return array{files: array, order: ?array, primary: ?string, sync: bool}
     */
    private function validateImages(Request $request, bool $required): array
    {
        $rules = [
            'images' => [$required ? 'required' : 'sometimes', 'array', $required ? 'min:1' : 'nullable'],
            'images.*' => ['image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'image_order' => ['sometimes', 'array'],
            'image_order.*' => ['string', 'regex:/^(existing|new):[1-9][0-9]*$/'],
            'primary_image' => ['nullable', 'string', 'regex:/^(existing|new):[1-9][0-9]*$/'],
            'clear_images' => ['sometimes', 'boolean'],
            'sync_images' => ['sometimes', 'boolean'],
        ];

        $validated = $request->validate($rules);
        $files = array_values($request->file('images', []));

        return [
            'files' => $files,
            'order' => $request->boolean('clear_images')
                ? []
                : ($request->has('image_order')
                    ? ($validated['image_order'] ?? [])
                    : null),
            'primary' => $validated['primary_image'] ?? null,
            'sync' => $required
                || $request->has('images')
                || $request->has('image_order')
                || $request->has('primary_image')
                || $request->boolean('sync_images')
                || $request->boolean('clear_images'),
        ];
    }
}
