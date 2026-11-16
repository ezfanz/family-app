<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStopPhotoRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\StopPhotos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A stop's photo: found on Wikimedia Commons, uploaded by an editor, or removed.
 */
class StopPhotoController extends Controller
{
    /**
     * Stream an uploaded stop photo to trip members.
     */
    public function show(Activity $activity): StreamedResponse
    {
        Gate::authorize('view', $activity->trip);
        abort_unless($activity->photo_source === 'upload' && $activity->photo_path !== null, 404);

        return Storage::disk(config('itinerary.photo_disk'))->response($activity->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    /**
     * Upload your own photo for a stop (owners and editors).
     */
    public function store(StoreStopPhotoRequest $request, Activity $activity): ActivityResource
    {
        Gate::authorize('update', $activity->trip);

        $file = $request->file('photo');
        $path = $file->storeAs(
            "trips/{$activity->trip_id}/stops",
            $activity->id.'-'.Str::random(12).'.'.$file->extension(),
            ['disk' => config('itinerary.photo_disk')],
        );

        $this->deleteUpload($activity);
        $url = "/api/activities/{$activity->id}/photo?v=".now()->timestamp;
        $activity->forceFill([
            'photo_source' => 'upload',
            'photo_path' => $path,
            'photo_url' => $url,
            'photo_thumb_url' => $url,
            'photo_credit' => 'Photo: '.$request->user()->name,
            'photo_link' => null,
            'photo_checked_at' => now(),
        ])->save();

        return ActivityResource::make($activity);
    }

    /**
     * Look for a photo on Wikimedia Commons again (replaces an upload only if one is found).
     */
    public function lookup(Activity $activity, StopPhotos $photos): ActivityResource
    {
        Gate::authorize('update', $activity->trip);

        $hadUpload = $activity->photo_source === 'upload' ? $activity->photo_path : null;
        $photos->refresh($activity, force: true);
        if ($hadUpload !== null && $activity->photo_source === 'wikimedia') {
            Storage::disk(config('itinerary.photo_disk'))->delete($hadUpload);
            $activity->forceFill(['photo_path' => null])->save();
        }

        return ActivityResource::make($activity);
    }

    /**
     * Remove the photo and stop looking one up for this stop.
     */
    public function destroy(Activity $activity): ActivityResource
    {
        Gate::authorize('update', $activity->trip);

        $this->deleteUpload($activity);
        $activity->forceFill([
            'photo_source' => 'none',
            'photo_path' => null,
            'photo_url' => null,
            'photo_thumb_url' => null,
            'photo_credit' => null,
            'photo_link' => null,
        ])->save();

        return ActivityResource::make($activity);
    }

    private function deleteUpload(Activity $activity): void
    {
        if ($activity->photo_path !== null) {
            Storage::disk(config('itinerary.photo_disk'))->delete($activity->photo_path);
        }
    }
}
