<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
        ];
    }
}
