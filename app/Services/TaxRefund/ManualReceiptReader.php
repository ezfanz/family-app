<?php

namespace App\Services\TaxRefund;

/**
 * Phase 1 reader: reads nothing, so the person types the store and amount.
 */
class ManualReceiptReader implements ReceiptReader
{
    /**
     * @return array{fields: array{}, confidence: array{}}
     */
    public function read(string $photoPath): array
    {
        return ['fields' => [], 'confidence' => []];
    }
}
