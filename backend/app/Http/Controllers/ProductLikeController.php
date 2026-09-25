<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductLikeController extends Controller
{
    /** Return all saved products, including pieces that are now sold. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = $request->user()
            ->likedProducts()
            ->latest('product_likes.created_at')
            ->with('primaryImage')
            ->get()
            ->each(fn (Product $product) => $product->setAttribute('is_liked', true));

        return ProductResource::collection($products);
    }

    /** Save a product without touching the independent claim lifecycle. */
    public function store(Request $request, Product $product): JsonResponse
    {
        $like = ProductLike::firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        $product->load('primaryImage');
        $product->setAttribute('is_liked', true);

        return response()->json([
            'message' => $like->wasRecentlyCreated ? 'Product added to liked products.' : 'Product is already liked.',
            'data' => (new ProductResource($product))->toArray($request),
        ], $like->wasRecentlyCreated ? 201 : 200);
    }

    /** Unliking is idempotent and works for every product status. */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        ProductLike::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        $product->load('primaryImage');
        $product->setAttribute('is_liked', false);

        return response()->json([
            'message' => 'Product removed from liked products.',
            'data' => (new ProductResource($product))->toArray($request),
        ]);
    }
}
