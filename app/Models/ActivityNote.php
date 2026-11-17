<?php

namespace App\Models;

use App\Enums\TripRole;
use Database\Factories\ActivityNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's note on a stop. The author always gets it; trip owners also
 * see everyone's notes (shown like comments, with who wrote them).
 */
#[Fillable(['note'])]
class ActivityNote extends Model
{
    /** @use HasFactory<ActivityNoteFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Other members' notes on this trip's stops that $user may read: all of them
     * for an owner, none for anyone else.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function othersVisibleTo(Builder $query, Trip $trip, User $user): void
    {
        if ($trip->roleFor($user) !== TripRole::Owner) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('user_id', '!=', $user->getKey())
            ->whereIn('activity_id', $trip->activities()->withTrashed()->select('id'));
    }
}
