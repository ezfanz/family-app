<?php

namespace App\Services\TaxRefund;

/**
 * Reads fields off a receipt photo (Phase 2). Implementations could be an
 * on-device OCR result passed up from the app, or a cloud vision model.
 *
 * Privacy: tax-free receipts can print passport details. Photos must be
 * cropped or blurred on the phone before upload (see photo-queue.js) and
 * before any implementation sends them to a third party.
 */
interface ReceiptReader
{
    /**
     * @return array{fields: array{store_name?: string, branch?: string, purchased_at?: string, pre_tax_yen?: int, tax_rate?: int, total_paid_yen?: int, tax_free_mark?: bool}, confidence: array<string, float>}
     */
    public function read(string $photoPath): array;
}
