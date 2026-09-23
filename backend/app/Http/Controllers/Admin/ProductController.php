<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\ActivityLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * List all products with full pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query();

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
        return new ProductResource($product);
    }

    /**
     * Create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $product = Product::create($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'product_id'  => $product->id,
            'action'      => ActivityLog::PRODUCT_CREATED,
            'description' => "{$request->user()->name} created product \"{$product->name}\"",
        ]);

        return response()->json(['data' => new ProductResource($product)], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $this->validateProduct($request, $product);

        $product->update($validated);

        ActivityLog::create([
            'user_id'     => $request->user()->id,
            'product_id'  => $product->id,
            'action'      => ActivityLog::PRODUCT_UPDATED,
            'description' => "{$request->user()->name} updated product \"{$product->name}\"",
        ]);

        return response()->json(['data' => new ProductResource($product->fresh())]);
    }

    /**
     * Upload a product image. Returns the filename stored.
     */
    public function uploadImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        // Delete old image if present
        if ($product->image) {
            Storage::disk('public')->delete("products/{$product->image}");
        }

        $file     = $request->file('image');
        $filename = uniqid('product_', true) . '.' . $file->getClientOriginalExtension();
        $file->storeAs('products', $filename, 'public');

        $product->update(['image' => $filename]);

        return response()->json([
            'image_url' => asset("storage/products/{$filename}"),
            'image'     => $filename,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category'    => ['nullable', 'string', 'max:100'],
            'brand'       => ['nullable', 'string', 'max:100'],
            'size'        => ['nullable', 'string', 'max:50'],
            'condition'   => ['required', 'in:excellent,good,fair,poor'],
            'mine_price'  => ['required', 'integer', 'min:1'],
            'steal_price' => ['required', 'integer', 'min:1'],
            'grab_price'  => ['required', 'integer', 'min:1'],
            'status'      => ['sometimes', 'in:available,mine_pending,steal_pending,grab_pending,sold'],
        ]);
    }
}
