<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportShoppingRequest;
use App\Imports\ShoppingListImport;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ShoppingImportController extends Controller
{
    /**
     * Check an Excel shopping list (default), or import it with confirm=1.
     * Members import into their own list; the owner can use the Buyer column
     * to fill anyone's list.
     */
    public function __invoke(ImportShoppingRequest $request, Trip $trip): JsonResponse
    {
        Gate::authorize('participate', $trip);

        $import = new ShoppingListImport($trip, $request->user());

        try {
            $result = $import->analyse($request->file('file')->getRealPath());
        } catch (RuntimeException $error) {
            throw ValidationException::withMessages(['file' => $error->getMessage()]);
        }

        $confirmed = $request->boolean('confirm');
        if ($confirmed) {
            $import->apply($result['rows']);
        }

        return response()->json([
            'applied' => $confirmed,
            'summary' => $result['summary'],
            'rows' => ShoppingListImport::present($result['rows']),
        ]);
    }
}
