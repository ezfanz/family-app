<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class ChangesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'since' => ['required', 'date'],
        ];
    }

    /**
     * The cut-off as a UTC timestamp.
     */
    public function since(): Carbon
    {
        return Carbon::parse($this->validated('since'))->utc();
    }
}
