<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIdeaRequest;
use App\Http\Requests\UpdateIdeaRequest;
use App\Http\Resources\IdeaResource;
use App\Models\Day;
use App\Models\Idea;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IdeaController extends Controller
{
    /**
     * Add an idea to the trip.
     */
    public function store(StoreIdeaRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('update', $trip);

        $attributes = self::attributesFrom($request->validated(), $trip);
        $attributes['sort_order'] = (int) $trip->ideas()->max('sort_order') + 1;

        $idea = $trip->ideas()->create($attributes);

        return IdeaResource::make($idea->refresh())->response()->setStatusCode(201);
    }

    /**
     * Tick an idea as visited, edit it, or link it to the day it was planned on.
     */
    public function update(UpdateIdeaRequest $request, Idea $idea): IdeaResource
    {
        Gate::authorize('update', $idea->trip);

        $idea->update(self::attributesFrom($request->validated(), $idea->trip));

        return IdeaResource::make($idea);
    }

    /**
     * Soft-delete an idea.
     */
    public function destroy(Idea $idea): Response
    {
        Gate::authorize('update', $idea->trip);

        $idea->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function attributesFrom(array $input, Trip $trip): array
    {
        $attributes = Arr::only($input, ['name', 'region', 'city', 'area', 'is_visited', 'planned_day_id']);

        foreach (['city', 'area'] as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] === '') {
                $attributes[$field] = null;
            }
        }

        if (($attributes['planned_day_id'] ?? null) !== null
            && Day::whereKey($attributes['planned_day_id'])->value('trip_id') !== $trip->id) {
            throw ValidationException::withMessages(['planned_day_id' => 'Pick a day from this trip.']);
        }

        return $attributes;
    }
}
