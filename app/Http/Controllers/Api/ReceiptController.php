<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReceiptStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptRequest;
use App\Http\Requests\UpdateReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Models\ShoppingItem;
use App\Models\Trip;
use App\Services\TaxRefund\TaxRefundCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReceiptController extends Controller
{
    /**
     * Receipts the caller may see: their own, or everyone's for the trip owner.
     * The owner can narrow it to one person with ?user=.
     */
    public function index(Request $request, Trip $trip): AnonymousResourceCollection
    {
        $query = $trip->receipts()->visibleTo($trip, $request->user())->with(['photos', 'shoppingItems']);

        if ($request->filled('user')) {
            $userId = (int) $request->query('user');
            Gate::authorize('manageReceiptsOf', [$trip, $userId]);
            $query->where('user_id', $userId);
        }

        return ReceiptResource::collection($query->get());
    }

    /**
     * Save a receipt (yours by default; the owner can save one for anyone).
     * Retrying with the same client-generated id is idempotent, so offline saves never duplicate.
     */
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->validated('trip_id'));
        $ownerId = (int) ($request->validated('user_id') ?? $request->user()->id);
        Gate::authorize('manageReceiptsOf', [$trip, $ownerId]);
        abort_if($trip->members()->whereKey($ownerId)->doesntExist(), 422, 'That person is not in this trip.');

        $id = $request->validated('id');
        if ($id !== null && ($existing = Receipt::withTrashed()->find($id)) !== null) {
            abort_unless($existing->trip_id === $trip->id && $existing->user_id === $ownerId, 409, 'That id is already in use.');

            return ReceiptResource::make($existing->load(['photos', 'shoppingItems']))->response()->setStatusCode(200);
        }

        $receipt = new Receipt;
        if ($id !== null) {
            $receipt->id = $id;
        }
        $receipt->trip_id = $trip->id;
        $receipt->user_id = $ownerId;

        DB::transaction(fn () => $this->save($receipt, $request->validated()));

        return ReceiptResource::make($receipt->refresh()->load(['photos', 'shoppingItems']))->response()->setStatusCode(201);
    }

    /**
     * Edit a receipt, or move it along: customs confirmed, refunded, not received.
     */
    public function update(UpdateReceiptRequest $request, Receipt $receipt): ReceiptResource
    {
        Gate::authorize('manageReceiptsOf', [$receipt->trip, (int) $receipt->user_id]);

        $input = $request->validated();
        $newOwnerId = isset($input['user_id']) ? (int) $input['user_id'] : (int) $receipt->user_id;
        if ($newOwnerId !== (int) $receipt->user_id) {
            Gate::authorize('manageReceiptsOf', [$receipt->trip, $newOwnerId]);
            abort_if($receipt->trip->members()->whereKey($newOwnerId)->doesntExist(), 422, 'That person is not in this trip.');
            $receipt->user_id = $newOwnerId;
            // Links point at the old owner's shopping list.
            $input['shopping_item_ids'] ??= [];
        }

        DB::transaction(fn () => $this->save($receipt, $input));

        return ReceiptResource::make($receipt->refresh()->load(['photos', 'shoppingItems']));
    }

    /**
     * Soft-delete a receipt. Its photos stay until the receipt is purged.
     */
    public function destroy(Receipt $receipt): Response
    {
        Gate::authorize('manageReceiptsOf', [$receipt->trip, (int) $receipt->user_id]);

        $receipt->delete();

        return response()->noContent();
    }

    /**
     * Apply validated input, keep the stage and its dates consistent, and link shopping items.
     *
     * @param  array<string, mixed>  $input
     */
    private function save(Receipt $receipt, array $input): void
    {
        $attributes = Arr::only($input, ['store_name', 'branch', 'purchased_at', 'pre_tax_yen', 'tax_rate', 'is_estimated', 'stage', 'refund_received_yen']);
        foreach (['store_name', 'branch'] as $optional) {
            if (array_key_exists($optional, $attributes) && $attributes[$optional] === '') {
                $attributes[$optional] = null;
            }
        }
        $receipt->fill($attributes);

        // No amount yet means "photo saved, check later"; an amount moves it on.
        if ($receipt->pre_tax_yen === null) {
            $receipt->stage = ReceiptStage::Captured;
        } elseif ($receipt->stage === ReceiptStage::Captured) {
            $receipt->stage = ReceiptStage::Checked;
        }

        $receipt->customs_confirmed_at = in_array($receipt->stage, [ReceiptStage::CustomsConfirmed, ReceiptStage::Refunded, ReceiptStage::NotReceived], true)
            ? ($receipt->customs_confirmed_at ?? now())
            : null;
        $receipt->refunded_at = $receipt->stage === ReceiptStage::Refunded ? ($receipt->refunded_at ?? now()) : null;
        if ($receipt->stage !== ReceiptStage::Refunded) {
            $receipt->refund_received_yen = null;
        }

        $receipt->save();

        if (array_key_exists('shopping_item_ids', $input)) {
            $this->linkShoppingItems($receipt, $input['shopping_item_ids']);
        }
    }

    /**
     * Link items from the receipt owner's shopping list and tick them as bought.
     * With a single linked item and no price yet, its "Paid ¥" becomes the receipt total.
     *
     * @param  list<string>  $itemIds
     */
    private function linkShoppingItems(Receipt $receipt, array $itemIds): void
    {
        $items = ShoppingItem::query()
            ->whereKey($itemIds)
            ->where('trip_id', $receipt->trip_id)
            ->where('user_id', $receipt->user_id)
            ->get();

        if ($items->count() !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages(['shopping_item_ids' => 'Link items from this person’s shopping list.']);
        }

        $receipt->shoppingItems()->sync($items->modelKeys());

        $paid = $receipt->pre_tax_yen !== null
            ? $receipt->pre_tax_yen + TaxRefundCalculator::fromConfig()->taxOf($receipt)
            : null;

        foreach ($items as $item) {
            $item->is_bought = true;
            if ($items->count() === 1 && $item->actual_yen === null && $paid !== null) {
                $item->actual_yen = $paid;
            }
            $item->save();
        }
    }
}
