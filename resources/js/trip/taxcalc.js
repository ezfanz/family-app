/*
 * Japan's refund-method rules, mirroring app/Services/TaxRefund/TaxRefundCalculator.php
 * so receipts can be checked offline. Keep the two in step.
 *
 * Receipts group by person + store + branch + calendar day in Japan. A group whose
 * before-tax total reaches the minimum makes every receipt in it eligible.
 */

const JAPAN_DAY = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Tokyo', year: 'numeric', month: '2-digit', day: '2-digit' });

/** Before-tax amount when only the tax-inclusive total is known (÷ 1.10, or ÷ 1.08 for food). */
export const estimatePreTax = (totalPaidYen, taxRate = 10) => Math.round((totalPaidYen * 100) / (100 + taxRate));

/** Consumption tax, rounded down to the yen like Japanese tills. */
export const taxOf = (receipt) => Math.floor(((Number(receipt.pre_tax_yen) || 0) * (Number(receipt.tax_rate) || 10)) / 100);

/** Japan-time calendar date, e.g. "2026-11-22". */
export const dayOf = (receipt) => JAPAN_DAY.format(new Date(receipt.purchased_at));

const normalise = (text) => String(text || '').trim().toLowerCase();

export const groupKey = (receipt) => [receipt.user_id, normalise(receipt.store_name), normalise(receipt.branch), dayOf(receipt)].join('|');

/** Before-tax total per group (receipts without an amount count as zero). */
export function groupTotals(receipts) {
    const totals = new Map();
    for (const receipt of receipts) {
        const key = groupKey(receipt);
        totals.set(key, (totals.get(key) || 0) + (Number(receipt.pre_tax_yen) || 0));
    }
    return totals;
}

/** How much more to spend in the group to qualify (0 when it already does). */
export const shortfall = (receipt, totals, minimum) => Math.max(0, minimum - (totals.get(groupKey(receipt)) || 0));

/** 'captured' | 'eligible' | 'not_eligible' | 'customs_confirmed' | 'refunded' | 'not_received' */
export function statusOf(receipt, totals, minimum) {
    if (receipt.stage === 'captured' || receipt.pre_tax_yen == null) return 'captured';
    if (shortfall(receipt, totals, minimum) > 0) return 'not_eligible';
    return receipt.stage === 'checked' ? 'eligible' : receipt.stage;
}

/** Last day the goods can leave Japan for this receipt, "YYYY-MM-DD". */
export function leaveBy(receipt, daysToExport) {
    const date = new Date(`${dayOf(receipt)}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + daysToExport);
    return date.toISOString().slice(0, 10);
}

/**
 * Expected = tax on qualifying receipts. Received = refunds that arrived (amount entered, else tax).
 * Waiting = customs confirmed, not refunded yet. To confirm = eligible, customs not done.
 */
export function totals(receipts, minimum) {
    const groups = groupTotals(receipts);
    const sum = { expected: 0, received: 0, waiting: 0, to_confirm: 0, not_received: 0 };
    for (const receipt of receipts) {
        const status = statusOf(receipt, groups, minimum);
        if (status === 'captured' || status === 'not_eligible') continue;
        const tax = taxOf(receipt);
        sum.expected += tax;
        if (status === 'refunded') sum.received += receipt.refund_received_yen ?? tax;
        else if (status === 'customs_confirmed') sum.waiting += tax;
        else if (status === 'not_received') sum.not_received += tax;
        else sum.to_confirm += tax;
    }
    return sum;
}
