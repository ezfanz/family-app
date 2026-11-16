<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportShoppingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx'],
            'confirm' => ['sometimes', 'boolean'],
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
            'file.required' => 'Choose an Excel file first.',
            'file.max' => 'The file is bigger than 2 MB.',
            'file.extensions' => 'Only Excel files (.xlsx) can be imported.',
        ];
    }
}
