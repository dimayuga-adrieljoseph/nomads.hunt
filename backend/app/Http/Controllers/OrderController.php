<?php

namespace App\Http\Controllers;

use App\Exceptions\ClaimException;
use App\Models\Order;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    // ── GET /api/my-orders ────────────────────────────────────────────────────

    public function myOrders(Request $request): JsonResponse
    {
        // Flush deadlines that already passed so the list is never stale
        $this->claimService->expireOverdueClaimsForUser($request->user());

        $orders = Order::where('user_id', $request->user()->id)
            ->with([
                'product:id,name,status',
                'product.primaryImage:id,product_id,image_path,is_primary,sort_order',
                'claim:id,type,status',
            ])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'data' => $orders->items() ? collect($orders->items())->map(fn ($o) => $this->formatOrder($o)) : [],
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    // ── GET /api/orders/{order} ───────────────────────────────────────────────

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Payment page polls this endpoint — reflect expirations immediately
        $this->claimService->expireOverdueClaimsForProduct($order->product_id);

        $order->load([
            'product',
            'product.primaryImage',
            'claim',
            'user:id,name,email',
        ]);

        return response()->json(['data' => $this->formatOrder($order, true)]);
    }

    // ── POST /api/orders/{order}/pay ──────────────────────────────────────────

    /**
     * Simulate payment.
     *
     * All validation lives in ClaimService::pay() — active claim, deadline and
     * product state are re-checked server-side before the sale is completed.
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        // Only the owner can pay
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        try {
            $paid = $this->claimService->pay($order, $request->user());
        } catch (ClaimException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json([
            'message' => 'Payment confirmed. The product is now yours!',
            'data' => $this->formatOrder($paid, true),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatOrder(Order $order, bool $full = false): array
    {
        $data = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'amount' => $order->amount,
            'claim_type' => $order->claim_type,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'paid_at' => $order->paid_at?->toISOString(),
            'expires_at' => $order->expires_at?->toISOString(),
            'created_at' => $order->created_at?->toISOString(),
        ];

        if ($order->relationLoaded('product')) {
            $data['product'] = [
                'id' => $order->product?->id,
                'name' => $order->product?->name,
                'image_url' => $order->product?->primaryImageUrl(),
                'status' => $order->product?->status,
            ];
        }

        if ($order->relationLoaded('claim')) {
            $data['claim'] = $order->claim ? [
                'id' => $order->claim->id,
                'type' => $order->claim->type,
                'status' => $order->claim->status,
                'phase' => $order->claim->phase,
            ] : null;
        }

        if ($full && $order->relationLoaded('user')) {
            $data['user'] = [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
            ];
        }

        return $data;
    }
}
