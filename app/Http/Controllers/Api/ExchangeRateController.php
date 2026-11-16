<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YenRate;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ExchangeRateController extends Controller
{
    /**
     * Today's yen to ringgit rate (RM per ¥100) from Bank Negara Malaysia.
     */
    public function __invoke(YenRate $rates): JsonResponse
    {
        try {
            return response()->json(['data' => $rates->current()]);
        } catch (RuntimeException $error) {
            return response()->json(['message' => $error->getMessage()], 503);
        }
    }
}
