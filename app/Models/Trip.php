<?php

namespace App\Models;

use App\Enums\TripRole;
use Carbon\CarbonPeriod;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'start_date', 'end_date', 'timezone', 'exchange_rate_rm_per_100_yen', 'exchange_rate_auto', 'deposit_rm'])]
class Trip extends Model
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    /**
     * Characters used for join codes. Look-alikes (0/O, 1/I/L) are left out.
     */
    private const JOIN_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * Roles already looked up on this instance, keyed by user id.
     *
     * @var array<int, TripRole|null>
     */
    private array $roleCache = [];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Trip $trip): void {
            $trip->join_code ??= static::generateUniqueJoinCode();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'exchange_rate_rm_per_100_yen' => 'decimal:4',
            'exchange_rate_auto' => 'boolean',
            'deposit_rm' => 'decimal:2',
        ];
    }

    /**
     * Generate an 8-character join code that is not in use.
     */
    public static function generateUniqueJoinCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::JOIN_CODE_ALPHABET[random_int(0, strlen(self::JOIN_CODE_ALPHABET) - 1)];
            }
        } while (static::where('join_code', $code)->exists());

        return $code;
    }

    /**
     * Start a new trip owned by the given user, with one blank day per date.
     *
     * @param  array{name: string, start_date: string, end_date: string, timezone?: string|null}  $attributes
     */
    public static function startFor(User $owner, array $attributes): self
    {
        return DB::transaction(function () use ($owner, $attributes): self {
            $trip = static::create([
                'name' => $attributes['name'],
                'start_date' => $attributes['start_date'],
                'end_date' => $attributes['end_date'],
                'timezone' => $attributes['timezone'] ?? 'Asia/Tokyo',
            ]);

            $period = CarbonPeriod::create($trip->start_date, $trip->end_date);
            foreach ($period as $index => $date) {
                $trip->days()->create([
                    'day_number' => $index + 1,
                    'date' => $date->toDateString(),
                    'city' => null,
                    'title' => '',
                ]);
            }

            $trip->addMember($owner, TripRole::Owner);

            return $trip;
        });
    }

    /**
     * Replace the join code with a fresh one.
     */
    public function regenerateJoinCode(): void
    {
        $this->forceFill(['join_code' => static::generateUniqueJoinCode()])->save();
    }

    /**
     * @return BelongsToMany<User, $this, TripMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TripMember::class)
            ->withPivot('role', 'budget_rm')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Day, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(Day::class)->orderBy('day_number');
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<ShoppingItem, $this>
     */
    public function shoppingItems(): HasMany
    {
        return $this->hasMany(ShoppingItem::class)->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * @return HasMany<Idea, $this>
     */
    public function ideas(): HasMany
    {
        return $this->hasMany(Idea::class)->orderBy('sort_order');
    }

    /**
     * Members whose shopping the given user may see: everyone for the owner, otherwise only themselves.
     *
     * @return Collection<int, User>
     */
    public function shoppersVisibleTo(User $user): Collection
    {
        $query = $this->members()->orderByPivot('id');

        if ($this->roleFor($user) !== TripRole::Owner) {
            $query->whereKey($user->getKey());
        }

        return $query->get();
    }

    /**
     * The given user's role on this trip, or null when they are not a member.
     * Cached per instance because policies ask several times per request.
     */
    public function roleFor(User $user): ?TripRole
    {
        if (! array_key_exists($user->getKey(), $this->roleCache)) {
            $member = $this->members()->whereKey($user->getKey())->first();
            $this->roleCache[$user->getKey()] = $member?->pivot->role;
        }

        return $this->roleCache[$user->getKey()];
    }

    /**
     * Add a user to the trip. Returns false when they were already a member.
     */
    public function addMember(User $user, TripRole $role): bool
    {
        if ($this->roleFor($user) !== null) {
            return false;
        }

        $this->members()->attach($user, ['role' => $role->value]);
        $this->roleCache[$user->getKey()] = $role;

        return true;
    }

    /**
     * Change a member's role.
     */
    public function changeRole(User $user, TripRole $role): void
    {
        $this->members()->updateExistingPivot($user->getKey(), ['role' => $role->value]);
        $this->roleCache[$user->getKey()] = $role;
    }

    /**
     * Remove a member from the trip.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->getKey());
        $this->roleCache[$user->getKey()] = null;
    }
}
