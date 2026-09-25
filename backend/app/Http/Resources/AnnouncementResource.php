<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $homepageId = $request->attributes->get('homepage_announcement_id');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'label' => $this->label,
            'description' => $this->description,
            'event_date' => $this->event_date?->toDateString(),
            'event_time' => $this->event_time,
            'location' => $this->location,
            'image_url' => $this->image
                ? asset('storage/announcements/'.$this->image)
                : null,
            'status' => $this->status,
            'display_status' => $this->displayStatus(),
            'priority' => $this->priority,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'is_eligible' => $this->isEligible(),
            'is_active' => $homepageId !== null
                ? (int) $homepageId === (int) $this->id
                : $this->isEligible(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
