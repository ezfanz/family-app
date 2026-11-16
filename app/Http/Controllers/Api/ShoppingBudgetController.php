<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Resources\ShopperResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ShoppingBudgetController extends Controller
{
    /**
     * Set a member's shopping budget: your own, or anyone's if you own the trip.
     */
    public function __invoke(UpdateBudgetRequest $request, Trip $trip, User $user): ShopperResource
    {
        Gate::authorize('manageShoppingOf', [$trip, $user->id]);
        abort_if($trip->roleFor($user) === null, 404);

        $trip->members()->updateExistingPivot($user->id, ['budget_rm' => $request->validated('budget_rm')]);

        return ShopperResource::make($trip->members()->whereKey($user->id)->firstOrFail());
    }
}
