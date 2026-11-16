<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptPhotoRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt photos live on a private disk and are only streamed to the
 * receipt's owner and the trip owner. There are no public URLs.
 */
class ReceiptPhotoController extends Controller
{
    /**
     * Save part 1 or 2 of a receipt photo, replacing that part if it exists.
     * The app shrinks the photo (and will blur passport details) before upload.
     */
    public function store(StoreReceiptPhotoRequest $request, Receipt $receipt): ReceiptResource
    {
        Gate::authorize('manageReceiptsOf', [$receipt->trip, (int) $receipt->user_id]);

        $disk = Storage::disk(config('tax_refund.disk'));
        $part = (int) $request->validated('part');
        $file = $request->file('photo');
        $path = $file->storeAs(
            "trips/{$receipt->trip_id}/{$receipt->id}",
            "{$part}-".Str::random(16).'.'.$file->extension(),
            ['disk' => config('tax_refund.disk'), 'visibility' => 'private'],
        );

        $previous = $receipt->photos()->where('part', $part)->first();
        $receipt->photos()->updateOrCreate(['part' => $part], [
            'path' => $path,
            'mime' => $file->getMimeType() ?? 'image/jpeg',
            'bytes' => $file->getSize(),
        ]);
        if ($previous !== null && $previous->path !== $path) {
            $disk->delete($previous->path);
        }
        $receipt->touch();

        return ReceiptResource::make($receipt->refresh()->load(['photos', 'shoppingItems']));
    }

    /**
     * Stream one part of a receipt photo.
     */
    public function show(Receipt $receipt, int $part): StreamedResponse
    {
        Gate::authorize('manageReceiptsOf', [$receipt->trip, (int) $receipt->user_id]);

        $photo = $receipt->photos()->where('part', $part)->firstOrFail();

        return Storage::disk(config('tax_refund.disk'))->response($photo->path, null, [
            'Content-Type' => $photo->mime,
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
