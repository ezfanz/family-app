<?php

namespace App\Http\Resources;

use App\Models\ActivityNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityNote
 */
class ActivityNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'activity_id' => $this->activity_id,
            'note' => $this->note,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
