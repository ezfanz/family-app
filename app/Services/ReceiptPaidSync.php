<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ShoppingItem;
use App\Services\TaxRefund\TaxRefundCalculator;

/**
 * Keeps the Paid ¥ of a receipt's linked shopping items equal to the receipt
 * total (before-tax amount + tax), so the budget's Spent follows the receipt.
 *
 * One linked item gets the whole total. Several share it in proportion to what
 * they already show as paid (when every one has a price), else their estimates,
 * else evenly. resources/js/trip/receipt-review.js mirrors this; keep the two in step.
 */
class ReceiptPaidSync
{
    public function __construct(private readonly TaxRefundCalculator $tax) {}

    public static function fromConfig(): self
    {
        return new self(TaxRefundCalculator::fromConfig());
    }

    /**
     * What was paid at the till, or null when the receipt has no amount yet.
     */
    public function totalOf(Receipt $receipt): ?int
    {
        return $receipt->pre_tax_yen === null ? null : (int) $receipt->pre_tax_yen + $this->tax->taxOf($receipt);
    }

    /**
     * Write the receipt total into its linked items' Paid ¥.
     */
    public function sync(Receipt $receipt): void
    {
        $total = $this->totalOf($receipt);
        if ($total === null) {
            return;
        }

        $items = $receipt->shoppingItems()->orderBy('shopping_items.sort_order')->orderBy('shopping_items.id')->get();
        if ($items->isEmpty()) {
            return;
        }

        $shares = self::split($total, $items->map(fn (ShoppingItem $item): array => [
            'paid' => $item->actual_yen,
            'estimated' => (int) $item->estimated_yen,
        ])->all());

        foreach ($items->values() as $index => $item) {
            $item->actual_yen = $shares[$index];
            $item->is_bought = true;
            $item->save();
        }
    }

    /**
     * Split a total across lines so the shares add up to it exactly.
     *
     * @param  list<array{paid: int|null, estimated: int}>  $lines
     * @return list<int>
     */
    public static function split(int $total, array $lines): array
    {
        $paid = array_map(fn (array $line): int => max(0, (int) $line['paid']), $lines);
        $estimated = array_map(fn (array $line): int => max(0, $line['estimated']), $lines);

        if (! in_array(0, $paid, true)) {
            $weights = $paid;
        } elseif (array_sum($estimated) > 0) {
            $weights = $estimated;
        } else {
            $weights = array_fill(0, count($lines), 1);
        }

        $sum = array_sum($weights);
        $shares = [];
        $running = 0;
        $given = 0;
        foreach ($weights as $weight) {
            $running += $weight;
            $upTo = (int) round($total * $running / $sum);
            $shares[] = $upTo - $given;
            $given = $upTo;
        }

        return $shares;
    }
}
