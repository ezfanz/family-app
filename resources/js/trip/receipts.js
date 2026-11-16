/*
 * Shopping → Receipts: tax refund tracking (Japan's refund method, from 1 Nov 2026).
 * Stores and customs keep the official records; this shows what each person can
 * expect back. Refunds never change the shopping Balance (budget − spent).
 */

import { S, UI, canManageShoppingOf } from './store.js';
import { $, esc, yen, rm, fmtDate, weekday, toast } from './util.js';
import { apiUrl } from './api.js';
import { write } from './writer.js';
import { rate, selectedShopper } from './shopping.js';
import * as tax from './taxcalc.js';

const FILTERS = [
    ['all', 'All'],
    ['eligible', 'Eligible'],
    ['customs_confirmed', 'Customs confirmed'],
    ['refunded', 'Refunded'],
    ['not_eligible', 'Not eligible'],
];

const STATUS_LABEL = {
    captured: 'Needs amount',
    eligible: 'Eligible',
    not_eligible: 'Not eligible',
    customs_confirmed: 'Customs confirmed',
    refunded: 'Refunded',
    not_received: 'Not received',
};

const minimum = () => Number(S.taxRules?.min_pre_tax_yen) || 5000;

/** Receipts for the person (or everyone, for the owner on "Everyone") in view. */
function receiptsInView() {
    const sel = selectedShopper();
    const all = [...S.receipts.values()];
    return sel === 'all' ? all : all.filter((r) => Number(r.user_id) === Number(sel));
}

const nameOf = (userId) => S.shoppers.find((p) => Number(p.user_id) === Number(userId))?.name || 'Someone';

const timeOf = (receipt) => new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' }).format(new Date(receipt.purchased_at));

/** "Sun 22 Nov · Day 2 · Osaka" */
function dayHeading(isoDay) {
    const day = S.days.find((d) => d.date === isoDay);
    const parts = [`${weekday(isoDay, true)} ${fmtDate(isoDay)}`];
    if (day) {
        parts.push(`Day ${day.day_number}`);
        if (day.city) parts.push(day.city);
    }
    return parts.join(' · ');
}

/** Linked shopping item names, else a plain label. */
function receiptTitle(receipt) {
    const names = (receipt.shopping_item_ids || []).map((id) => S.items.get(id)?.name).filter(Boolean);
    if (names.length) return names.join(', ');
    return receipt.pre_tax_yen == null ? 'Photo saved' : 'Receipt';
}

function thumbHtml(receipt) {
    if ((receipt.photo_parts || []).includes(1)) {
        return `<img class="rc-thumb" src="${esc(apiUrl(`/receipts/${receipt.id}/photos/1`))}" alt="" loading="lazy">`;
    }
    return '<span class="rc-thumb blank" aria-hidden="true"><span></span><span></span><span></span><span></span></span>';
}

function rowHtml(receipt, status, editable) {
    const known = receipt.pre_tax_yen != null;
    const pre = Number(receipt.pre_tax_yen) || 0;
    const counts = !['captured', 'not_eligible'].includes(status);
    const t = tax.taxOf(receipt);
    const meta = known
        ? `${timeOf(receipt)} · ${yen(pre + t)} paid · ${yen(pre)} before tax${receipt.is_estimated ? ' (estimated)' : ''}${receipt.tax_rate === 8 ? ' · 8%' : ''}`
        : `${timeOf(receipt)} · add the amount`;
    const refund = status === 'refunded' ? (receipt.refund_received_yen ?? t) : t;
    const refundWord = !counts ? 'no refund' : status === 'refunded' ? 'received' : 'tax back';

    return `<li class="rc-row" data-id="${esc(receipt.id)}">
        ${thumbHtml(receipt)}
        <div class="rc-main">
            <div class="rc-title">${esc(receiptTitle(receipt))}</div>
            <div class="rc-meta tnum">${esc(meta)}</div>
            <span class="rc-pill ${status}">${STATUS_LABEL[status]}</span>
        </div>
        <div class="rc-side">
            <div class="rc-refund tnum ${status === 'refunded' ? 'got' : counts ? '' : 'none'}">${counts ? yen(refund) : '—'}</div>
            <div class="rc-word">${refundWord}</div>
            ${editable && status === 'customs_confirmed' ? '<button type="button" class="rc-mark" data-act="mark-refunded">Mark refunded</button>' : ''}
        </div>
    </li>`;
}

function renderSummary(receipts) {
    const sum = tax.totals(receipts, minimum());
    const pct = (v) => (sum.expected ? ((v / sum.expected) * 100).toFixed(1) : 0);
    $('rcExpected').textContent = yen(sum.expected);
    $('rcExpectedRm').textContent = `≈ ${rm((sum.expected * rate()) / 100)}`;
    $('rcReceived').textContent = yen(sum.received);
    $('rcWaiting').textContent = yen(sum.waiting + sum.not_received);
    $('rcToConfirm').textContent = yen(sum.to_confirm);
    $('rcBarReceived').style.width = `${pct(sum.received)}%`;
    $('rcBarWaiting').style.width = `${pct(sum.waiting + sum.not_received)}%`;
}

function renderFilters() {
    $('rcFilters').innerHTML = FILTERS.map(([key, label]) =>
        `<button type="button" class="chip rc-chip" data-filter="${key}" aria-pressed="${UI.receiptFilter === key}">${label}</button>`).join('');
}

function renderList(receipts) {
    const groups = tax.groupTotals(receipts);
    const sorted = [...receipts].sort((a, b) => new Date(a.purchased_at) - new Date(b.purchased_at));

    // Group by Japan day, then by person + store + branch (the eligibility group).
    const byGroup = new Map();
    for (const receipt of sorted) {
        const key = tax.groupKey(receipt);
        if (!byGroup.has(key)) byGroup.set(key, []);
        byGroup.get(key).push(receipt);
    }

    let lastDay = null;
    const html = [];
    for (const [key, members] of byGroup) {
        const first = members[0];
        const rows = members
            .map((r) => [r, tax.statusOf(r, groups, minimum())])
            .filter(([, status]) => UI.receiptFilter === 'all' || status === UI.receiptFilter || (UI.receiptFilter === 'customs_confirmed' && status === 'not_received'));
        if (!rows.length) continue;

        const day = tax.dayOf(first);
        if (day !== lastDay) {
            html.push(`<h2 class="rc-day tnum">${esc(dayHeading(day))}</h2>`);
            lastDay = day;
        }
        const total = groups.get(key) || 0;
        const short = Math.max(0, minimum() - total);
        const pending = members.every((r) => r.pre_tax_yen == null);
        const tag = pending ? '' : short === 0
            ? '<span class="rc-tag ok">Eligible</span>'
            : `<span class="rc-tag short tnum">${yen(short)} more to qualify</span>`;
        const who = selectedShopper() === 'all' ? `${nameOf(first.user_id)} · ` : '';
        const sub = [first.branch, `${members.length} receipt${members.length === 1 ? '' : 's'}`, `${yen(total)} before tax`].filter(Boolean).join(' · ');

        html.push(`<section class="rc-group">
            <div class="rc-head">
                <div style="min-width:0"><div class="rc-store">${esc(first.store_name || 'Store not set')}</div><div class="rc-sub tnum">${esc(who + sub)}</div></div>
                ${tag}
            </div>
            <ul class="rc-list">${rows.map(([r, status]) => rowHtml(r, status, canManageShoppingOf(r.user_id))).join('')}</ul>
        </section>`);
    }

    $('rcGroups').innerHTML = html.join('');
    const none = html.length === 0;
    $('rcEmpty').classList.toggle('hidden', !none || receipts.length === 0);
    $('rcNone').classList.toggle('hidden', receipts.length !== 0);
}

export function renderReceipts() {
    const receipts = receiptsInView();
    const sel = selectedShopper();
    $('shopLede').textContent = sel === 'all'
        ? 'Everyone’s receipts and tax refunds'
        : `${S.shoppers.find((p) => p.user_id === sel)?.is_me ? 'Your' : `${nameOf(sel)}’s`} receipts and tax refunds`;
    $('rcActions').classList.toggle('hidden', sel !== 'all' && !canManageShoppingOf(sel));
    renderSummary(receipts);
    renderFilters();
    renderList(receipts);
}

/** Switch the Shopping page between the list and receipts. */
export function renderShopTab() {
    const receiptsTab = UI.shopTab === 'receipts';
    $('v-shopping').dataset.tab = UI.shopTab;
    document.querySelectorAll('[data-shop-tab]').forEach((b) => b.setAttribute('aria-current', b.dataset.shopTab === UI.shopTab ? 'page' : 'false'));
    if (!receiptsTab) $('shopLede').textContent = 'Barang kirim for family back home';
    if (receiptsTab) renderReceipts();
}

let markingId = null;

function openMarkRefunded(receipt) {
    markingId = receipt.id;
    const t = tax.taxOf(receipt);
    $('refundAmount').value = String(receipt.refund_received_yen ?? t);
    $('refundHint').textContent = `Expected ${yen(t)} tax back from ${receipt.store_name || 'this store'}. Enter what actually arrived.`;
    $('refundDlg').showModal();
}

function saveStage(receipt, changes) {
    const before = { ...receipt };
    Object.assign(receipt, changes);
    renderReceipts();
    write({
        key: `receipt:${receipt.id}`,
        method: 'PATCH',
        path: `/receipts/${receipt.id}`,
        body: changes,
        onSaved: (data) => { if (data?.data) S.receipts.set(receipt.id, data.data); renderReceipts(); },
        revert: () => { S.receipts.set(receipt.id, before); renderReceipts(); },
    });
}

export function bindReceipts({ onSnap, onAirport } = {}) {
    $('v-shopping').addEventListener('click', (e) => {
        const tab = e.target.closest('[data-shop-tab]');
        if (tab) {
            UI.shopTab = tab.dataset.shopTab;
            renderShopTab();
            return;
        }
        const chip = e.target.closest('[data-filter]');
        if (chip && chip.closest('#rcFilters')) {
            UI.receiptFilter = chip.dataset.filter;
            renderReceipts();
            return;
        }
        const mark = e.target.closest('[data-act="mark-refunded"]');
        if (mark) {
            const receipt = S.receipts.get(mark.closest('[data-id]').dataset.id);
            if (receipt && canManageShoppingOf(receipt.user_id)) openMarkRefunded(receipt);
        }
    });

    $('rcSnap').addEventListener('click', () => (onSnap ? onSnap() : toast('Camera comes in the next step.')));
    $('rcAirport').addEventListener('click', () => (onAirport ? onAirport() : toast('Airport checklist comes in a later step.')));

    $('refundForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const receipt = S.receipts.get(markingId);
        const amount = Math.round(Number($('refundAmount').value));
        if (!receipt || !(amount >= 0)) return;
        $('refundDlg').close();
        saveStage(receipt, { stage: 'refunded', refund_received_yen: amount });
        toast(`Marked ${yen(amount)} as received.`);
    });
    $('refundNotReceived').addEventListener('click', () => {
        const receipt = S.receipts.get(markingId);
        if (!receipt) return;
        $('refundDlg').close();
        saveStage(receipt, { stage: 'not_received', refund_received_yen: null });
    });
}

