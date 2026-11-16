<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /**
     * Account, trips and device preferences.
     */
    public function __invoke(Request $request): View
    {
        $previous = url()->previous();
        $cameFromApp = $previous !== $request->url() && str_starts_with($previous, url('/'));
        $back = $cameFromApp ? $previous : route('home');

        $currentTripId = null;
        if (preg_match('#/trips/(\d+)#', $back, $match) === 1
            && $request->user()->trips()->whereKey((int) $match[1])->exists()) {
            $currentTripId = (int) $match[1];
            $back = route('trips.show', $currentTripId);
        }

        $theme = $request->cookie('theme');

        return view('account', [
            'back' => $back,
            'backLabel' => $currentTripId !== null ? 'Itinerary' : 'Back',
            'theme' => in_array($theme, ['light', 'dark'], true) ? $theme : 'system',
            'user' => $request->user(),
            'trips' => $request->user()->trips()->orderBy('start_date')->get(),
            'currentTripId' => $currentTripId,
        ]);
    }
}
