<?php

namespace App\Models;

use App\Enums\TripRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TripMember extends Pivot
{
    /**
     * The table associated with the pivot.
     *
     * @var string
     */
    protected $table = 'trip_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TripRole::class,
        ];
    }
}
