<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStopCommentRequest extends FormRequest
{
    /**
     * Membership is checked in the controller (404 for outsiders).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['body' => trim((string) $this->input('body'))]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'body' => ['required', 'string', 'max:1000'],
            'mention_ids' => ['nullable', 'array', 'max:20'],
            'mention_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Type a comment first.',
            'body.max' => 'Keep comments to 1,000 characters.',
        ];
    }
}
