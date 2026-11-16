<?php

namespace App\Services\TaxRefund;

use App\Enums\ReceiptStage;
use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use Carbon\CarbonImmutable;

/**
 * Japan's refund-method rules, applied to one person's receipts.
 *
 * Receipts are grouped by person + store + branch + calendar day in Japan.
 * A group whose before-tax total reaches the minimum makes every receipt in
 * it eligible. Refunds are always "expected": stores pay them, not this app.
 *
 * resources/js/trip/taxcalc.js mirrors these rules for the offline UI.
 */
class TaxRefundCalculator
{
    public function __construct(
        public readonly int $minPreTaxYen = 5000,
        public readonly int $daysToExport = 90,
        public readonly string $timezone = 'Asia/Tokyo',
    ) {}

    /**
     * Build a calculator from config/tax_refund.php.
     */
    public static function fromConfig(): self
    {
        return new self(
            (int) config('tax_refund.min_pre_tax_yen'),
            (int) config('tax_refund.days_to_export'),
            (string) config('tax_refund.timezone'),
        );
    }

    /**
     * Before-tax amount when only the tax-inclusive total is known (total ÷ 1.10, or ÷ 1.08 for food).
     */
    public static function estimatePreTax(int $totalPaidYen, int $taxRate = 10): int
    {
        return (int) round($totalPaidYen * 100 / (100 + $taxRate));
    }

    /**
     * Consumption tax on a receipt, rounded down to the yen like Japanese tills.
     */
    public function taxOf(Receipt $receipt): int
    {
        return intdiv((int) $receipt->pre_tax_yen * (int) $receipt->tax_rate, 100);
    }

    /**
     * Japan-time calendar date the receipt counts towards.
     */
    public function dayOf(Receipt $receipt): string
    {
        return CarbonImmutable::parse($receipt->purchased_at)->setTimezone($this->timezone)->toDateString();
    }

    /**
     * Key of the person + store + branch + day group the receipt belongs to.
     */
    public function groupKey(Receipt $receipt): string
    {
        $normalise = fn (?string $text): string => mb_strtolower(trim((string) $text));

        return implode('|', [$receipt->user_id, $normalise($receipt->store_name), $normalise($receipt->branch), $this->dayOf($receipt)]);
    }

    /**
     * Before-tax total per group. Receipts without an amount yet count as zero.
     *
     * @param  iterable<Receipt>  $receipts
     * @return array<string, int>
     */
    public function groupTotals(iterable $receipts): array
    {
        $totals = [];
        foreach ($receipts as $receipt) {
            $key = $this->groupKey($receipt);
            $totals[$key] = ($totals[$key] ?? 0) + (int) $receipt->pre_tax_yen;
        }

        return $totals;
    }

    /**
     * How much more must be spent in the group to qualify (0 when it already does).
     *
     * @param  array<string, int>  $groupTotals
     */
    public function shortfall(Receipt $receipt, array $groupTotals): int
    {
        return max(0, $this->minPreTaxYen - ($groupTotals[$this->groupKey($receipt)] ?? 0));
    }

    /**
     * The status a person sees: the stored stage, or Eligible / Not eligible from the group rule.
     *
     * @param  array<string, int>  $groupTotals
     */
    public function statusOf(Receipt $receipt, array $groupTotals): ReceiptStatus
    {
        if ($receipt->stage === ReceiptStage::Captured || $receipt->pre_tax_yen === null) {
            return ReceiptStatus::Captured;
        }
        if ($this->shortfall($receipt, $groupTotals) > 0) {
            return ReceiptStatus::NotEligible;
        }

        return match ($receipt->stage) {
            ReceiptStage::CustomsConfirmed => ReceiptStatus::CustomsConfirmed,
            ReceiptStage::Refunded => ReceiptStatus::Refunded,
            ReceiptStage::NotReceived => ReceiptStatus::NotReceived,
            default => ReceiptStatus::Eligible,
        };
    }

    /**
     * Last day the goods can leave Japan for this receipt.
     */
    public function leaveBy(Receipt $receipt): string
    {
        return CarbonImmutable::parse($this->dayOf($receipt))->addDays($this->daysToExport)->toDateString();
    }

    /**
     * Expected = tax on every qualifying receipt. Received = refunds that arrived
     * (the amount entered, else the tax). Waiting = customs confirmed, not refunded yet.
     * To confirm = eligible, customs not done yet. Not received = given up on.
     *
     * @param  iterable<Receipt>  $receipts
     * @return array{expected: int, received: int, waiting: int, to_confirm: int, not_received: int}
     */
    public function totals(iterable $receipts): array
    {
        $receipts = is_array($receipts) ? $receipts : iterator_to_array($receipts, false);
        $groupTotals = $this->groupTotals($receipts);
        $totals = ['expected' => 0, 'received' => 0, 'waiting' => 0, 'to_confirm' => 0, 'not_received' => 0];

        foreach ($receipts as $receipt) {
            $status = $this->statusOf($receipt, $groupTotals);
            if (in_array($status, [ReceiptStatus::Captured, ReceiptStatus::NotEligible], true)) {
                continue;
            }

            $tax = $this->taxOf($receipt);
            $totals['expected'] += $tax;
            match ($status) {
                ReceiptStatus::Refunded => $totals['received'] += $receipt->refund_received_yen ?? $tax,
                ReceiptStatus::CustomsConfirmed => $totals['waiting'] += $tax,
                ReceiptStatus::NotReceived => $totals['not_received'] += $tax,
                default => $totals['to_confirm'] += $tax,
            };
        }

        return $totals;
    }
}
