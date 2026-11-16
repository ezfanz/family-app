<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShoppingItemRequest;
use App\Http\Requests\UpdateShoppingItemRequest;
use App\Http\Resources\ShoppingItemResource;
use App\Models\ShoppingItem;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ShoppingItemController extends Controller
{
    /**
     * Add an item to someone's list (your own by default; the owner can add for anyone).
     * Retrying with the same client-generated id is idempotent.
     */
    public function store(StoreShoppingItemRequest $request): JsonResponse
    {
        $trip = Trip::findOrFail($request->validated('trip_id'));
        $listOwnerId = (int) ($request->validated('user_id') ?? $request->user()->id);
        Gate::authorize('manageShoppingOf', [$trip, $listOwnerId]);
        abort_if($trip->members()->whereKey($listOwnerId)->doesntExist(), 422, 'That person is not in this trip.');

        $id = $request->validated('id');
        if ($id !== null && ($existing = ShoppingItem::withTrashed()->find($id)) !== null) {
            abort_unless($existing->trip_id === $trip->id && $existing->user_id === $listOwnerId, 409, 'That id is already in use.');

            return ShoppingItemResource::make($existing)->response()->setStatusCode(200);
        }

        $item = new ShoppingItem(self::attributesFrom($request->validated()));
        if ($id !== null) {
            $item->id = $id;
        }
        $item->trip_id = $trip->id;
        $item->user_id = $listOwnerId;
        $item->sort_order = (int) $trip->shoppingItems()->max('sort_order') + 1;
        $item->save();

        return ShoppingItemResource::make($item->refresh())->response()->setStatusCode(201);
    }

    /**
     * Edit an item, tick it as bought, or record the price paid.
     */
    public function update(UpdateShoppingItemRequest $request, ShoppingItem $shoppingItem): ShoppingItemResource
    {
        Gate::authorize('manageShoppingOf', [$shoppingItem->trip, (int) $shoppingItem->user_id]);

        $shoppingItem->fill(self::attributesFrom($request->validated()))->save();

        return ShoppingItemResource::make($shoppingItem);
    }

    /**
     * Soft-delete an item.
     */
    public function destroy(ShoppingItem $shoppingItem): Response
    {
        Gate::authorize('manageShoppingOf', [$shoppingItem->trip, (int) $shoppingItem->user_id]);

        $shoppingItem->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private static function attributesFrom(array $input): array
    {
        $attributes = Arr::only($input, ['name', 'store', 'details', 'for_whom', 'category', 'link', 'notes', 'qty', 'estimated_yen', 'actual_yen', 'is_bought']);

        foreach (['store', 'details', 'for_whom', 'link', 'notes'] as $optional) {
            if (array_key_exists($optional, $attributes) && $attributes[$optional] === '') {
                $attributes[$optional] = null;
            }
        }
        if (array_key_exists('estimated_yen', $attributes)) {
            $attributes['estimated_yen'] ??= 0;
        }

        return $attributes;
    }
}
