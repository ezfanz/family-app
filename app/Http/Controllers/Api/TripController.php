<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinTripRequest;
use App\Http\Requests\StoreTripRequest;
use App\Http\Requests\UpdateTripRequest;
use App\Http\Resources\ActivityNoteResource;
use App\Http\Resources\DayResource;
use App\Http\Resources\IdeaResource;
use App\Http\Resources\ReceiptResource;
use App\Http\Resources\ShopperResource;
use App\Http\Resources\ShoppingItemResource;
use App\Http\Resources\TripResource;
use App\Models\ActivityNote;
use App\Models\Trip;
use App\Services\TaxRefund\TaxRefundRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripController extends Controller
{
    /**
     * Trips the user belongs to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return TripResource::collection(
            $request->user()->trips()->orderBy('start_date')->get()
        );
    }

    /**
     * Start a new trip with the user as owner.
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = Trip::startFor($request->user(), $request->validated());

        return TripResource::make($trip)->response()->setStatusCode(201);
    }

    /**
     * Everything the app needs to render a trip offline.
     */
    public function show(Request $request, Trip $trip): JsonResponse
    {
        $serverTime = TripChangesController::nextSince();

        $trip->load(['days.activities', 'ideas']);
        $user = $request->user();

        $myNotes = ActivityNote::query()
            ->whereBelongsTo($user)
            ->whereIn('activity_id', $trip->activities()->select('id'))
            ->get();

        return response()->json([
            'trip' => TripResource::make($trip),
            'days' => DayResource::collection($trip->days),
            'shoppers' => ShopperResource::collection($trip->shoppersVisibleTo($user)),
            'shopping_items' => ShoppingItemResource::collection($trip->shoppingItems()->visibleTo($trip, $user)->get()),
            'ideas' => IdeaResource::collection($trip->ideas),
            'my_notes' => ActivityNoteResource::collection($myNotes),
            'others_notes' => ActivityNoteResource::collection(ActivityNote::othersVisibleTo($trip, $user)->where('note', '!=', '')->get()),
            'receipts' => ReceiptResource::collection($trip->receipts()->visibleTo($trip, $user)->with(['photos', 'shoppingItems'])->get()),
            'my_airport_checklist' => $trip->members()->whereKey($user->id)->first()?->pivot->airport_checklist ?? [],
            'tax_refund' => TaxRefundRules::forApp(),
            'server_time' => $serverTime->toIso8601String(),
        ]);
    }

    /**
     * Change the shopping exchange rate and deposit.
     */
    public function update(UpdateTripRequest $request, Trip $trip): TripResource
    {
        $trip->update($request->validated());

        return TripResource::make($trip);
    }

    /**
     * Join a trip with its code.
     */
    public function join(JoinTripRequest $request): JsonResponse
    {
        $trip = Trip::where('join_code', $request->validated('code'))->firstOrFail();

        $joined = $trip->addMember($request->user(), TripRole::forNewMembers());

        return TripResource::make($trip)->response()->setStatusCode($joined ? 201 : 200);
    }
}
