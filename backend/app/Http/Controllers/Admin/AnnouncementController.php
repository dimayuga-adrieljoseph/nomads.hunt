<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::query();

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $status = strtoupper((string) $request->input('status', ''));
        if ($status === Announcement::DISPLAY_ACTIVE) {
            $query->active();
        } elseif ($status === Announcement::DISPLAY_SCHEDULED) {
            $query->scheduled();
        } elseif ($status === Announcement::DISPLAY_EXPIRED) {
            $query->expired();
        } elseif (in_array($status, [
            Announcement::STATUS_DRAFT,
            Announcement::STATUS_PUBLISHED,
            Announcement::STATUS_ARCHIVED,
        ], true)) {
            $query->where('status', $status);
        }

        $this->setHomepageContext($request);

        return AnnouncementResource::collection(
            $query->orderByDesc('priority')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(20)
        );
    }

    public function show(Request $request, Announcement $announcement): AnnouncementResource
    {
        $this->setHomepageContext($request);

        return new AnnouncementResource($announcement);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateAnnouncement($request);
        $image = $validated['image'] ?? null;
        unset($validated['image'], $validated['remove_image']);

        $validated['status'] = $validated['status'] ?? Announcement::STATUS_DRAFT;
        $validated['priority'] = $validated['priority'] ?? 0;

        $announcement = Announcement::create($validated);

        if ($image) {
            $this->storeImage($announcement, $image);
        }

        $this->setHomepageContext($request);

        return response()->json([
            'data' => (new AnnouncementResource($announcement->fresh()))->resolve($request),
        ], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $this->validateAnnouncement($request);
        $image = $validated['image'] ?? null;
        $removeImage = (bool) ($validated['remove_image'] ?? false);
        unset($validated['image'], $validated['remove_image']);

        if ($removeImage && ! $image) {
            $this->deleteImageFile($announcement->image);
            $announcement->image = null;
        }

        $announcement->fill($validated);
        $announcement->save();

        if ($image) {
            $this->storeImage($announcement, $image);
        }

        $this->setHomepageContext($request);

        return response()->json([
            'data' => (new AnnouncementResource($announcement->fresh()))->resolve($request),
        ]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->deleteImageFile($announcement->image);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function uploadImage(Request $request, Announcement $announcement): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $this->storeImage($announcement, $request->file('image'));
        $this->setHomepageContext($request);

        return response()->json([
            'data' => (new AnnouncementResource($announcement->fresh()))->resolve($request),
        ]);
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->changeStatus($request, $announcement, Announcement::STATUS_PUBLISHED);
    }

    public function unpublish(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->changeStatus($request, $announcement, Announcement::STATUS_DRAFT);
    }

    public function archive(Request $request, Announcement $announcement): JsonResponse
    {
        return $this->changeStatus($request, $announcement, Announcement::STATUS_ARCHIVED);
    }

    private function changeStatus(
        Request $request,
        Announcement $announcement,
        string $status
    ): JsonResponse {
        $announcement->update(['status' => $status]);
        $this->setHomepageContext($request);

        return response()->json([
            'message' => 'Announcement status updated.',
            'data' => (new AnnouncementResource($announcement->fresh()))->resolve($request),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateAnnouncement(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([
                Announcement::STATUS_DRAFT,
                Announcement::STATUS_PUBLISHED,
                Announcement::STATUS_ARCHIVED,
            ])],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:4096', 'mimes:jpg,jpeg,png,webp'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $startsAt = $request->input('starts_at');
            $endsAt = $request->input('ends_at');

            if (! $startsAt || ! $endsAt) {
                return;
            }

            $start = strtotime($startsAt);
            $end = strtotime($endsAt);

            if ($start !== false && $end !== false && $start >= $end) {
                $validator->errors()->add(
                    'ends_at',
                    'Stop Showing must be after Start Showing.'
                );
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function storeImage(Announcement $announcement, UploadedFile $file): void
    {
        $oldImage = $announcement->image;
        $filename = 'announcement_'.uniqid('', true).'.'.strtolower($file->extension());

        if (! $file->storeAs('announcements', $filename, 'public')) {
            throw new RuntimeException('Unable to store announcement image.');
        }

        $announcement->update(['image' => $filename]);
        $this->deleteImageFile($oldImage);
    }

    private function deleteImageFile(?string $image): void
    {
        if ($image) {
            Storage::disk('public')->delete('announcements/'.$image);
        }
    }

    private function setHomepageContext(Request $request): void
    {
        $request->attributes->set(
            'homepage_announcement_id',
            Announcement::active()->value('id')
        );
    }
}
