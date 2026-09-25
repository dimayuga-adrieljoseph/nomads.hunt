<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'brand' => $this->brand,
            'size' => $this->size,
            'condition' => $this->condition,
            // Flat compatibility field for list/card consumers. It is resolved
            // from the primary relationship, never from products.image.
            'image_url' => $this->primaryImageUrl(),
            'images' => $this->whenLoaded(
                'images',
                fn () => ProductImageResource::collection($this->images),
            ),
            'mine_price' => $this->mine_price,
            'steal_price' => $this->steal_price,
            'grab_price' => $this->grab_price,
            'status' => $this->status,
            'is_liked' => (bool) ($this->is_liked ?? false),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
