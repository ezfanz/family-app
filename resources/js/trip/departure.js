/*
 * Departure day: tax refund at the airport. A personal checklist (saved per
 * person) and one "I've done customs" button: the kiosk confirms every
 * purchase linked to your passport at once, so all eligible receipts move on.
 */

import { S } from './store.js';
import { $, esc, yen, fmtDate, weekday, toast } from './util.js';
import { write } from './writer.js';
import * as tax from './taxcalc.js';

const STEPS = [
    ['passport', 'Passport', () => 'The real one, not a copy'],
    ['hand_luggage', 'Tax-free goods in hand luggage', () => 'Officers may ask to see them'],
    ['receipts_open', 'Receipts open in the app', (mine) => `${mine.count} eligible receipt${mine.count === 1 ? '' : 's'}, ${yen(mine.tax)} tax`],
    ['customs_first', 'Customs before check-in and security', () => 'Look for the tax-free export counter or kiosk'],
];

const CHECK = '<svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';

let onChange = () => {};
/** The last "I've done customs" request, so an undo goes after it. */
let customsRequest = Promise.resolve();
const minimum = () => Number(S.taxRules?.min_pre_tax_yen) || 5000;

/** My receipts that count for a refund, with their derived status. */
function myQualifying() {
    const mine = [...S.receipts.values()].filter((r) => Number(r.user_id) === Number(S.me));
    const groups = tax.groupTotals(mine);
    return mine
        .map((receipt) => ({ receipt, status: tax.statusOf(receipt, groups, minimum()) }))
        .filter(({ status }) => !['captured', 'not_eligible'].includes(status))
        .sort((a, b) => new Date(a.receipt.purchased_at) - new Date(b.receipt.purchased_at));
}

function render() {
    const last = S.days[S.days.length - 1];
    $('airDay').textContent = last ? `${weekday(last.date, true)} ${fmtDate(last.date)} · Day ${last.day_number} · Departure` : 'Departure day';

    const rows = myQualifying();
    const eligible = rows.filter((r) => r.status === 'eligible');
    const summary = { count: rows.length, tax: rows.reduce((sum, r) => sum + tax.taxOf(r.receipt), 0) };

    $('airSteps').innerHTML = STEPS.map(([id, title, sub]) => {
        const done = S.checklist.includes(id);
        return `<li><label class="air-step${done ? ' done' : ''}"><input type="checkbox" value="${id}" ${done ? 'checked' : ''}><span><span class="air-step-title">${esc(title)}</span><span class="air-step-sub">${esc(sub(summary))}</span></span></label></li>`;
    }).join('');

    const confirmed = rows.length - eligible.length;
    $('airProgress').textContent = rows.length ? `${confirmed} of ${rows.length} confirmed` : '';
    $('airBar').style.width = `${rows.length ? (confirmed / rows.length) * 100 : 0}%`;
    $('airNone').classList.toggle('hidden', rows.length > 0);
    $('airReceipts').innerHTML = rows.map(({ receipt, status }) => {
        const pre = Number(receipt.pre_tax_yen) || 0;
        const t = tax.taxOf(receipt);
        const day = tax.dayOf(receipt);
        const done = status !== 'eligible';
        const word = { customs_confirmed: 'Confirmed', refunded: 'Refunded', not_received: 'Confirmed' }[status] || 'To confirm';
        return `<li class="air-row">
            <div style="min-width:0"><div class="air-store">${esc([receipt.store_name, receipt.branch].filter(Boolean).join(' · '))}</div>
            <div class="muted tnum air-meta">${fmtDate(day)} · ${yen(pre + t)} paid · ${yen(t)} tax</div></div>
            <span class="air-state${done ? ' done' : ''}">${done ? CHECK : ''}${word}</span>
        </li>`;
    }).join('');

    $('airDone').disabled = eligible.length === 0;
    $('airDone').textContent = eligible.length
        ? `I’ve done customs (${eligible.length} receipt${eligible.length === 1 ? '' : 's'})`
        : (rows.length ? 'All confirmed' : 'Nothing to confirm yet');
}

export function openAirport() {
    render();
    $('airDlg').showModal();
}

function saveChecklist() {
    write({
        key: 'checklist',
        method: 'PUT',
        path: `/trips/${S.trip.id}/airport-checklist`,
        body: { checked: S.checklist },
    });
}

function customsDone() {
    const eligible = myQualifying().filter((r) => r.status === 'eligible').map((r) => r.receipt);
    if (!eligible.length) return;
    const now = new Date().toISOString();
    const before = eligible.map((r) => ({ ...r }));
    eligible.forEach((r) => Object.assign(r, { stage: 'customs_confirmed', customs_confirmed_at: now }));
    render();
    onChange();
    customsRequest = write({
        key: 'customs-done',
        method: 'POST',
        path: `/trips/${S.trip.id}/customs-done`,
        onSaved: (data) => { (data?.data || []).forEach((r) => S.receipts.set(r.id, r)); render(); onChange(); },
        revert: () => { before.forEach((r) => S.receipts.set(r.id, r)); render(); onChange(); },
    });
    toast(`${eligible.length} receipt${eligible.length === 1 ? '' : 's'} confirmed. Refunds now wait on each store.`, {
        action: 'Undo',
        onAction: () => undoCustoms(before),
    });
}

/** Put receipts back to "to confirm" (a mistaken tap). */
async function undoCustoms(previous) {
    previous.forEach((old) => S.receipts.set(old.id, old));
    render();
    onChange();
    await customsRequest.catch(() => {});
    for (const old of previous) {
        write({ key: `receipt:${old.id}`, method: 'PATCH', path: `/receipts/${old.id}`, body: { stage: 'checked' } });
    }
}

/**
 * @param {{ onChange: () => void }} hooks re-render receipts after customs is confirmed
 */
export function bindDeparture(hooks) {
    onChange = hooks.onChange;
    $('airSteps').addEventListener('change', (e) => {
        if (e.target.type !== 'checkbox') return;
        const checked = new Set(S.checklist);
        e.target.checked ? checked.add(e.target.value) : checked.delete(e.target.value);
        S.checklist = [...checked];
        render();
        saveChecklist();
    });
    $('airDone').addEventListener('click', customsDone);
}
