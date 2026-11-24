<?php

namespace App\Services;

use App\Enums\ReceiptStatus;
use App\Enums\ShoppingCategory;
use App\Models\Receipt;
use App\Models\ShoppingItem;
use App\Services\TaxRefund\TaxRefundCalculator;
use Carbon\CarbonImmutable;

/**
 * Shopping overview numbers for one person or the whole group.
 *
 * Spent = (Σ Paid ¥ + Σ totals of receipts not linked to any item) × rate / 100.
 * Linked receipts already flow into their items' Paid ¥ (ReceiptPaidSync), so they
 * are not counted twice. An item counts towards the Japan-time day its Paid ¥ was
 * first entered; an unlinked receipt towards its purchase day, under "Other". resources/js/trip/overviewcalc.js mirrors these
 * rules so the screen works offline; keep the two in step.
 */
class ShoppingOverview
{
    /** Y-axis tops for "Spent by day", in RM: the smallest that fits the peak. */
    public const AXIS_STEPS = [300, 600, 1200];

    /**
     * @param  list<array{user_id: int, name: string, budget_rm: float}>  $members
     * @param  list<ShoppingItem>  $items
     * @param  list<Receipt>  $receipts
     */
    public function __construct(
        private readonly array $members,
        private readonly array $items,
        private readonly array $receipts,
        private readonly float $ratePer100Yen,
        private readonly string $startDate,
        private readonly string $endDate,
        private readonly string $today,
        private readonly TaxRefundCalculator $tax,
        private readonly string $timezone = 'Asia/Tokyo',
    ) {}

    /**
     * Smallest axis top (300, 600 or 1200 RM) that fits the peak. Larger peaks round up to the next 600.
     */
    public static function axisMax(float $peakRm): int
    {
        foreach (self::AXIS_STEPS as $step) {
            if ($peakRm <= $step) {
                return $step;
            }
        }

        return (int) (ceil($peakRm / 600) * 600);
    }

    public static function isOverBudget(float $spentRm, float $budgetRm): bool
    {
        return round($spentRm, 2) > round($budgetRm, 2);
    }

    /**
     * The day to select: the latest past day with spending, else today (clamped to the trip).
     *
     * @param  list<array{n: int, rm: float, future: bool}>  $days
     */
    public static function latestDay(array $days, int $todayNumber): int
    {
        $spent = array_filter($days, fn (array $day): bool => ! $day['future'] && $day['rm'] > 0);
        if ($spent !== []) {
            return max(array_column($spent, 'n'));
        }

        return max(1, min(count($days), $todayNumber));
    }

    public function toRm(int $yen): float
    {
        return round($yen * $this->ratePer100Yen / 100, 2);
    }

    /**
     * Trip day number for a date (Day 1 = start date); may be < 1 or past the end.
     */
    public function dayNumber(string $date): int
    {
        return (int) CarbonImmutable::parse($this->startDate)->diffInDays(CarbonImmutable::parse($date), false) + 1;
    }

    /**
     * Everything the overview shows for one member (user id) or everyone (null).
     *
     * @return array<string, mixed>
     */
    public function for(?int $userId): array
    {
        $members = array_values(array_filter($this->members, fn (array $m): bool => $userId === null || $m['user_id'] === $userId));
        $ids = array_column($members, 'user_id');
        $items = array_values(array_filter($this->items, fn (ShoppingItem $i): bool => in_array((int) $i->user_id, $ids, true)));
        $receipts = array_values(array_filter($this->receipts, fn (Receipt $r): bool => in_array((int) $r->user_id, $ids, true)));

        $spentYen = array_sum(array_map(fn (ShoppingItem $i): int => (int) $i->actual_yen, $items))
            + array_sum(array_map(fn (Receipt $r): int => $this->unlinkedReceiptYen($r), $receipts));
        $spentRm = $this->toRm($spentYen);
        $budgetRm = round(array_sum(array_column($members, 'budget_rm')), 2);
        $bought = count(array_filter($items, fn (ShoppingItem $i): bool => $i->is_bought));

        $groups = $this->tax->groupTotals($receipts);
        $qualifying = array_filter($receipts, fn (Receipt $r): bool => ! in_array($this->tax->statusOf($r, $groups), [ReceiptStatus::Captured, ReceiptStatus::NotEligible], true));
        $refundYen = $this->tax->totals($receipts)['expected'];

        [$days, $beforeTripRm] = $this->days($items, $receipts);

        return [
            'user_id' => $userId,
            'spent_yen' => $spentYen,
            'spent_rm' => $spentRm,
            'budget_rm' => $budgetRm,
            'used_pct' => $budgetRm > 0 ? (int) round($spentRm / $budgetRm * 100) : 0,
            'over_budget' => self::isOverBudget($spentRm, $budgetRm),
            'left_rm' => round($budgetRm - $spentRm, 2),
            'items_total' => count($items),
            'items_bought' => $bought,
            'items_to_buy' => count($items) - $bought,
            'receipts' => count($receipts),
            'receipts_eligible' => count($qualifying),
            'refund_yen' => $refundYen,
            'refund_rm' => $this->toRm($refundYen),
            'days' => $days,
            'before_trip_rm' => $beforeTripRm,
            'axis_max' => self::axisMax(max([0, ...array_column($days, 'rm')])),
            'selected_day' => self::latestDay($days, $this->dayNumber($this->today)),
            'categories' => $this->categories($items, $receipts),
            'people' => $userId === null ? $this->people() : [],
        ];
    }

    /**
     * What a receipt adds to Spent by itself: its total, unless linked items already carry it.
     * Receipts loaded without a shopping_items_count are treated as unlinked.
     */
    public function unlinkedReceiptYen(Receipt $receipt): int
    {
        if ($receipt->pre_tax_yen === null || (int) ($receipt->shopping_items_count ?? 0) > 0) {
            return 0;
        }

        return (int) $receipt->pre_tax_yen + $this->tax->taxOf($receipt);
    }

    /**
     * @param  list<ShoppingItem>  $items
     * @param  list<Receipt>  $receipts
     * @return array{0: list<array{n: int, date: string, rm: float, items: int, future: bool}>, 1: float}
     */
    private function days(array $items, array $receipts): array
    {
        $count = $this->dayNumber($this->endDate);
        $today = $this->dayNumber($this->today);
        $yenByDay = array_fill(1, max(1, $count), 0);
        $itemsByDay = array_fill(1, max(1, $count), 0);
        $beforeYen = 0;

        $spends = [];
        foreach ($items as $item) {
            if ($item->actual_yen !== null && $item->paid_at !== null) {
                $spends[] = [CarbonImmutable::parse($item->paid_at), (int) $item->actual_yen];
            }
        }
        foreach ($receipts as $receipt) {
            $yen = $this->unlinkedReceiptYen($receipt);
            if ($yen > 0 && $receipt->purchased_at !== null) {
                $spends[] = [CarbonImmutable::parse($receipt->purchased_at), $yen];
            }
        }

        foreach ($spends as [$at, $yen]) {
            $n = $this->dayNumber($at->setTimezone($this->timezone)->toDateString());
            if ($n < 1) {
                $beforeYen += $yen;
            } elseif ($n <= $count) {
                $yenByDay[$n] += $yen;
                $itemsByDay[$n]++;
            }
        }

        $days = [];
        foreach ($yenByDay as $n => $yen) {
            $days[] = [
                'n' => $n,
                'date' => CarbonImmutable::parse($this->startDate)->addDays($n - 1)->toDateString(),
                'rm' => $this->toRm($yen),
                'items' => $itemsByDay[$n],
                'future' => $n > $today,
            ];
        }

        return [$days, $this->toRm($beforeYen)];
    }

    /**
     * Categories with spending, largest first.
     *
     * @param  list<ShoppingItem>  $items
     * @param  list<Receipt>  $receipts
     * @return list<array{key: string, label: string, rm: float}>
     */
    private function categories(array $items, array $receipts): array
    {
        $yen = [];
        foreach ($items as $item) {
            if ((int) $item->actual_yen > 0) {
                $key = $item->category->value;
                $yen[$key] = ($yen[$key] ?? 0) + (int) $item->actual_yen;
            }
        }
        $receiptYen = array_sum(array_map(fn (Receipt $r): int => $this->unlinkedReceiptYen($r), $receipts));
        if ($receiptYen > 0) {
            $other = ShoppingCategory::Other->value;
            $yen[$other] = ($yen[$other] ?? 0) + $receiptYen;
        }
        arsort($yen);

        return array_map(fn (string $key, int $value): array => [
            'key' => $key,
            'label' => ShoppingCategory::from($key)->label(),
            'rm' => $this->toRm($value),
        ], array_keys($yen), array_values($yen));
    }

    /**
     * Budget by person, for the group view.
     *
     * @return list<array{user_id: int, name: string, spent_rm: float, budget_rm: float, used_pct: int, over_budget: bool, left_rm: float}>
     */
    private function people(): array
    {
        $items = $this->items;
        $receipts = $this->receipts;

        return array_map(function (array $member) use ($items, $receipts): array {
            $spentRm = $this->toRm(array_sum(array_map(
                fn (ShoppingItem $i): int => (int) $i->user_id === $member['user_id'] ? (int) $i->actual_yen : 0,
                $items,
            )) + array_sum(array_map(
                fn (Receipt $r): int => (int) $r->user_id === $member['user_id'] ? $this->unlinkedReceiptYen($r) : 0,
                $receipts,
            )));
            $budgetRm = round($member['budget_rm'], 2);

            return [
                'user_id' => $member['user_id'],
                'name' => $member['name'],
                'spent_rm' => $spentRm,
                'budget_rm' => $budgetRm,
                'used_pct' => $budgetRm > 0 ? (int) round($spentRm / $budgetRm * 100) : 0,
                'over_budget' => self::isOverBudget($spentRm, $budgetRm),
                'left_rm' => round($budgetRm - $spentRm, 2),
            ];
        }, $this->members);
    }
}
