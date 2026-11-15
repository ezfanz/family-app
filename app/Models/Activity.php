<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['start_time', 'name', 'transport', 'meal', 'ticket', 'notes', 'places', 'is_halal', 'status', 'sort_order'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'transport' => '',
        'meal' => '',
        'ticket' => '',
        'is_halal' => false,
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
            'places' => 'array',
            'is_halal' => 'boolean',
            'status' => ActivityStatus::class,
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
     * @return BelongsTo<Day, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(Day::class);
    }

    /**
     * Members' private notes on this stop.
     *
     * @return HasMany<ActivityNote, $this>
     */
    public function personalNotes(): HasMany
    {
        return $this->hasMany(ActivityNote::class);
    }

    /**
     * Start time as HH:MM regardless of how the driver returns it.
     */
    public function timeLabel(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }
}
