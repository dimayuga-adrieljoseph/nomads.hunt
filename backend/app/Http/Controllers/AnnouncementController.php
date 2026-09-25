<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicAnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Public compatibility endpoint. The homepage is intentionally single-slot,
     * so this returns the same deterministic active payload as /active.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->activeResponse($request);
    }

    public function active(Request $request): JsonResponse
    {
        return $this->activeResponse($request);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isEligible(), 404);

        return response()->json([
            'data' => (new PublicAnnouncementResource($announcement))->resolve($request),
        ]);
    }

    private function activeResponse(Request $request): JsonResponse
    {
        $announcement = Announcement::active()->first();

        return response()->json([
            'data' => $announcement
                ? (new PublicAnnouncementResource($announcement))->resolve($request)
                : null,
        ])->header('Cache-Control', 'no-store');
    }
}
