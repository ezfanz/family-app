<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SwapDaysRequest extends FormRequest
{
    /**
     * Who may swap is checked on the route (trip update).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Two different days of this trip.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $ofThisTrip = Rule::exists('days', 'id')->where('trip_id', $this->route('trip')->id);

        return [
            'day_id' => ['required', 'integer', $ofThisTrip],
            'with_day_id' => ['required', 'integer', 'different:day_id', $ofThisTrip],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'with_day_id.different' => 'Pick another day to swap with.',
            'day_id.exists' => 'Pick a day from this trip.',
            'with_day_id.exists' => 'Pick a day from this trip.',
        ];
    }
}
