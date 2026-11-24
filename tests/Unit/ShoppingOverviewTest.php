<?php

namespace Tests\Unit;

use App\Enums\ReceiptStage;
use App\Models\Receipt;
use App\Models\ShoppingItem;
use App\Services\ShoppingOverview;
use App\Services\TaxRefund\TaxRefundCalculator;
use Tests\TestCase;

/**
 * Uses the sample data from the design (code-s-shoppingoverview) at RM3.00 per ¥100.
 */
class ShoppingOverviewTest extends TestCase
{
    private const IRFAN = 1;

    private const AMIRA = 2;

    private const HAIKAL = 3;

    private const AMALINA = 4;

    private ShoppingOverview $overview;

    protected function setUp(): void
    {
        parent::setUp();

        $members = [
            ['user_id' => self::IRFAN, 'name' => 'Irfan', 'budget_rm' => 800.0],
            ['user_id' => self::AMIRA, 'name' => 'Amira', 'budget_rm' => 500.0],
            ['user_id' => self::HAIKAL, 'name' => 'Haikal', 'budget_rm' => 1200.0],
            ['user_id' => self::AMALINA, 'name' => 'Amalina', 'budget_rm' => 600.0],
        ];

        // [owner, day (0 = not bought), category, paid yen]
        $sample = [
            [self::IRFAN, 2, 'clothing_bags', 5990], [self::IRFAN, 2, 'clothing_bags', 11960], [self::IRFAN, 0, 'beauty', 0], [self::IRFAN, 0, 'snacks_food', 0],
            [self::AMIRA, 2, 'snacks_food', 2950], [self::AMIRA, 2, 'beauty', 2180], [self::AMIRA, 0, 'games_toys', 0],
            [self::HAIKAL, 2, 'clothing_bags', 15400], [self::HAIKAL, 3, 'games_toys', 6280], [self::HAIKAL, 9, 'clothing_bags', 7990], [self::HAIKAL, 9, 'snacks_food', 4800], [self::HAIKAL, 6, 'home_kitchen', 8800],
            [self::AMALINA, 3, 'home_kitchen', 1100], [self::AMALINA, 0, 'snacks_food', 0], [self::AMALINA, 0, 'snacks_food', 0],
        ];
        $items = array_map(function (array $row): ShoppingItem {
            [$owner, $day, $category, $yen] = $row;
            $item = new ShoppingItem(['name' => 'Item', 'category' => $category, 'actual_yen' => $yen ?: null, 'is_bought' => $yen > 0]);
            $item->user_id = $owner;
            $item->paid_at = $yen > 0 ? sprintf('2026-11-%02d 03:00:00', 20 + $day) : null;

            return $item;
        }, $sample);

        $this->overview = new ShoppingOverview(
            members: $members,
            items: $items,
            receipts: $this->sampleReceipts(),
            ratePer100Yen: 3.0,
            startDate: '2026-11-21',
            endDate: '2026-12-04',
            today: '2026-11-30',
            tax: new TaxRefundCalculator,
        );
    }

    /**
     * Receipt counts and refunds per person as in the design: 6/5/¥3,063, 4/4/¥1,420, 7/7/¥4,288, 1/0/¥0.
     *
     * @return list<Receipt>
     */
    private function sampleReceipts(): array
    {
        $receipt = function (int $owner, string $store, string $at, int $pre, int $rate = 10, ReceiptStage $stage = ReceiptStage::Checked): Receipt {
            $r = new Receipt(['store_name' => $store, 'branch' => 'Namba', 'purchased_at' => $at, 'pre_tax_yen' => $pre, 'tax_rate' => $rate, 'stage' => $stage]);
            $r->user_id = $owner;
            // The design's receipts are for items on the list, so they add nothing beyond Paid ¥.
            $r->shopping_items_count = 1;

            return $r;
        };

        return [
            $receipt(self::IRFAN, 'MUJI', '2026-11-22 11:42+09:00', 5990, stage: ReceiptStage::Refunded),
            $receipt(self::IRFAN, 'UNIQLO', '2026-11-22 13:05+09:00', 11960, stage: ReceiptStage::CustomsConfirmed),
            $receipt(self::IRFAN, 'Matsumoto Kiyoshi', '2026-11-22 16:20+09:00', 1980),
            $receipt(self::IRFAN, 'Matsumoto Kiyoshi', '2026-11-22 19:48+09:00', 3200),
            $receipt(self::IRFAN, '7-Eleven', '2026-11-22 21:10+09:00', 1800, 8),
            $receipt(self::IRFAN, 'Don Quijote', '2026-11-23 20:15+09:00', 7500),
            ...array_map(fn (int $pre) => $receipt(self::AMIRA, 'Royce', '2026-11-22 12:00+09:00', $pre), [3500, 3500, 3500, 3700]),
            ...array_map(fn (int $pre) => $receipt(self::HAIKAL, 'Bic Camera', '2026-11-29 12:00+09:00', $pre), [6000, 6000, 6000, 6000, 6000, 6000, 6880]),
            $receipt(self::AMALINA, 'Daiso', '2026-11-23 12:00+09:00', 1000),
        ];
    }

    public function test_everyone_totals(): void
    {
        $all = $this->overview->for(null);

        $this->assertSame(2023.5, $all['spent_rm']);
        $this->assertSame(3100.0, $all['budget_rm']);
        $this->assertSame(65, $all['used_pct']);
        $this->assertSame(1076.5, $all['left_rm']);
        $this->assertFalse($all['over_budget']);
        $this->assertSame([5, 10, 15], [$all['items_to_buy'], $all['items_bought'], $all['items_total']]);
        $this->assertSame([18, 16], [$all['receipts'], $all['receipts_eligible']]);
        $this->assertSame(8771, $all['refund_yen']);
        $this->assertSame(263.13, $all['refund_rm']);
        $this->assertSame(['n' => 2, 'date' => '2026-11-22', 'rm' => 1154.4, 'items' => 5, 'future' => false], $all['days'][1]);
        $this->assertCount(14, $all['days']);
        $this->assertTrue($all['days'][10]['future']);
        $this->assertSame(1200, $all['axis_max']);
        $this->assertSame(9, $all['selected_day']);
        $this->assertCount(4, $all['people']);
    }

    public function test_one_person_over_budget(): void
    {
        $haikal = $this->overview->for(self::HAIKAL);

        $this->assertSame(1298.1, $haikal['spent_rm']);
        $this->assertSame(1200.0, $haikal['budget_rm']);
        $this->assertSame(108, $haikal['used_pct']);
        $this->assertTrue($haikal['over_budget']);
        $this->assertSame(-98.1, $haikal['left_rm']);
        $this->assertSame([], $haikal['people']);

        $row = collect($this->overview->for(null)['people'])->firstWhere('user_id', self::HAIKAL);
        $this->assertTrue($row['over_budget']);
        $this->assertSame(108, $row['used_pct']);
    }

    public function test_a_person_with_little_spending_and_no_eligible_receipts(): void
    {
        $amalina = $this->overview->for(self::AMALINA);

        $this->assertSame(33.0, $amalina['spent_rm']);
        $this->assertSame(0, $amalina['refund_yen']);
        $this->assertSame([1, 0], [$amalina['receipts'], $amalina['receipts_eligible']]);
        $this->assertSame(3, $amalina['selected_day']);
        $this->assertSame([['key' => 'home_kitchen', 'label' => 'Home and kitchen', 'rm' => 33.0]], $amalina['categories']);
        $this->assertSame(300, $amalina['axis_max']);
    }

    public function test_categories_are_largest_first_and_skip_empty_ones(): void
    {
        $labels = array_column($this->overview->for(null)['categories'], 'label');

        $this->assertSame(['Clothing and bags', 'Home and kitchen', 'Snacks and food', 'Games and toys', 'Beauty'], $labels);
    }

    public function test_axis_snaps_to_300_600_or_1200(): void
    {
        $this->assertSame(300, ShoppingOverview::axisMax(0));
        $this->assertSame(300, ShoppingOverview::axisMax(300));
        $this->assertSame(600, ShoppingOverview::axisMax(300.01));
        $this->assertSame(600, ShoppingOverview::axisMax(600));
        $this->assertSame(1200, ShoppingOverview::axisMax(600.5));
        $this->assertSame(1200, ShoppingOverview::axisMax(1200));
        $this->assertSame(1800, ShoppingOverview::axisMax(1300));
    }

    public function test_over_budget_is_strictly_more_than_the_budget(): void
    {
        $this->assertFalse(ShoppingOverview::isOverBudget(1200, 1200));
        $this->assertTrue(ShoppingOverview::isOverBudget(1200.01, 1200));
        $this->assertFalse(ShoppingOverview::isOverBudget(0, 0));
    }

    public function test_latest_day_falls_back_to_today_within_the_trip(): void
    {
        $days = fn (array $spent, int $today) => array_map(fn (int $n) => ['n' => $n, 'rm' => $spent[$n] ?? 0.0, 'future' => $n > $today], range(1, 14));

        $this->assertSame(6, ShoppingOverview::latestDay($days([2 => 10.0, 6 => 5.0], 10), 10));
        $this->assertSame(10, ShoppingOverview::latestDay($days([], 10), 10));
        $this->assertSame(1, ShoppingOverview::latestDay($days([], -4), -4));
        $this->assertSame(14, ShoppingOverview::latestDay($days([], 20), 20));
    }
}
