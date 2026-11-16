<?php

namespace App\Models;

use App\Enums\TripRole;
use Database\Factories\ShoppingItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'for_whom', 'qty', 'estimated_yen', 'actual_yen', 'is_bought', 'sort_order'])]
class ShoppingItem extends Model
{
    /** @use HasFactory<ShoppingItemFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'qty' => 1,
        'estimated_yen' => 0,
        'is_bought' => false,
        'sort_order' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'estimated_yen' => 'integer',
            'actual_yen' => 'integer',
            'is_bought' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * The member whose shopping list this item is on.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Items the given member may see: owners see everyone's, others only their own.
     *
     * @param  Builder<ShoppingItem>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Trip $trip, User $user): void
    {
        if ($trip->roleFor($user) !== TripRole::Owner) {
            $query->where('user_id', $user->getKey());
        }
    }
}
