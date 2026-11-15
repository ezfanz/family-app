<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Web\TripController;
use App\Http\Controllers\Web\TripMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [TripController::class, 'home'])->name('home');
    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('/trips/join', [TripController::class, 'join'])->name('trips.join')->middleware('throttle:10,1');
    Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show')->can('view', 'trip');

    Route::get('/trips/{trip}/members', [TripMemberController::class, 'index'])
        ->name('trips.members.index')->can('view', 'trip');
    Route::patch('/trips/{trip}/members/{user}', [TripMemberController::class, 'update'])
        ->name('trips.members.update')->can('manageMembers', 'trip');
    Route::delete('/trips/{trip}/members/{user}', [TripMemberController::class, 'destroy'])
        ->name('trips.members.destroy')->can('manageMembers', 'trip');
    Route::post('/trips/{trip}/join-code', [TripMemberController::class, 'regenerateCode'])
        ->name('trips.join-code.regenerate')->can('manageMembers', 'trip');
});
