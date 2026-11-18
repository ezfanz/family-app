<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * A person's bell notifications for one trip. The data column is plain text
 * in Postgres, so the trip is matched after loading (lists are small).
 */
class TripNotifications
{
    /**
     * @return Collection<int, DatabaseNotification>
     */
    public static function for(User $user, Trip $trip, int $limit = 100): Collection
    {
        return $user->notifications()->latest()->limit($limit)->get()
            ->filter(fn (DatabaseNotification $notification): bool => (int) ($notification->data['trip_id'] ?? 0) === $trip->id)
            ->values();
    }

    public static function unreadCount(User $user, Trip $trip): int
    {
        return $user->unreadNotifications()->latest()->limit(200)->get()
            ->filter(fn (DatabaseNotification $notification): bool => (int) ($notification->data['trip_id'] ?? 0) === $trip->id)
            ->count();
    }
}
