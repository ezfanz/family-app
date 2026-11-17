<?php

namespace App\Http\Resources;

use App\Models\Receipt;
use App\Models\ReceiptPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eligibility is not included: it depends on the person's other receipts,
 * so the app works it out (taxcalc.js) and can do so offline.
 *
 * @mixin Receipt
 */
class ReceiptResource extends JsonResource
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
            'user_id' => $this->user_id,
            'store_name' => $this->store_name,
            'branch' => $this->branch,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'pre_tax_yen' => $this->pre_tax_yen,
            'tax_rate' => $this->tax_rate,
            'is_estimated' => $this->is_estimated,
            'stage' => $this->stage->value,
            'customs_confirmed_at' => $this->customs_confirmed_at?->toIso8601String(),
            'refund_received_yen' => $this->refund_received_yen,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'reading' => $this->reading,
            'photo_parts' => $this->whenLoaded('photos', fn (): array => $this->photos->map(fn (ReceiptPhoto $photo): int => $photo->part)->values()->all(), []),
            'shopping_item_ids' => $this->whenLoaded('shoppingItems', fn (): array => $this->shoppingItems->modelKeys(), []),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
