<?php

namespace App\Http\Resources;

use App\Enums\TripRole;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->roleFor($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'timezone' => $this->timezone,
            'exchange_rate_rm_per_100_yen' => (float) $this->exchange_rate_rm_per_100_yen,
            'deposit_rm' => (float) $this->deposit_rm,
            'join_code' => $this->when($role === TripRole::Owner, $this->join_code),
            'my_role' => $role?->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
