<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReceiptStage;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAirportChecklistRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Trip;
use App\Services\TaxRefund\TaxRefundCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Departure day: your own checklist, and "I've done customs".
 */
class AirportController extends Controller
{
    /**
     * Save which checklist items you have ticked.
     */
    public function checklist(UpdateAirportChecklistRequest $request, Trip $trip): JsonResponse
    {
        $checked = array_values(array_unique($request->validated('checked')));
        $trip->members()->updateExistingPivot($request->user()->id, ['airport_checklist' => $checked]);

        return response()->json(['checked' => $checked]);
    }

    /**
     * The customs kiosk confirms all your purchases at once, so this marks every
     * eligible receipt of yours as customs confirmed. Not-eligible ones stay as they are.
     */
    public function customsDone(Request $request, Trip $trip): AnonymousResourceCollection
    {
        $calculator = TaxRefundCalculator::fromConfig();
        $receipts = $trip->receipts()->whereBelongsTo($request->user())->get();
        $groupTotals = $calculator->groupTotals($receipts);

        $confirmed = $receipts->filter(fn ($receipt): bool => $calculator->statusOf($receipt, $groupTotals) === ReceiptStatus::Eligible);
        foreach ($confirmed as $receipt) {
            $receipt->stage = ReceiptStage::CustomsConfirmed;
            $receipt->customs_confirmed_at = now();
            $receipt->save();
        }

        return ReceiptResource::collection($confirmed->values()->load(['photos', 'shoppingItems']));
    }
}
