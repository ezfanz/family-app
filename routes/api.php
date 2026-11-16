<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\IdeaController;
use App\Http\Controllers\Api\ShoppingBudgetController;
use App\Http\Controllers\Api\ShoppingItemController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TripChangesController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripMemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::pattern('activity', '[0-9a-fA-F-]{36}');
Route::pattern('shoppingItem', '[0-9a-fA-F-]{36}');
Route::pattern('trip', '[0-9]+');
Route::pattern('idea', '[0-9]+');
Route::pattern('user', '[0-9]+');

/*
| Mobile app login. Returns a Sanctum bearer token.
*/
Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:6,1')->name('api.token.store');

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::delete('/auth/token', [TokenController::class, 'destroy'])->name('token.destroy');
    Route::get('/user', fn (Request $request) => $request->user()->only(['id', 'name', 'email']))->name('user');

    Route::get('/trips', [TripController::class, 'index'])->name('trips.index');
    Route::post('/trips', [TripController::class, 'store'])->name('trips.store')->middleware('throttle:10,1');
    Route::post('/trips/join', [TripController::class, 'join'])->name('trips.join')->middleware('throttle:10,1');

    Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show')->can('view', 'trip');
    Route::patch('/trips/{trip}', [TripController::class, 'update'])->name('trips.update')->can('update', 'trip');
    Route::get('/trips/{trip}/changes', TripChangesController::class)->name('trips.changes')->can('view', 'trip');

    Route::get('/trips/{trip}/members', [TripMemberController::class, 'index'])->name('trips.members.index')->can('view', 'trip');
    Route::patch('/trips/{trip}/members/{user}', [TripMemberController::class, 'update'])->name('trips.members.update')->can('manageMembers', 'trip');
    Route::delete('/trips/{trip}/members/{user}', [TripMemberController::class, 'destroy'])->name('trips.members.destroy')->can('manageMembers', 'trip');
    Route::post('/trips/{trip}/join-code', [TripMemberController::class, 'regenerateCode'])->name('trips.join-code.regenerate')->can('manageMembers', 'trip');

    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::patch('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::patch('/activities/{activity}/status', [ActivityController::class, 'updateStatus'])->name('activities.status');
    Route::put('/activities/{activity}/note', [ActivityController::class, 'updateNote'])->name('activities.note');

    Route::post('/shopping-items', [ShoppingItemController::class, 'store'])->name('shopping-items.store');
    Route::patch('/shopping-items/{shoppingItem}', [ShoppingItemController::class, 'update'])->name('shopping-items.update');
    Route::delete('/shopping-items/{shoppingItem}', [ShoppingItemController::class, 'destroy'])->name('shopping-items.destroy');

    Route::post('/trips/{trip}/ideas', [IdeaController::class, 'store'])->name('ideas.store');
    Route::patch('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
    Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
    Route::patch('/ideas/{idea}/restore', [IdeaController::class, 'restore'])->name('ideas.restore')->withTrashed();

    Route::patch('/trips/{trip}/budgets/{user}', ShoppingBudgetController::class)->name('trips.budgets.update');
});
