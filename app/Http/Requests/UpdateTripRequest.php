<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'exchange_rate_rm_per_100_yen' => ['sometimes', 'numeric', 'gt:0', 'max:1000'],
            'exchange_rate_auto' => ['sometimes', 'boolean'],
            'deposit_rm' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
