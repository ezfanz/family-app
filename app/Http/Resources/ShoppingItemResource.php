<?php

namespace App\Http\Resources;

use App\Models\ShoppingItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShoppingItem
 */
class ShoppingItemResource extends JsonResource
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
            'name' => $this->name,
            'for_whom' => $this->for_whom,
            'qty' => $this->qty,
            'estimated_yen' => $this->estimated_yen,
            'actual_yen' => $this->actual_yen,
            'is_bought' => $this->is_bought,
            'sort_order' => $this->sort_order,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
