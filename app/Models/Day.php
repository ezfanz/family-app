<?php

namespace App\Models;

use Database\Factories\DayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['day_number', 'date', 'city', 'title'])]
class Day extends Model
{
    /** @use HasFactory<DayFactory> */
    use HasFactory;

    /**
     * Cities with a colour on the day strip.
     *
     * @var list<string>
     */
    public const CITIES = ['Osaka', 'Nara', 'Kyoto', 'Tokyo', 'Fuji'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
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
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('start_time')->orderBy('sort_order');
    }
}
