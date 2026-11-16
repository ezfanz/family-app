<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YenRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExchangeRateController extends Controller
{
    /**
     * Today's yen to ringgit rate (RM per ¥100) from Bank Negara Malaysia.
     * With fresh=1 (the ↻ button) the server re-checks if its copy is over 15 minutes old.
     */
    public function __invoke(Request $request, YenRate $rates): JsonResponse
    {
        try {
            if ($request->boolean('fresh')) {
                $rates->forgetIfOlderThan(15);
            }

            return response()->json(['data' => $rates->current()]);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 503);
        }
    }
}
