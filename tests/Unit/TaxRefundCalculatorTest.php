<?php

namespace Tests\Unit;

use App\Enums\ReceiptStage;
use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Services\TaxRefund\TaxRefundCalculator;
use Tests\TestCase;

class TaxRefundCalculatorTest extends TestCase
{
    private TaxRefundCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TaxRefundCalculator(minPreTaxYen: 5000, daysToExport: 90, timezone: 'Asia/Tokyo');
    }

    /**
     * An unsaved receipt; the calculator never needs the database.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function receipt(array $attributes): Receipt
    {
        $receipt = new Receipt(array_merge([
            'store_name' => 'Matsumoto Kiyoshi',
            'branch' => 'Dotonbori',
            'purchased_at' => '2026-11-22 16:20:00+09:00',
            'tax_rate' => 10,
            'stage' => ReceiptStage::Checked,
        ], $attributes));
        $receipt->user_id = $attributes['user_id'] ?? 1;

        return $receipt;
    }

    public function test_receipts_at_the_same_store_and_branch_on_the_same_day_qualify_together(): void
    {
        $first = $this->receipt(['pre_tax_yen' => 1980, 'purchased_at' => '2026-11-22 16:20:00+09:00']);
        $second = $this->receipt(['pre_tax_yen' => 3200, 'purchased_at' => '2026-11-22 19:48:00+09:00']);
        $groups = $this->calculator->groupTotals([$first, $second]);

        $this->assertSame([5180], array_values($groups));
        $this->assertSame(ReceiptStatus::Eligible, $this->calculator->statusOf($first, $groups));
        $this->assertSame(ReceiptStatus::Eligible, $this->calculator->statusOf($second, $groups));
    }

    public function test_a_receipt_under_the_minimum_shows_how_much_more_is_needed(): void
    {
        $alone = $this->receipt(['pre_tax_yen' => 1800, 'store_name' => '7-Eleven', 'branch' => 'Namba Sennichimae']);
        $groups = $this->calculator->groupTotals([$alone]);

        $this->assertSame(ReceiptStatus::NotEligible, $this->calculator->statusOf($alone, $groups));
        $this->assertSame(3200, $this->calculator->shortfall($alone, $groups));
    }

    public function test_groups_split_by_branch_by_japan_day_and_by_person(): void
    {
        $base = ['pre_tax_yen' => 3000];
        $receipts = [
            $this->receipt([...$base, 'branch' => 'Dotonbori']),
            $this->receipt([...$base, 'branch' => 'Namba']),
            // 23:30 on the 22nd in Japan is still the 22nd, though it is the 22nd 14:30 UTC.
            $this->receipt([...$base, 'purchased_at' => '2026-11-22 14:30:00+00:00']),
            // 00:30 on the 23rd in Japan is the 22nd 15:30 UTC: a different Japan day.
            $this->receipt([...$base, 'purchased_at' => '2026-11-22 15:30:00+00:00']),
            $this->receipt([...$base, 'user_id' => 2]),
        ];
        $groups = $this->calculator->groupTotals($receipts);

        $this->assertCount(4, $groups);
        $this->assertSame(ReceiptStatus::Eligible, $this->calculator->statusOf($receipts[0], $groups));
        $this->assertSame(ReceiptStatus::NotEligible, $this->calculator->statusOf($receipts[1], $groups));
        $this->assertSame(ReceiptStatus::NotEligible, $this->calculator->statusOf($receipts[3], $groups));
        $this->assertSame(ReceiptStatus::NotEligible, $this->calculator->statusOf($receipts[4], $groups));
    }

    public function test_store_names_match_regardless_of_case_and_spaces(): void
    {
        $receipts = [
            $this->receipt(['pre_tax_yen' => 2500, 'store_name' => 'MUJI ']),
            $this->receipt(['pre_tax_yen' => 2500, 'store_name' => 'muji']),
        ];

        $this->assertSame([5000], array_values($this->calculator->groupTotals($receipts)));
    }

    public function test_before_tax_is_estimated_from_the_total(): void
    {
        $this->assertSame(3200, TaxRefundCalculator::estimatePreTax(3520, 10));
        $this->assertSame(1800, TaxRefundCalculator::estimatePreTax(1944, 8));
        $this->assertSame(909, TaxRefundCalculator::estimatePreTax(1000, 10));
    }

    public function test_tax_is_rounded_down_and_uses_the_reduced_rate_for_food(): void
    {
        $this->assertSame(320, $this->calculator->taxOf($this->receipt(['pre_tax_yen' => 3200])));
        $this->assertSame(144, $this->calculator->taxOf($this->receipt(['pre_tax_yen' => 1800, 'tax_rate' => 8])));
        $this->assertSame(99, $this->calculator->taxOf($this->receipt(['pre_tax_yen' => 999])));
    }

    public function test_totals_match_the_design_sample(): void
    {
        $day = '2026-11-22';
        $receipts = [
            $this->receipt(['store_name' => 'MUJI', 'branch' => 'Namba Parks', 'purchased_at' => "{$day} 11:42:00+09:00", 'pre_tax_yen' => 5990, 'stage' => ReceiptStage::Refunded]),
            $this->receipt(['store_name' => 'UNIQLO', 'branch' => 'Shinsaibashi', 'purchased_at' => "{$day} 13:05:00+09:00", 'pre_tax_yen' => 11960, 'stage' => ReceiptStage::CustomsConfirmed]),
            $this->receipt(['purchased_at' => "{$day} 16:20:00+09:00", 'pre_tax_yen' => 1980, 'stage' => ReceiptStage::CustomsConfirmed]),
            $this->receipt(['purchased_at' => "{$day} 19:48:00+09:00", 'pre_tax_yen' => 3200]),
            $this->receipt(['store_name' => '7-Eleven', 'branch' => 'Namba Sennichimae', 'purchased_at' => "{$day} 21:10:00+09:00", 'pre_tax_yen' => 1800, 'tax_rate' => 8]),
            $this->receipt(['store_name' => 'Don Quijote', 'branch' => 'Dotonbori', 'purchased_at' => '2026-11-23 20:15:00+09:00', 'pre_tax_yen' => 7500, 'stage' => ReceiptStage::CustomsConfirmed]),
        ];

        $totals = $this->calculator->totals($receipts);

        $this->assertSame(['expected' => 3063, 'received' => 599, 'waiting' => 2144, 'to_confirm' => 320, 'not_received' => 0], $totals);
        $this->assertSame('91.89', number_format($totals['expected'] * 3.00 / 100, 2));
    }

    public function test_received_uses_the_amount_that_actually_arrived(): void
    {
        $refunded = $this->receipt(['pre_tax_yen' => 6000, 'stage' => ReceiptStage::Refunded, 'refund_received_yen' => 540]);

        $totals = $this->calculator->totals([$refunded]);

        $this->assertSame(600, $totals['expected']);
        $this->assertSame(540, $totals['received']);
    }

    public function test_captured_receipts_and_not_eligible_groups_add_nothing(): void
    {
        $captured = $this->receipt(['pre_tax_yen' => null, 'stage' => ReceiptStage::Captured]);
        $small = $this->receipt(['pre_tax_yen' => 1000, 'store_name' => 'Daiso']);

        $this->assertSame(ReceiptStatus::Captured, $this->calculator->statusOf($captured, $this->calculator->groupTotals([$captured])));
        $this->assertSame(0, $this->calculator->totals([$captured, $small])['expected']);
    }

    public function test_leave_by_date_counts_days_to_export_from_the_japan_day(): void
    {
        $receipt = $this->receipt(['pre_tax_yen' => 6000, 'purchased_at' => '2026-11-22 15:30:00+00:00']);

        $this->assertSame('2027-02-21', $this->calculator->leaveBy($receipt));
    }
}
