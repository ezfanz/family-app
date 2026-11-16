<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAirportChecklistRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'checked' => ['present', 'array', 'max:20'],
            'checked.*' => ['string', 'max:30', 'regex:/^[a-z_]+$/'],
        ];
    }
}
