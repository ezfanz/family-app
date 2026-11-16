<?php

namespace App\Http\Requests;

class UpdateReceiptRequest extends StoreReceiptRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer'],
            ...self::contentRules(partial: true),
        ];
    }
}
