/*
 * Shopping overview numbers, mirroring app/Services/ShoppingOverview.php so the
 * screen works offline. Keep the two in step (tests/Unit/ShoppingOverviewTest.php
 * holds the expected numbers from the design's sample data).
 */

import * as tax from './taxcalc.js';

export const AXIS_STEPS = [300, 600, 1200];

export const CATEGORIES = {
    clothing_bags: 'Clothing and bags',
    home_kitchen: 'Home and kitchen',
    snacks_food: 'Snacks and food',
    games_toys: 'Games and toys',
    beauty: 'Beauty',
    other: 'Other',
};

const JAPAN_DAY = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit' });
const round2 = (n) => Math.round(n * 100) / 100;

/** Smallest axis top (300, 600 or 1200 RM) that fits the peak; larger peaks round up to the next 600. */
export function axisMax(peakRm) {
    for (const step of AXIS_STEPS) {
        if (peakRm <= step) return step;
    }
    return Math.ceil(peakRm / 600) * 600;
}

export const isOverBudget = (spentRm, budgetRm) => round2(spentRm) > round2(budgetRm);

/** The latest past day with spending, else today (kept inside the trip). */
export function latestDay(days, todayNumber) {
    const spent = days.filter((d) => !d.future && d.rm > 0).map((d) => d.n);
    if (spent.length) return Math.max(...spent);
    return Math.max(1, Math.min(days.length, todayNumber));
}

const dayDiff = (fromIso, toIso) => Math.round((Date.parse(`${toIso}T00:00:00Z`) - Date.parse(`${fromIso}T00:00:00Z`)) / 864e5);

/**
 * @param {{ members: {user_id:number,name:string,budget_rm:number}[], items: object[], receipts: object[],
 *           rate: number, startDate: string, endDate: string, today: string, minimum: number }} data
 * @param {number|null} userId null = everyone
 */
export function overviewFor(data, userId) {
    const toRm = (yen) => round2((yen * data.rate) / 100);
    const dayNumber = (iso) => dayDiff(data.startDate, iso) + 1;
    const members = data.members.filter((m) => userId === null || Number(m.user_id) === Number(userId));
    const ids = new Set(members.map((m) => Number(m.user_id)));
    const items = data.items.filter((i) => ids.has(Number(i.user_id)));
    const receipts = data.receipts.filter((r) => ids.has(Number(r.user_id)));

    const spentYen = items.reduce((sum, i) => sum + (Number(i.actual_yen) || 0), 0);
    const spentRm = toRm(spentYen);
    const budgetRm = round2(members.reduce((sum, m) => sum + (Number(m.budget_rm) || 0), 0));
    const bought = items.filter((i) => i.is_bought).length;

    const groups = tax.groupTotals(receipts);
    const eligible = receipts.filter((r) => !['captured', 'not_eligible'].includes(tax.statusOf(r, groups, data.minimum))).length;
    const refundYen = tax.totals(receipts, data.minimum).expected;

    // Spent by day: the Japan-time day Paid ¥ was first entered.
    const count = Math.max(1, dayNumber(data.endDate));
    const today = dayNumber(data.today);
    const yenByDay = new Array(count + 1).fill(0);
    const itemsByDay = new Array(count + 1).fill(0);
    let beforeYen = 0;
    for (const item of items) {
        if (item.actual_yen == null || !item.paid_at) continue;
        const n = dayNumber(JAPAN_DAY.format(new Date(item.paid_at)));
        if (n < 1) {
            beforeYen += Number(item.actual_yen);
        } else if (n <= count) {
            yenByDay[n] += Number(item.actual_yen);
            itemsByDay[n] += 1;
        }
    }
    const days = [];
    for (let n = 1; n <= count; n += 1) {
        const date = new Date(`${data.startDate}T00:00:00Z`);
        date.setUTCDate(date.getUTCDate() + n - 1);
        days.push({ n, date: date.toISOString().slice(0, 10), rm: toRm(yenByDay[n]), items: itemsByDay[n], future: n > today });
    }

    const byCategory = new Map();
    for (const item of items) {
        if ((Number(item.actual_yen) || 0) > 0) {
            const key = item.category || 'other';
            byCategory.set(key, (byCategory.get(key) || 0) + Number(item.actual_yen));
        }
    }
    const categories = [...byCategory]
        .sort((a, b) => b[1] - a[1])
        .map(([key, yen]) => ({ key, label: CATEGORIES[key] || 'Other', rm: toRm(yen) }));

    const people = userId !== null ? [] : data.members.map((m) => {
        const mineRm = toRm(data.items.filter((i) => Number(i.user_id) === Number(m.user_id)).reduce((sum, i) => sum + (Number(i.actual_yen) || 0), 0));
        const budget = round2(Number(m.budget_rm) || 0);
        return {
            user_id: m.user_id,
            name: m.name,
            spent_rm: mineRm,
            budget_rm: budget,
            used_pct: budget > 0 ? Math.round((mineRm / budget) * 100) : 0,
            over_budget: isOverBudget(mineRm, budget),
            left_rm: round2(budget - mineRm),
        };
    });

    return {
        user_id: userId,
        spent_yen: spentYen,
        spent_rm: spentRm,
        budget_rm: budgetRm,
        used_pct: budgetRm > 0 ? Math.round((spentRm / budgetRm) * 100) : 0,
        over_budget: isOverBudget(spentRm, budgetRm),
        left_rm: round2(budgetRm - spentRm),
        items_total: items.length,
        items_bought: bought,
        items_to_buy: items.length - bought,
        receipts: receipts.length,
        receipts_eligible: eligible,
        refund_yen: refundYen,
        refund_rm: toRm(refundYen),
        days,
        before_trip_rm: toRm(beforeYen),
        axis_max: axisMax(Math.max(0, ...days.map((d) => d.rm))),
        selected_day: latestDay(days, today),
        categories,
        people,
    };
}
