<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateActivityRequest extends StoreActivityRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'day_id' => ['sometimes', 'integer', 'exists:days,id'],
            ...self::contentRules(partial: true),
        ];
    }
}
