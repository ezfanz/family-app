<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityNoteRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Requests\UpdateActivityStatusRequest;
use App\Http\Resources\ActivityNoteResource;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\ActivityNote;
use App\Models\Day;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    /**
     * Add a stop. Retrying with the same client-generated id returns the
     * existing stop instead of creating a duplicate.
     */
    public function store(StoreActivityRequest $request): JsonResponse
    {
        $day = Day::findOrFail($request->validated('day_id'));
        Gate::authorize('update', $day->trip);

        $id = $request->validated('id');
        if ($id !== null && ($existing = Activity::withTrashed()->find($id)) !== null) {
            abort_unless($existing->trip_id === $day->trip_id, 409, 'That id is already in use.');

            return ActivityResource::make($existing)->response()->setStatusCode(200);
        }

        $activity = new Activity(self::attributesFrom($request->validated()));
        if ($id !== null) {
            $activity->id = $id;
        }
        $activity->trip_id = $day->trip_id;
        $activity->day_id = $day->id;
        $activity->sort_order = $day->activities()->count();
        $activity->save();

        return ActivityResource::make($activity->refresh())->response()->setStatusCode(201);
    }

    /**
     * Edit a stop, optionally moving it to another day of the same trip.
     */
    public function update(UpdateActivityRequest $request, Activity $activity): ActivityResource
    {
        Gate::authorize('update', $activity->trip);

        if ($request->has('day_id')) {
            $day = Day::findOrFail($request->validated('day_id'));
            if ($day->trip_id !== $activity->trip_id) {
                throw ValidationException::withMessages(['day_id' => 'Pick a day from this trip.']);
            }
            $activity->day_id = $day->id;
        }

        $activity->fill(self::attributesFrom($request->validated()))->save();

        return ActivityResource::make($activity);
    }

    /**
     * Soft-delete a stop so the deletion reaches everyone through the changes feed.
     */
    public function destroy(Activity $activity): Response
    {
        Gate::authorize('update', $activity->trip);

        $activity->delete();

        return response()->noContent();
    }

    /**
     * Mark a stop pending, done or skipped. Viewers may do this too.
     */
    public function updateStatus(UpdateActivityStatusRequest $request, Activity $activity): ActivityResource
    {
        Gate::authorize('participate', $activity->trip);

        $activity->update(['status' => $request->validated('status')]);

        return ActivityResource::make($activity);
    }

    /**
     * Save the user's private note on a stop. Viewers may do this too.
     */
    public function updateNote(UpdateActivityNoteRequest $request, Activity $activity): JsonResponse
    {
        Gate::authorize('participate', $activity->trip);

        $note = ActivityNote::query()
            ->whereBelongsTo($activity)
            ->whereBelongsTo($request->user())
            ->first() ?? new ActivityNote;
        $note->activity_id = $activity->id;
        $note->user_id = $request->user()->id;
        $note->note = (string) $request->validated('note');
        $note->save();

        return ActivityNoteResource::make($note)->response()->setStatusCode(200);
    }

    /**
     * Map validated input onto model attributes, keeping empty text as ''.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function attributesFrom(array $input): array
    {
        $map = [
            'time' => 'start_time',
            'name' => 'name',
            'transport' => 'transport',
            'meal' => 'meal',
            'ticket' => 'ticket',
            'notes' => 'notes',
            'places' => 'places',
            'is_halal' => 'is_halal',
        ];

        $attributes = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $input)) {
                $attributes[$column] = $input[$key];
            }
        }

        foreach (['transport', 'meal', 'ticket'] as $column) {
            if (array_key_exists($column, $attributes)) {
                $attributes[$column] ??= '';
            }
        }
        if (array_key_exists('notes', $attributes) && $attributes['notes'] === '') {
            $attributes['notes'] = null;
        }
        if (array_key_exists('places', $attributes)) {
            $attributes['places'] ??= [];
        }

        return $attributes;
    }
}
