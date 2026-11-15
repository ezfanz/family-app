<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    /**
     * Longest trip the app will create days for.
     */
    public const MAX_DAYS = 60;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Closure|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $start = CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('start_date'));
                    $end = CarbonImmutable::createFromFormat('Y-m-d', (string) $value);
                    if ($start && $end && $start->diffInDays($end) + 1 > self::MAX_DAYS) {
                        $fail('Trips can be up to '.self::MAX_DAYS.' days long.');
                    }
                },
            ],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ];
    }
}
