<?php

namespace App\Services\TaxRefund;

/**
 * Reads nothing, so the person types the store and amount. Used when no
 * Claude API key is set.
 */
class ManualReceiptReader implements ReceiptReader
{
    /**
     * @param  list<array{data: string, mime: string}>  $images
     * @return array{fields: array{}, confidence: array{}, model: null}
     */
    public function read(array $images): array
    {
        return ['fields' => [], 'confidence' => [], 'model' => null];
    }
}
