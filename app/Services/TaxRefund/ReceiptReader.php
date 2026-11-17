<?php

namespace App\Services\TaxRefund;

/**
 * Reads the fields off a receipt photo so the person only has to check them.
 *
 * Privacy: tax-free receipts can print passport details. Readers must only
 * return the fields below, never names or document numbers.
 */
interface ReceiptReader
{
    /**
     * @param  list<array{data: string, mime: string}>  $images  the receipt's photo parts, top first
     * @return array{
     *     fields: array{store_name?: string, branch?: string, date?: string, time?: string, amount_yen?: int, amount_kind?: 'pre_tax'|'total', tax_rate?: int},
     *     confidence: array<string, float>,
     *     items?: list<array{name_en: string, name_ja: string, qty: int, price_yen: int, reduced_rate: bool}>,
     *     summary?: string,
     *     model: string|null
     * }
     */
    public function read(array $images): array;
}
