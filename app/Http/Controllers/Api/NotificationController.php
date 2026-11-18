<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\TripNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The bell: your @mentions on this trip, newest first.
 */
class NotificationController extends Controller
{
    public function index(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => TripNotifications::for($user, $trip, 60)->take(30)->map(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => 'mention',
                ...$notification->data,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values(),
            'unread_count' => TripNotifications::unreadCount($user, $trip),
        ]);
    }

    /**
     * Mark this trip's notifications read (all, or the given ids).
     */
    public function read(Request $request, Trip $trip): JsonResponse
    {
        $user = $request->user();
        $ids = collect($request->input('ids', []))->map(fn ($id): string => (string) $id);

        TripNotifications::for($user, $trip, 200)
            ->filter(fn (DatabaseNotification $notification): bool => $notification->read_at === null && ($ids->isEmpty() || $ids->contains($notification->id)))
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

        return response()->json(['unread_count' => TripNotifications::unreadCount($user, $trip)]);
    }
}
