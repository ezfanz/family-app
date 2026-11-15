<?php

namespace App\Http\Controllers\Web;

use App\Enums\TripRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\JoinTripRequest;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripController extends Controller
{
    /**
     * Send people straight into their trip when they only have one.
     */
    public function home(Request $request): RedirectResponse
    {
        $trips = $request->user()->trips()->limit(2)->get();

        return $trips->count() === 1
            ? redirect()->route('trips.show', $trips->first())
            : redirect()->route('trips.index');
    }

    /**
     * List the user's trips with a join form.
     */
    public function index(Request $request): View
    {
        return view('trips.index', [
            'trips' => $request->user()->trips()->orderBy('start_date')->get(),
        ]);
    }

    /**
     * The trip app shell.
     */
    public function show(Request $request, Trip $trip): View
    {
        return view('trips.show', [
            'trip' => $trip,
            'role' => $trip->roleFor($request->user()),
        ]);
    }

    /**
     * Join a trip with its code.
     */
    public function join(JoinTripRequest $request): RedirectResponse
    {
        $trip = Trip::where('join_code', $request->validated('code'))->firstOrFail();

        $joined = $trip->addMember($request->user(), TripRole::forNewMembers());

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', $joined ? 'You’re in. Welcome to the trip.' : 'You’re already in this trip.');
    }
}
