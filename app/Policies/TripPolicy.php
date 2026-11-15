<?php

namespace App\Policies;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Trip access is decided by the user's role on the trip_user pivot.
 * Non-members get a 404 so trip ids are not confirmed to outsiders.
 */
class TripPolicy
{
    /**
     * Any member can see the trip and everything in it.
     */
    public function view(User $user, Trip $trip): Response
    {
        return $trip->roleFor($user) !== null
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Owners and editors can change trip content and settings.
     */
    public function update(User $user, Trip $trip): Response
    {
        $role = $trip->roleFor($user);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        return $role->canEdit()
            ? Response::allow()
            : Response::deny('You can view this trip but not edit it.');
    }

    /**
     * Only owners see the join code, change roles and remove people.
     */
    public function manageMembers(User $user, Trip $trip): Response
    {
        $role = $trip->roleFor($user);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        return $role === TripRole::Owner
            ? Response::allow()
            : Response::deny('Only the trip owner can manage people.');
    }
}
