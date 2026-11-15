<?php

namespace App\Http\Resources;

use App\Models\Day;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Day
 */
class DayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'date' => $this->date->toDateString(),
            'city' => $this->city,
            'title' => $this->title,
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
