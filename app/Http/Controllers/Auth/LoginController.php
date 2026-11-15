<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Log the user in with email and password.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => strtolower(trim($request->string('email'))),
            'password' => $request->string('password')->toString(),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'That email and password don’t match an account.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
