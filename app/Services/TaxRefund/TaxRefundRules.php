<?php

namespace App\Services\TaxRefund;

/**
 * The tax refund settings the app needs. Sent with the full trip and with
 * every changes check, so a phone with a saved trip picks up a change (like
 * receipt reading being switched on) without reloading everything.
 */
class TaxRefundRules
{
    /**
     * @return array{min_pre_tax_yen: int, days_to_export: int, auto_read: bool}
     */
    public static function forApp(): array
    {
        return [
            'min_pre_tax_yen' => (int) config('tax_refund.min_pre_tax_yen'),
            'days_to_export' => (int) config('tax_refund.days_to_export'),
            'auto_read' => ClaudeReceiptReader::isConfigured(),
        ];
    }
}
