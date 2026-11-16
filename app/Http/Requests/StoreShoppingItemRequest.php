<?php

namespace App\Http\Requests;

use App\Enums\ShoppingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShoppingItemRequest extends FormRequest
{
    /**
     * Trim text fields before validating.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['name', 'store', 'details', 'for_whom', 'link', 'notes'] as $field) {
            if ($this->has($field)) {
                $clean[$field] = trim((string) $this->input($field));
            }
        }
        $this->merge($clean);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
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
     * Rules for the editable item fields, shared with updates.
     *
     * @return array<string, array<int, string>>
     */
    public static function contentRules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'store' => ['sometimes', 'nullable', 'string', 'max:60'],
            'details' => ['sometimes', 'nullable', 'string', 'max:160'],
            'for_whom' => ['sometimes', 'nullable', 'string', 'max:80'],
            'category' => ['sometimes', Rule::enum(ShoppingCategory::class)],
            'link' => ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'qty' => ['sometimes', 'integer', 'min:1', 'max:999'],
            'estimated_yen' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'actual_yen' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'is_bought' => ['sometimes', 'boolean'],
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
            'name.required' => 'Give the item a name.',
        ];
    }
}
