<?php

namespace App\Http\Requests;

use App\Enums\ReceiptStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceiptRequest extends FormRequest
{
    /**
     * Trim text fields before validating.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['store_name', 'branch'] as $field) {
            if ($this->has($field)) {
                $clean[$field] = trim((string) $this->input($field));
            }
        }
        $this->merge($clean);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'user_id' => ['nullable', 'integer'],
            ...self::contentRules(),
        ];
    }

    /**
     * Rules for the editable receipt fields, shared with updates.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function contentRules(bool $partial = false): array
    {
        return [
            'store_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'branch' => ['sometimes', 'nullable', 'string', 'max:80'],
            'purchased_at' => [$partial ? 'sometimes' : 'required', 'date'],
            'pre_tax_yen' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000000'],
            'tax_rate' => ['sometimes', 'integer', Rule::in(config('tax_refund.rates'))],
            'is_estimated' => ['sometimes', 'boolean'],
            'stage' => ['sometimes', Rule::enum(ReceiptStage::class)],
            'refund_received_yen' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'shopping_item_ids' => ['sometimes', 'array', 'max:50'],
            'shopping_item_ids.*' => ['uuid'],
            'new_items' => ['sometimes', 'array', 'max:40'],
            'new_items.*.id' => ['required', 'uuid'],
            'new_items.*.name' => ['required', 'string', 'max:160'],
            'new_items.*.details' => ['nullable', 'string', 'max:160'],
            'new_items.*.qty' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'new_items.*.price_yen' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'new_items.*.reduced_rate' => ['sometimes', 'boolean'],
            'new_items_for' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchased_at.required' => 'Add the date and time of the purchase.',
            'pre_tax_yen.min' => 'Enter the amount in yen.',
            'tax_rate.in' => 'Tax is 10%, or 8% for food and drink.',
        ];
    }
}
