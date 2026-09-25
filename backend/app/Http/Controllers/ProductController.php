<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    /**
     * Public catalog — supports search, filter, pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // Deadlines that already passed must be released before the rack is read
        $this->claimService->expireOverdueClaims();

        $query = Product::query()->with('primaryImage');

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

        // Resolve an optional Sanctum user so the catalog can include user-specific
        // like state without one request per product card.
        if ($user = $request->user('sanctum')) {
            $query->withExists([
                'likes as is_liked' => fn ($likes) => $likes->where('user_id', $user->id),
            ]);
        }

        $products = $query->latest()->paginate(12);

        return ProductResource::collection($products);
    }

    /**
     * Public product detail — includes active claim info for UI rendering.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        // Release anything that expired, then make sure the status matches reality
        $this->claimService->expireOverdueClaimsForProduct($product->id);
        $this->claimService->reconcileProductStatus($product);
        $product->load('images');

        if ($user = $request->user('sanctum')) {
            $product->loadExists([
                'likes as is_liked' => fn ($likes) => $likes->where('user_id', $user->id),
            ]);
        }

        $activeClaim = $product->activeClaim();

        $data = (new ProductResource($product))->toArray($request);

        // Expose just enough claim context for the UI to render correctly
        $data['active_claim'] = $activeClaim ? [
            'id' => $activeClaim->id,
            'type' => $activeClaim->type,
            'user_id' => $activeClaim->user_id,
            'status' => $activeClaim->status,
            'phase' => $activeClaim->phase,
            'claim_expires_at' => $activeClaim->claim_expires_at?->toISOString(),
            'payment_starts_at' => $activeClaim->payment_starts_at?->toISOString(),
            'payment_expires_at' => $activeClaim->payment_expires_at?->toISOString(),
            'expires_at' => $activeClaim->expires_at?->toISOString(),
        ] : null;

        // Mine queue count (WAITING mines — shows position context)
        $data['mine_queue_count'] = $product->claims()
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
            'sizes' => Product::distinct()->whereNotNull('size')->pluck('size')->sort()->values(),
            'conditions' => ['excellent', 'good', 'fair', 'poor'],
            'statuses' => ['available', 'mine_pending', 'steal_pending', 'grab_pending', 'sold'],
        ]);
    }
}
