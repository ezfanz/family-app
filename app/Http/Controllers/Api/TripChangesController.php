<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangesRequest;
use App\Http\Resources\ActivityNoteResource;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\DayResource;
use App\Http\Resources\IdeaResource;
use App\Http\Resources\ShopperResource;
use App\Http\Resources\ShoppingItemResource;
use App\Http\Resources\TripResource;
use App\Models\ActivityNote;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Polling feed for group sync.
 *
 * Timestamps are stored to the second, so records are matched with >= and the
 * client passes back `server_time` (taken before querying) as the next `since`.
 * A record can therefore arrive twice; the client merges by id, so that is harmless.
 * Soft-deleted rows are included with `deleted_at` set so deletions propagate.
 */
class TripChangesController extends Controller
{
    /**
     * Records changed since the given time.
     */
    public function __invoke(ChangesRequest $request, Trip $trip): JsonResponse
    {
        $serverTime = self::nextSince();
        $since = $request->since();

        $activities = $trip->activities()->withTrashed()->where('updated_at', '>=', $since)->get();
        $shoppingItems = $trip->shoppingItems()->withTrashed()->visibleTo($trip, $request->user())->where('updated_at', '>=', $since)->get();
        $ideas = $trip->ideas()->withTrashed()->where('updated_at', '>=', $since)->get();
        $days = $trip->days()->where('updated_at', '>=', $since)->get();

        $myNotes = ActivityNote::query()
            ->whereBelongsTo($request->user())
            ->where('updated_at', '>=', $since)
            ->whereIn('activity_id', $trip->activities()->withTrashed()->select('id'))
            ->get();

        return response()->json([
            'trip' => TripResource::make($trip),
            'days' => DayResource::collection($days),
            'activities' => ActivityResource::collection($activities),
            'shoppers' => ShopperResource::collection($trip->shoppersVisibleTo($request->user())),
            'shopping_items' => ShoppingItemResource::collection($shoppingItems),
            'ideas' => IdeaResource::collection($ideas),
            'my_notes' => ActivityNoteResource::collection($myNotes),
            'server_time' => $serverTime->toIso8601String(),
        ]);
    }

    /**
     * The `since` value the client should send next time. It overlaps a few
     * seconds so a write still committing while we query is not missed.
     */
    public static function nextSince(): Carbon
    {
        return now()->startOfSecond()->subSeconds(5);
    }
}
