<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Product;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    /**
     * List all claims with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        // Keep the monitor view honest — release deadlines that already passed
        $this->claimService->expireOverdueClaims();

        $query = Claim::with(['product:id,name,status', 'user:id,name,email']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $claims = $query->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => collect($claims->items())->map(fn ($c) => $this->formatClaim($c)),
            'meta' => [
                'current_page' => $claims->currentPage(),
                'last_page'    => $claims->lastPage(),
                'total'        => $claims->total(),
            ],
        ]);
    }

    /**
     * Full claim history for a specific product.
     */
    public function productClaims(Product $product): JsonResponse
    {
        $this->claimService->expireOverdueClaimsForProduct($product->id);
        $this->claimService->reconcileProductStatus($product);

        $claims = $product->claims()
            ->with(['user:id,name,email', 'order'])
            ->orderBy('type')
            ->orderBy('position')
            ->get()
            ->map(fn ($c) => $this->formatClaim($c, true));

        $activeClaim = $product->activeClaim();

        return response()->json([
            'product' => [
                'id'     => $product->id,
                'name'   => $product->name,
                'status' => $product->status,
            ],
            'active_claim' => $activeClaim ? $this->formatClaim($activeClaim->load('user', 'order'), true) : null,
            'claims'       => $claims,
        ]);
    }

    /**
     * Force the CURRENT STAGE of an active claim to finish immediately.
     *
     * Two-stage lifecycle:
     *   claim phase   → jumps straight into the payment window for the same user
     *   payment phase → expires the claim and activates the next queued claimant
     *
     * Runs exactly the same backend logic as a real timeout.
     */
    public function forceExpire(Claim $claim): JsonResponse
    {
        if ($claim->status !== Claim::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'Only active claims can be advanced.',
            ], 422);
        }

        $stage = $claim->phase;

        $this->claimService->expireClaim($claim);

        $claim->refresh();

        return response()->json([
            'message' => $stage === Claim::PHASE_CLAIM
                ? 'Claim period ended — the payment window for this claimant is now open.'
                : 'Payment window expired — the next queued claimant (if any) is now active.',
            'claim' => [
                'id'                 => $claim->id,
                'status'             => $claim->status,
                'phase'              => $claim->phase,
                'payment_expires_at' => $claim->payment_expires_at?->toISOString(),
            ],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatClaim(Claim $claim, bool $withOrder = false): array
    {
        $data = [
            'id'         => $claim->id,
            'product_id' => $claim->product_id,
            'user_id'    => $claim->user_id,
            'type'       => $claim->type,
            'position'   => $claim->position,
            'status'     => $claim->status,
            'amount'     => $claim->amount,
            // ── Two-stage lifecycle ───────────────────────────────────────────
            'phase'              => $claim->phase,
            'claim_expires_at'   => $claim->claim_expires_at?->toISOString(),
            'payment_starts_at'  => $claim->payment_starts_at?->toISOString(),
            'payment_expires_at' => $claim->payment_expires_at?->toISOString(),
            'can_pay'            => $claim->canPay(),
            // `expires_at` = deadline of the CURRENT phase (claim or payment)
            'expires_at' => $claim->expires_at?->toISOString(),
            'created_at' => $claim->created_at?->toISOString(),
        ];

        if ($claim->relationLoaded('user')) {
            $data['user'] = $claim->user ? [
                'id'    => $claim->user->id,
                'name'  => $claim->user->name,
                'email' => $claim->user->email,
            ] : null;
        }

        if ($claim->relationLoaded('product')) {
            $data['product'] = $claim->product ? [
                'id'     => $claim->product->id,
                'name'   => $claim->product->name,
                'status' => $claim->product->status,
            ] : null;
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
