<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as a shopper: name, role and personal budget.
 *
 * @mixin User
 */
class ShopperResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->id,
            'name' => $this->name,
            'role' => $this->pivot->role->value,
            'budget_rm' => (float) $this->pivot->budget_rm,
            'is_me' => $this->is($request->user()),
            'updated_at' => $this->pivot->updated_at?->toIso8601String(),
        ];
    }
}
