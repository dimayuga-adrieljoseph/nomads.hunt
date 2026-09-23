<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with([
            'user:id,name',
            'product:id,name',
        ]);

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50);

        return response()->json([
            'data' => collect($logs->items())->map(fn ($log) => [
                'id'          => $log->id,
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'product'     => $log->product ? ['id' => $log->product->id, 'name' => $log->product->name] : null,
                'claim_id'    => $log->claim_id,
                'created_at'  => $log->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}
