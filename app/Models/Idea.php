<?php

namespace App\Models;

use Database\Factories\IdeaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['region', 'name', 'city', 'area', 'is_visited', 'planned_day_id', 'sort_order'])]
class Idea extends Model
{
    /** @use HasFactory<IdeaFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visited' => 'boolean',
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
     * The day this idea was planned onto, if any.
     *
     * @return BelongsTo<Day, $this>
     */
    public function plannedDay(): BelongsTo
    {
        return $this->belongsTo(Day::class, 'planned_day_id');
    }
}
