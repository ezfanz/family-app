<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared stop data. Private notes are sent separately as `my_notes`.
 *
 * @mixin Activity
 */
class ActivityResource extends JsonResource
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
            'day_id' => $this->day_id,
            'time' => $this->timeLabel(),
            'name' => $this->name,
            'transport' => $this->transport,
            'meal' => $this->meal,
            'ticket' => $this->ticket,
            'notes' => $this->notes ?? '',
            'places' => $this->places ?? [],
            'is_halal' => $this->is_halal,
            'status' => $this->status->value,
            'system_key' => $this->system_key,
            'sort_order' => $this->sort_order,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
