<?php

namespace App\Http\Controllers;

use App\Exceptions\ClaimException;
use App\Models\Claim;
use App\Models\Product;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    // ── POST /api/products/{product}/mine ─────────────────────────────────────

    public function mine(Request $request, Product $product): JsonResponse
    {
        try {
            $claim = $this->claimService->mine($product, $request->user());

            return response()->json([
                'message' => $claim->status === Claim::STATUS_ACTIVE
                    ? 'Mine claim activated. You have ' . config('app.claim_hold_seconds') . ' seconds to pay.'
                    : 'You have been added to the Mine queue.',
                'claim'   => $this->formatClaim($claim),
            ], 201);
        } catch (ClaimException $e) {
            return response()->json([
                'message'    => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
    }

    // ── POST /api/products/{product}/steal ────────────────────────────────────

    public function steal(Request $request, Product $product): JsonResponse
    {
        try {
            $claim = $this->claimService->steal($product, $request->user());

            return response()->json([
                'message' => $claim->status === Claim::STATUS_ACTIVE
                    ? 'Steal activated! All Mine claims have been overridden. You have ' . config('app.claim_hold_seconds') . ' seconds to pay.'
                    : 'You have been added to the Steal queue.',
                'claim'   => $this->formatClaim($claim),
            ], 201);
        } catch (ClaimException $e) {
            return response()->json([
                'message'    => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
    }

    // ── POST /api/products/{product}/grab ─────────────────────────────────────

    public function grab(Request $request, Product $product): JsonResponse
    {
        try {
            $claim = $this->claimService->grab($product, $request->user());

            return response()->json([
                'message' => 'Grab activated! You have ' . config('app.claim_hold_seconds') . ' seconds to complete payment.',
                'claim'   => $this->formatClaim($claim),
            ], 201);
        } catch (ClaimException $e) {
            return response()->json([
                'message'    => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }
    }

    // ── GET /api/products/{product}/claims ────────────────────────────────────

    /**
     * Return the full claim history for a product (public — used in detail view).
     */
    public function productClaims(Product $product): JsonResponse
    {
        $claims = $product->claims()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => $this->formatClaim($c));

        return response()->json(['data' => $claims]);
    }

    // ── GET /api/my-claims ────────────────────────────────────────────────────

    /**
     * Return the authenticated customer's claims grouped by status.
     */
    public function myClaims(Request $request): JsonResponse
    {
        $claims = Claim::where('user_id', $request->user()->id)
            ->with(['product:id,name,image,status', 'order'])
            ->orderByDesc('created_at')
            ->get();

        $active    = $claims->where('status', Claim::STATUS_ACTIVE)->values();
        $waiting   = $claims->where('status', Claim::STATUS_WAITING)->values();
        $completed = $claims->where('status', Claim::STATUS_COMPLETED)->values();
        $expired   = $claims->whereIn('status', [Claim::STATUS_EXPIRED, Claim::STATUS_OVERRIDDEN, Claim::STATUS_CANCELLED])->values();

        return response()->json([
            'active'    => $active->map(fn ($c) => $this->formatClaim($c, true)),
            'waiting'   => $waiting->map(fn ($c) => $this->formatClaim($c, true)),
            'completed' => $completed->map(fn ($c) => $this->formatClaim($c, true)),
            'expired'   => $expired->map(fn ($c) => $this->formatClaim($c, true)),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatClaim(Claim $claim, bool $withOrder = false): array
    {
        $data = [
            'id'         => $claim->id,
            'product_id' => $claim->product_id,
            'user_id'    => $claim->user_id,
            'user_name'  => $claim->relationLoaded('user') ? $claim->user?->name : null,
            'type'       => $claim->type,
            'position'   => $claim->position,
            'status'     => $claim->status,
            'amount'     => $claim->amount,
            'expires_at' => $claim->expires_at?->toISOString(),
            'created_at' => $claim->created_at?->toISOString(),
        ];

        if ($claim->relationLoaded('product')) {
            $data['product'] = [
                'id'        => $claim->product?->id,
                'name'      => $claim->product?->name,
                'image_url' => $claim->product?->image
                    ? asset('storage/products/' . $claim->product->image)
                    : null,
                'status'    => $claim->product?->status,
            ];
        }

        if ($withOrder && $claim->relationLoaded('order')) {
            $data['order'] = $claim->order ? [
                'id'             => $claim->order->id,
                'order_number'   => $claim->order->order_number,
                'payment_status' => $claim->order->payment_status,
                'status'         => $claim->order->status,
                'expires_at'     => $claim->order->expires_at?->toISOString(),
            ] : null;
        }

        return $data;
    }
}
