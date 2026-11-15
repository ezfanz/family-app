<?php

namespace App\Http\Controllers\Web;

use App\Enums\TripRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripMemberController extends Controller
{
    /**
     * List the people on the trip. Owners also see the join code and controls.
     */
    public function index(Trip $trip): View
    {
        return view('trips.members', [
            'trip' => $trip,
            'members' => $trip->members()->orderBy('name')->get(),
            'roles' => TripRole::assignable(),
        ]);
    }

    /**
     * Change someone's role. Owners cannot change their own role,
     * which guarantees the trip always keeps at least one owner.
     */
    public function update(UpdateMemberRoleRequest $request, Trip $trip, User $user): RedirectResponse
    {
        $this->ensureEditableMember($request, $trip, $user);

        $trip->changeRole($user, TripRole::from($request->validated('role')));

        return back()->with('status', "{$user->name} is now {$request->validated('role')}.");
    }

    /**
     * Remove someone from the trip.
     */
    public function destroy(Request $request, Trip $trip, User $user): RedirectResponse
    {
        $this->ensureEditableMember($request, $trip, $user);

        $trip->removeMember($user);

        return back()->with('status', "{$user->name} was removed from the trip.");
    }

    /**
     * Replace the join code.
     */
    public function regenerateCode(Trip $trip): RedirectResponse
    {
        $trip->regenerateJoinCode();

        return back()->with('status', 'New join code made. The old one no longer works.');
    }

    /**
     * Guard against acting on yourself or on someone outside the trip.
     */
    private function ensureEditableMember(Request $request, Trip $trip, User $user): void
    {
        abort_if($trip->roleFor($user) === null, 404);
        abort_if($user->is($request->user()), 422, 'You can’t change your own role or remove yourself.');
    }
}
