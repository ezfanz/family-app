<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIdeaRequest extends FormRequest
{
    /**
     * Trim text fields before validating.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['name', 'region', 'city', 'area'] as $field) {
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
        return self::contentRules();
    }

    /**
     * Rules for the editable idea fields, shared with updates.
     *
     * @return array<string, array<int, string>>
     */
    public static function contentRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:160'],
            'region' => [$required, 'string', 'max:32'],
            'city' => ['sometimes', 'nullable', 'string', 'max:32'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'is_visited' => ['sometimes', 'boolean'],
            'planned_day_id' => ['sometimes', 'nullable', 'integer', 'exists:days,id'],
        ];
    }
}
