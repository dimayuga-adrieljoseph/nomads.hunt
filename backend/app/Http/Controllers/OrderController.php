<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    // ── GET /api/my-orders ────────────────────────────────────────────────────

    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['product:id,name,image', 'claim:id,type,status'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'data'  => $orders->items() ? collect($orders->items())->map(fn ($o) => $this->formatOrder($o)) : [],
            'meta'  => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    // ── GET /api/orders/{order} ───────────────────────────────────────────────

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $order->load(['product', 'claim', 'user:id,name,email']);

        return response()->json(['data' => $this->formatOrder($order, true)]);
    }

    // ── POST /api/orders/{order}/pay ──────────────────────────────────────────

    /**
     * Simulate payment — validates server-side then marks order as PAID and product as SOLD.
     */
    public function pay(Request $request, Order $order): JsonResponse
    {
        // Only the owner can pay
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($order->payment_status !== Order::PAYMENT_PENDING) {
            $msg = match ($order->payment_status) {
                Order::PAYMENT_PAID      => 'This order has already been paid.',
                Order::PAYMENT_EXPIRED   => 'This payment window has expired.',
                Order::PAYMENT_CANCELLED => 'This order has been cancelled.',
                default                  => 'This action is no longer available.',
            };
            return response()->json(['message' => $msg], 422);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json(['message' => 'This order is no longer pending.'], 422);
        }

        return DB::transaction(function () use ($order, $request) {
            // Lock order and product
            $order   = Order::lockForUpdate()->findOrFail($order->id);
            $product = Product::lockForUpdate()->findOrFail($order->product_id);
            $claim   = $order->claim_id
                ? Claim::lockForUpdate()->findOrFail($order->claim_id)
                : null;

            // Re-validate: product not already sold
            if ($product->isSold()) {
                return response()->json(['message' => 'This product has already been sold.'], 422);
            }

            // Re-validate: claim not expired
            if ($claim && $claim->isExpired()) {
                return response()->json(['message' => 'Your claim has expired.'], 422);
            }

            // Re-validate: payment deadline not passed (use order expires_at as authoritative)
            if ($order->expires_at && now()->isAfter($order->expires_at)) {
                return response()->json(['message' => 'This payment window has expired.'], 422);
            }

            // All checks pass — complete the transaction
            $now = now();

            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'status'         => Order::STATUS_COMPLETED,
                'paid_at'        => $now,
            ]);

            if ($claim) {
                $claim->update(['status' => Claim::STATUS_COMPLETED]);

                // Cancel all other active/waiting claims for this product
                Claim::where('product_id', $product->id)
                    ->where('id', '!=', $claim->id)
                    ->whereIn('status', [Claim::STATUS_ACTIVE, Claim::STATUS_WAITING])
                    ->update(['status' => Claim::STATUS_CANCELLED]);

                // Cancel their pending orders too
                Order::where('product_id', $product->id)
                    ->where('id', '!=', $order->id)
                    ->where('payment_status', Order::PAYMENT_PENDING)
                    ->update([
                        'payment_status' => Order::PAYMENT_CANCELLED,
                        'status'         => Order::STATUS_CANCELLED,
                    ]);
            }

            $product->update(['status' => Product::STATUS_SOLD]);

            ActivityLog::create([
                'user_id'     => $request->user()->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim?->id,
                'action'      => ActivityLog::PAYMENT_CONFIRMED,
                'description' => "{$request->user()->name} paid for \"{$product->name}\" via " . strtoupper($order->claim_type),
            ]);

            ActivityLog::create([
                'user_id'     => $request->user()->id,
                'product_id'  => $product->id,
                'claim_id'    => $claim?->id,
                'action'      => ActivityLog::PRODUCT_SOLD,
                'description' => "\"{$product->name}\" has been sold to {$request->user()->name}",
            ]);

            $order->load(['product', 'claim', 'user:id,name,email']);

            return response()->json([
                'message' => 'Payment confirmed. The product is now yours!',
                'data'    => $this->formatOrder($order, true),
            ]);
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function formatOrder(Order $order, bool $full = false): array
    {
        $data = [
            'id'             => $order->id,
            'order_number'   => $order->order_number,
            'amount'         => $order->amount,
            'claim_type'     => $order->claim_type,
            'payment_status' => $order->payment_status,
            'status'         => $order->status,
            'paid_at'        => $order->paid_at?->toISOString(),
            'expires_at'     => $order->expires_at?->toISOString(),
            'created_at'     => $order->created_at?->toISOString(),
        ];

        if ($order->relationLoaded('product')) {
            $data['product'] = [
                'id'        => $order->product?->id,
                'name'      => $order->product?->name,
                'image_url' => $order->product?->image
                    ? asset('storage/products/' . $order->product->image)
                    : null,
                'status'    => $order->product?->status,
            ];
        }

        if ($order->relationLoaded('claim')) {
            $data['claim'] = $order->claim ? [
                'id'     => $order->claim->id,
                'type'   => $order->claim->type,
                'status' => $order->claim->status,
            ] : null;
        }

        if ($full && $order->relationLoaded('user')) {
            $data['user'] = [
                'id'    => $order->user?->id,
                'name'  => $order->user?->name,
                'email' => $order->user?->email,
            ];
        }

        return $data;
    }
}
