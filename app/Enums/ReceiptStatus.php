<?php

namespace App\Enums;

/**
 * What a person sees on a receipt: its stage, with "Checked" split into
 * Eligible / Not eligible by the store-and-day rule.
 */
enum ReceiptStatus: string
{
    case Captured = 'captured';
    case Eligible = 'eligible';
    case NotEligible = 'not_eligible';
    case CustomsConfirmed = 'customs_confirmed';
    case Refunded = 'refunded';
    case NotReceived = 'not_received';
}
