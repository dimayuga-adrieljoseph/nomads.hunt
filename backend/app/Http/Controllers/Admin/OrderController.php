<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'user:id,name,email',
            'product:id,name,status',
            'claim:id,type,status',
        ]);

        if ($status = $request->input('payment_status')) {
            $query->where('payment_status', $status);
        }

        if ($type = $request->input('claim_type')) {
            $query->where('claim_type', $type);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $orders = $query->orderByDesc('created_at')->paginate(25);

        return response()->json([
            'data' => collect($orders->items())->map(fn ($o) => $this->formatOrder($o)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'product', 'claim']);

        return response()->json(['data' => $this->formatOrder($order, true)]);
    }

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

        if ($order->relationLoaded('user')) {
            $data['user'] = $order->user ? [
                'id'    => $order->user->id,
                'name'  => $order->user->name,
                'email' => $order->user->email,
            ] : null;
        }

        if ($order->relationLoaded('product')) {
            $data['product'] = $order->product ? [
                'id'     => $order->product->id,
                'name'   => $order->product->name,
                'status' => $order->product->status,
            ] : null;
        }

        if ($order->relationLoaded('claim')) {
            $data['claim'] = $order->claim ? [
                'id'     => $order->claim->id,
                'type'   => $order->claim->type,
                'status' => $order->claim->status,
            ] : null;
        }

        return $data;
    }
}
