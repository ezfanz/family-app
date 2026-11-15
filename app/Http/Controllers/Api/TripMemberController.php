<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TripMemberController extends Controller
{
    /**
     * People on the trip. Owners also receive email addresses.
     */
    public function index(Request $request, Trip $trip): AnonymousResourceCollection
    {
        $isOwner = $trip->roleFor($request->user()) === TripRole::Owner;
        $members = MemberResource::collection($trip->members()->orderBy('name')->get());
        $members->collection->each(fn (MemberResource $member) => $member->showEmail = $isOwner);

        return $members;
    }

    /**
     * Change someone's role to editor or viewer.
     */
    public function update(UpdateMemberRoleRequest $request, Trip $trip, User $user): MemberResource
    {
        $this->ensureEditableMember($request, $trip, $user);

        $trip->changeRole($user, TripRole::from($request->validated('role')));

        $member = MemberResource::make($trip->members()->whereKey($user->id)->firstOrFail());
        $member->showEmail = true;

        return $member;
    }

    /**
     * Remove someone from the trip.
     */
    public function destroy(Request $request, Trip $trip, User $user): Response
    {
        $this->ensureEditableMember($request, $trip, $user);

        $trip->removeMember($user);

        return response()->noContent();
    }

    /**
     * Replace the join code.
     */
    public function regenerateCode(Trip $trip): TripResource
    {
        $trip->regenerateJoinCode();

        return TripResource::make($trip);
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
