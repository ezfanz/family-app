<?php

namespace App\Http\Requests;

class UpdateIdeaRequest extends StoreIdeaRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return self::contentRules(partial: true);
    }
}
