<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripRole;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\User;
use App\Services\ShoppingOverview;
use App\Services\TaxRefund\TaxRefundCalculator;
use App\Services\YenRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ShoppingOverviewController extends Controller
{
    /**
     * Shopping overview for ?user=<id>, or ?user=all for the group (owner only).
     * Owners default to the group; everyone else to themselves.
     */
    public function __invoke(Request $request, Trip $trip, YenRate $rates): JsonResponse
    {
        $user = $request->user();
        $isOwner = $trip->roleFor($user) === TripRole::Owner;
        $wanted = $request->query('user', $isOwner ? 'all' : (string) $user->id);

        if ($wanted === 'all') {
            Gate::authorize('viewEveryonesShopping', $trip);
            $userId = null;
        } else {
            $userId = (int) $wanted;
            Gate::authorize('manageShoppingOf', [$trip, $userId]);
            abort_if($trip->members()->whereKey($userId)->doesntExist(), 404);
        }

        $members = $trip->members()->orderByPivot('id')->get()
            ->map(fn (User $member): array => [
                'user_id' => $member->id,
                'name' => $member->name,
                'budget_rm' => (float) $member->pivot->budget_rm,
            ])->all();
        $visible = $userId === null ? array_column($members, 'user_id') : [$userId];

        $overview = new ShoppingOverview(
            members: $members,
            items: $trip->shoppingItems()->whereIn('user_id', $visible)->get()->all(),
            receipts: $trip->receipts()->whereIn('user_id', $visible)->withCount('shoppingItems')->get()->all(),
            ratePer100Yen: $this->rate($trip, $rates),
            startDate: $trip->start_date->toDateString(),
            endDate: $trip->end_date->toDateString(),
            today: now((string) config('tax_refund.timezone'))->toDateString(),
            tax: TaxRefundCalculator::fromConfig(),
        );

        return response()->json(['data' => $overview->for($userId)]);
    }

    /**
     * RM per ¥100: today's Bank Negara rate when the trip follows it, else the trip's own.
     */
    private function rate(Trip $trip, YenRate $rates): float
    {
        if ($trip->exchange_rate_auto) {
            try {
                return (float) $rates->current()['rm_per_100_yen'];
            } catch (Throwable) {
                // Offline or the rate service is down: fall back to the trip's rate.
            }
        }

        return (float) $trip->exchange_rate_rm_per_100_yen;
    }
}
