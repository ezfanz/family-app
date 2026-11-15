<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Limits mirror the prototype's add-stop sheet.
 */
class StoreActivityRequest extends FormRequest
{
    /**
     * Meal options offered by the add-stop sheet.
     *
     * @var list<string>
     */
    public const MEALS = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];

    /**
     * Trim text and drop blank places before validating.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['name', 'transport', 'ticket', 'notes', 'meal'] as $field) {
            if ($this->has($field)) {
                $clean[$field] = trim((string) $this->input($field));
            }
        }

        if (is_array($this->input('places'))) {
            $clean['places'] = array_values(array_filter(
                array_map(fn ($place) => is_string($place) ? trim($place) : $place, $this->input('places')),
                fn ($place) => $place !== '' && $place !== null,
            ));
        }

        $this->merge($clean);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'day_id' => ['required', 'integer', 'exists:days,id'],
            ...self::contentRules(),
        ];
    }

    /**
     * Rules for the editable stop fields, shared with updates.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function contentRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'time' => [$required, 'date_format:H:i'],
            'name' => [$required, 'string', 'max:160'],
            'transport' => ['sometimes', 'nullable', 'string', 'max:200'],
            'meal' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', self::MEALS)],
            'ticket' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'places' => ['sometimes', 'nullable', 'array', 'max:10'],
            'places.*' => ['string', 'max:160'],
            'is_halal' => ['sometimes', 'boolean'],
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
            'name.required' => 'Give the stop a name.',
            'time.required' => 'Pick a time.',
            'places.max' => 'Up to 10 places per stop.',
        ];
    }
}
