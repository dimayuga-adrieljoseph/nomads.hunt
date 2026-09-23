<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Public catalog — supports search, filter, pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query();

        // Full-text search across name, brand, category
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($size = $request->input('size')) {
            $query->where('size', $size);
        }

        if ($condition = $request->input('condition')) {
            $query->where('condition', $condition);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $products = $query->latest()->paginate(12);

        return ProductResource::collection($products);
    }

    /**
     * Public product detail — includes active claim info for UI rendering.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $activeClaim = $product->activeClaim();

        $data = (new ProductResource($product))->toArray($request);

        // Expose just enough claim context for the UI to render correctly
        $data['active_claim'] = $activeClaim ? [
            'id'         => $activeClaim->id,
            'type'       => $activeClaim->type,
            'user_id'    => $activeClaim->user_id,
            'status'     => $activeClaim->status,
            'expires_at' => $activeClaim->expires_at?->toISOString(),
        ] : null;

        // Mine queue count (WAITING mines — shows position context)
        $data['mine_queue_count']  = $product->claims()
            ->where('type', 'mine')
            ->whereIn('status', ['active', 'waiting'])
            ->count();

        $data['steal_queue_count'] = $product->claims()
            ->where('type', 'steal')
            ->whereIn('status', ['active', 'waiting'])
            ->count();

        return response()->json(['data' => $data]);
    }

    /**
     * Return distinct filter values for the catalog UI.
     */
    public function filterOptions(): JsonResponse
    {
        return response()->json([
            'categories' => Product::distinct()->whereNotNull('category')->pluck('category')->sort()->values(),
            'sizes'      => Product::distinct()->whereNotNull('size')->pluck('size')->sort()->values(),
            'conditions' => ['excellent', 'good', 'fair', 'poor'],
            'statuses'   => ['available', 'mine_pending', 'steal_pending', 'grab_pending', 'sold'],
        ]);
    }
}
