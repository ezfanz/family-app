<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\TaxRefund\ReceiptReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Read the store, date and amount off a receipt's uploaded photo. Nothing is
 * saved: the app fills the Check receipt form and the person confirms.
 * Results are cached per photo, so opening the receipt again costs nothing.
 */
class ReceiptReadController extends Controller
{
    public function __invoke(Receipt $receipt, ReceiptReader $reader): JsonResponse
    {
        Gate::authorize('manageReceiptsOf', [$receipt->trip, (int) $receipt->user_id]);

        $photos = $receipt->photos()->orderBy('part')->get();
        if ($photos->isEmpty()) {
            return response()->json(['message' => 'This receipt has no photo yet.'], 422);
        }

        $cacheKey = 'receipt-read:'.sha1($photos->pluck('path')->implode('|'));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->keepSummary($receipt, $cached);

            return response()->json(['data' => $cached]);
        }

        $disk = Storage::disk(config('tax_refund.disk'));
        $images = $photos->map(fn ($photo): array => [
            'data' => (string) $disk->get($photo->path),
            'mime' => $photo->mime ?: 'image/jpeg',
        ])->all();

        try {
            $result = $reader->read($images);
        } catch (Throwable $error) {
            Log::warning('Receipt reading failed', ['receipt' => $receipt->id, 'error' => $error->getMessage()]);

            return response()->json(['message' => 'Couldn’t read this receipt. Type the details in.'], 503);
        }

        Cache::put($cacheKey, $result, now()->addDays(30));
        $this->keepSummary($receipt, $result);

        return response()->json(['data' => $result]);
    }

    /**
     * Keep the English summary and items on the receipt (the fields stay the
     * person's to confirm), so the summary shows whenever it is opened.
     *
     * @param  array<string, mixed>  $result
     */
    private function keepSummary(Receipt $receipt, array $result): void
    {
        if (($result['items'] ?? []) === [] && ($result['summary'] ?? '') === '') {
            return;
        }
        $reading = [
            'summary' => $result['summary'] ?? '',
            'items' => $result['items'] ?? [],
            'model' => $result['model'] ?? null,
        ];
        if (Arr::except($receipt->reading ?? [], 'read_at') === $reading) {
            return;
        }
        $receipt->forceFill(['reading' => $reading + ['read_at' => now()->toIso8601String()]])->save();
    }
}
