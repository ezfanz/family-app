<?php

namespace App\Models;

use App\Enums\TripRole;
use Database\Factories\StopCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a stop. Trip owners read every comment; anyone else reads
 * their own and the ones that @mention them.
 */
#[Fillable(['body', 'mention_ids'])]
class StopComment extends Model
{
    /** @use HasFactory<StopCommentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'mention_ids' => 'array',
        ];
    }

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
     * Comments on the trip's stops that $user may read.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Trip $trip, User $user): void
    {
        $query->whereIn('activity_id', $trip->activities()->withTrashed()->select('id'));

        if ($trip->roleFor($user) !== TripRole::Owner) {
            $query->where(fn (Builder $mine) => $mine
                ->where('user_id', $user->getKey())
                ->orWhereJsonContains('mention_ids', $user->getKey()));
        }
    }
}
