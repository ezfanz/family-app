<?php

namespace App\Models;

use App\Enums\ReceiptStage;
use App\Enums\TripRole;
use Carbon\CarbonImmutable;
use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tax-free purchase receipt. Private to its owner and the trip owner.
 */
#[Fillable(['store_name', 'branch', 'purchased_at', 'pre_tax_yen', 'tax_rate', 'is_estimated', 'stage', 'customs_confirmed_at', 'refund_received_yen', 'refunded_at'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tax_rate' => 10,
        'is_estimated' => false,
        'stage' => 'checked',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'pre_tax_yen' => 'integer',
            'tax_rate' => 'integer',
            'is_estimated' => 'boolean',
            'stage' => ReceiptStage::class,
            'customs_confirmed_at' => 'datetime',
            'refund_received_yen' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Store purchase times in UTC. The datetime cast keeps the clock time but
     * drops the offset, so "19:48+09:00" would otherwise be saved as 19:48 UTC.
     *
     * @return Attribute<CarbonImmutable|null, CarbonImmutable>
     */
    protected function purchasedAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * The member whose receipt this is.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ReceiptPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ReceiptPhoto::class)->orderBy('part');
    }

    /**
     * Shopping items bought on this receipt.
     *
     * @return BelongsToMany<ShoppingItem, $this>
     */
    public function shoppingItems(): BelongsToMany
    {
        return $this->belongsToMany(ShoppingItem::class);
    }

    /**
     * Receipts the given member may see: owners see everyone's, others only their own.
     *
     * @param  Builder<Receipt>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Trip $trip, User $user): void
    {
        if ($trip->roleFor($user) !== TripRole::Owner) {
            $query->where('user_id', $user->getKey());
        }
    }
}
