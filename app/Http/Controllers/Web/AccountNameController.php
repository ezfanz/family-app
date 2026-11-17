<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountNameRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Change the name everyone in your trips sees (avatars, lists, receipts).
 */
class AccountNameController extends Controller
{
    public function __invoke(UpdateAccountNameRequest $request): RedirectResponse
    {
        $name = $request->validated('name');
        $request->user()->update(['name' => $name]);

        return redirect()->route('account')->with('status', "Name saved. Everyone in your trips now sees “{$name}”.");
    }
}
