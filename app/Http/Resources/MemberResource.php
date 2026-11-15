<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A trip member as seen by another member. Emails are only shown to owners.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * Whether the person asking may see contact details.
     */
    public bool $showEmail = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($this->showEmail, $this->email),
            'role' => $this->pivot->role->value,
            'is_me' => $this->is($request->user()),
        ];
    }
}
