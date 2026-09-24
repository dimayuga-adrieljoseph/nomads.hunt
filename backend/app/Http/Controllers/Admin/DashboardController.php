<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly ClaimService $claimService) {}

    public function index(): JsonResponse
    {
        // Reflect expirations before counting so the stats are never stale
        $this->claimService->expireOverdueClaims();

        $stats = [
            'available'       => Product::where('status', 'available')->count(),
            'active_claims'   => Claim::where('status', 'active')->count(),
            // Two-stage lifecycle breakdown so admin can tell the phases apart
            'claim_period'    => Claim::where('status', 'active')->where('phase', Claim::PHASE_CLAIM)->count(),
            'payment_windows' => Claim::where('status', 'active')->where('phase', Claim::PHASE_PAYMENT)->count(),
            'waiting_claims'  => Claim::where('status', Claim::STATUS_WAITING)->count(),
            'payment_expired' => Claim::where('status', Claim::STATUS_EXPIRED)->whereNotNull('payment_starts_at')->count(),
            'overridden'      => Claim::where('status', Claim::STATUS_OVERRIDDEN)->count(),
            'pending_payments'=> Order::where('payment_status', 'pending')->count(),
            'sold'            => Product::where('status', 'sold')->count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_products'  => Product::count(),
            'total_orders'    => Order::where('payment_status', 'paid')->count(),
            'total_revenue'   => Order::where('payment_status', 'paid')->sum('amount'),
        ];

        $recentActivity = ActivityLog::with(['user:id,name', 'product:id,name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'product'     => $log->product ? ['id' => $log->product->id, 'name' => $log->product->name] : null,
                'created_at'  => $log->created_at?->toISOString(),
            ]);

        return response()->json([
            'stats'           => $stats,
            'recent_activity' => $recentActivity,
        ]);
    }
}
