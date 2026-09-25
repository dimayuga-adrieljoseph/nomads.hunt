<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * List all customers with summary stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'customer')
            ->withCount(['claims', 'orders'])
            ->withSum(['orders as total_spent' => fn ($q) => $q->where('payment_status', 'paid')], 'amount');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => collect($customers->items())->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'claims_count' => $u->claims_count,
                'orders_count' => $u->orders_count,
                'total_spent' => $u->total_spent ?? 0,
                'created_at' => $u->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Customer detail with full order and claim history.
     */
    public function show(User $user): JsonResponse
    {
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Not a customer account.'], 404);
        }

        $orders = $user->orders()
            ->with([
                'product:id,name,status',
                'product.primaryImage:id,product_id,image_path,is_primary,sort_order',
                'claim:id,type,status',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'amount' => $o->amount,
                'claim_type' => $o->claim_type,
                'payment_status' => $o->payment_status,
                'status' => $o->status,
                'paid_at' => $o->paid_at?->toISOString(),
                'created_at' => $o->created_at?->toISOString(),
                'product' => $o->product ? [
                    'id' => $o->product->id,
                    'name' => $o->product->name,
                    'image_url' => $o->product->primaryImageUrl(),
                    'status' => $o->product->status,
                ] : null,
            ]);

        $claims = $user->claims()
            ->with('product:id,name,status')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'status' => $c->status,
                'amount' => $c->amount,
                'position' => $c->position,
                // ── Two-stage lifecycle ───────────────────────────────────────
                'phase' => $c->phase,
                'claim_expires_at' => $c->claim_expires_at?->toISOString(),
                'payment_starts_at' => $c->payment_starts_at?->toISOString(),
                'payment_expires_at' => $c->payment_expires_at?->toISOString(),
                'can_pay' => $c->canPay(),
                'expires_at' => $c->expires_at?->toISOString(),
                'created_at' => $c->created_at?->toISOString(),
                'product' => $c->product ? [
                    'id' => $c->product->id,
                    'name' => $c->product->name,
                    'status' => $c->product->status,
                ] : null,
            ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toISOString(),
                'total_spent' => $user->orders()
                    ->where('payment_status', 'paid')
                    ->sum('amount'),
                'orders' => $orders,
                'claims' => $claims,
            ],
        ]);
    }
}
