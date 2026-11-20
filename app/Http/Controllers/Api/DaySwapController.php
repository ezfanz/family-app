<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SwapDaysRequest;
use App\Http\Resources\DayResource;
use App\Models\Activity;
use App\Models\Day;
use App\Models\Idea;
use App\Models\Trip;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Swap two days' plans: city, title, stops (with their status and comments)
 * and ideas planned on them. Dates and day numbers stay put, and so does
 * the tax refund stop, which belongs to the departure day.
 */
class DaySwapController extends Controller
{
    public function __invoke(SwapDaysRequest $request, Trip $trip): AnonymousResourceCollection
    {
        $first = Day::findOrFail($request->validated('day_id'));
        $second = Day::findOrFail($request->validated('with_day_id'));

        DB::transaction(function () use ($first, $second) {
            $stopsOf = fn (Day $day): array => Activity::withTrashed()
                ->where('day_id', $day->id)
                ->whereNull('system_key')
                ->pluck('id')
                ->all();
            $ideasOf = fn (Day $day): array => Idea::withTrashed()->where('planned_day_id', $day->id)->pluck('id')->all();

            [$firstStops, $secondStops] = [$stopsOf($first), $stopsOf($second)];
            [$firstIdeas, $secondIdeas] = [$ideasOf($first), $ideasOf($second)];

            Activity::withTrashed()->whereKey($firstStops)->update(['day_id' => $second->id]);
            Activity::withTrashed()->whereKey($secondStops)->update(['day_id' => $first->id]);
            Idea::withTrashed()->whereKey($firstIdeas)->update(['planned_day_id' => $second->id]);
            Idea::withTrashed()->whereKey($secondIdeas)->update(['planned_day_id' => $first->id]);

            [$city, $title] = [$first->city, $first->title];
            $first->update(['city' => $second->city, 'title' => $second->title]);
            $second->update(['city' => $city, 'title' => $title]);
        });

        return DayResource::collection([$first->refresh(), $second->refresh()]);
    }
}
