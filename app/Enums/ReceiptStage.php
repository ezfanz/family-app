<?php

namespace App\Enums;

/**
 * Where a receipt is in the refund journey. "Eligible" and "Not eligible"
 * are not stages: they are worked out from the store-and-day group total.
 */
enum ReceiptStage: string
{
    /** Photo saved, amount not entered yet. */
    case Captured = 'captured';

    /** Amount entered; eligibility comes from its group. */
    case Checked = 'checked';

    case CustomsConfirmed = 'customs_confirmed';
    case Refunded = 'refunded';
    case NotReceived = 'not_received';
}
