<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinTripRequest extends FormRequest
{
    /**
     * Normalise the code so "ab cd-1234" style input still matches.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->input('code'))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:8', 'exists:trips,join_code'],
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
            'code.required' => 'Type the join code.',
            'code.size' => 'Join codes are 8 letters and numbers.',
            'code.exists' => 'That code doesn’t match a trip. Check it with the organiser.',
        ];
    }
}
