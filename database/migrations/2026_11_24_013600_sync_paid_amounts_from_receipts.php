<?php

use App\Models\Receipt;
use App\Services\ReceiptPaidSync;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Bring existing linked items in line with their receipt totals, so the
     * budget's Spent matches the receipts without editing Paid ¥ by hand.
     */
    public function up(): void
    {
        $sync = ReceiptPaidSync::fromConfig();

        Receipt::query()
            ->whereNotNull('pre_tax_yen')
            ->whereHas('shoppingItems')
            ->orderBy('purchased_at')
            ->each(fn (Receipt $receipt) => $sync->sync($receipt));
    }

    public function down(): void
    {
        // Paid amounts are data; nothing to undo.
    }
};
