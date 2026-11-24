/*
 * Shopping → Receipts: tax refund tracking (Japan's refund method, from 1 Nov 2026).
 * Stores and customs keep the official records; this shows what each person can
 * expect back. Refunds never change the shopping Balance (budget − spent).
 */

import { S, UI, canManageShoppingOf, isOwner } from './store.js';
import { $, esc, yen, rm, fmtDate, weekday, toast, uuid } from './util.js';
import { apiUrl } from './api.js';
import { write } from './writer.js';
import { rate, selectedShopper, avatar, displayName, ROLE_LABEL, renderShop, forWhoKey, forWhoLabel, UNASSIGNED } from './shopping.js';
import * as tax from './taxcalc.js';
import { queuePhoto, flushPhotos, localPhotoUrl, waitingReceiptIds, onPhotosChanged, restoreLocalUrls } from './photo-queue.js';
import { openCamera } from './camera.js';
import { openReview, bindReview, readIfOpen } from './receipt-review.js';
import { scanPhotos, bindScanner, warmScanner } from './scanner.js';
import { openAirport, bindDeparture } from './departure.js';
import { renderOverview, bindOverview } from './overview.js';

/** Receipts with a photo still on this phone, waiting to upload. */
let waitingUpload = new Set();

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
    const local = localPhotoUrl(receipt.id, 1);
    if (local) {
        return `<img class="rc-thumb" src="${esc(local)}" alt="">`;
    }
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

    const open = editable ? ` tabindex="0" role="button" aria-label="Open receipt: ${esc(receiptTitle(receipt))}, ${esc(STATUS_LABEL[status])}"` : '';
    return `<li class="rc-row${editable ? ' openable' : ''}" data-id="${esc(receipt.id)}"${open}>
        ${thumbHtml(receipt)}
        <div class="rc-main">
            <div class="rc-title">${esc(receiptTitle(receipt))}</div>
            <div class="rc-meta tnum">${esc(meta)}</div>
            <span class="rc-pill ${status}">${STATUS_LABEL[status]}</span>${waitingUpload.has(receipt.id) ? '<span class="rc-wait">Photo saved on this phone · uploads when online</span>' : ''}
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

/** "For who" of a receipt, from its linked items; receipts without a name are "Not assigned". */
function forWhoOf(receipt) {
    const items = (receipt.shopping_item_ids || []).map((id) => S.items.get(id)).filter(Boolean);
    const named = new Map();
    for (const item of items) {
        const key = forWhoKey(item.for_whom);
        if (!named.has(key)) named.set(key, key === UNASSIGNED ? 'Not assigned' : forWhoLabel(item.for_whom));
    }
    return named.size ? named : new Map([[UNASSIGNED, 'Not assigned']]);
}

const matchesForWho = (receipt) => UI.receiptForWho === 'all' || forWhoOf(receipt).has(UI.receiptForWho);

/** Name chips (as on the List), shown once a receipt is linked to an item for someone. */
function renderForChips(receipts) {
    const nav = $('rcForChips');
    const counts = new Map();
    for (const receipt of receipts) {
        for (const [key, label] of forWhoOf(receipt)) {
            const entry = counts.get(key) || { label, count: 0 };
            entry.count += 1;
            counts.set(key, entry);
        }
    }
    const useful = [...counts.keys()].some((key) => key !== UNASSIGNED);
    nav.classList.toggle('hidden', !useful);
    if (!useful || (UI.receiptForWho !== 'all' && !counts.has(UI.receiptForWho))) UI.receiptForWho = 'all';
    if (!useful) return;
    const sorted = [...counts].sort(([a, x], [b, y]) => (a === UNASSIGNED) - (b === UNASSIGNED) || x.label.localeCompare(y.label));
    const chip = (key, label, count) => `<button type="button" class="chip for-chip${key === UNASSIGNED ? ' none' : ''}" data-rc-for="${esc(key)}" aria-pressed="${UI.receiptForWho === key}">${esc(label)}<span class="chip-count tnum">${count}</span></button>`;
    nav.innerHTML = chip('all', 'All', receipts.length) + sorted.map(([key, { label, count }]) => chip(key, label, count)).join('');
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
            .filter(([r, status]) => matchesForWho(r) && (UI.receiptFilter === 'all' || status === UI.receiptFilter || (UI.receiptFilter === 'customs_confirmed' && status === 'not_received')));
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
        const sub = [first.branch, `${members.length} receipt${members.length === 1 ? '' : 's'}`, `${yen(total)} before tax`].filter(Boolean).join(' · ');

        html.push(`<section class="rc-group">
            <div class="rc-head">
                <div style="min-width:0"><div class="rc-store">${esc(first.store_name || 'Store not set')}</div><div class="rc-sub tnum">${esc(sub)}</div></div>
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

/** One line about where a person's refunds stand, most useful first. */
function personLine(receipts) {
    if (!receipts.length) return 'No receipts yet';
    const groups = tax.groupTotals(receipts);
    const statuses = receipts.map((r) => tax.statusOf(r, groups, minimum()));
    const count = (status) => statuses.filter((s) => s === status).length;
    const qualifying = statuses.filter((s) => !['captured', 'not_eligible'].includes(s)).length;
    const parts = [`${receipts.length} receipt${receipts.length === 1 ? '' : 's'}`];
    if (count('captured')) {
        parts.push(`${count('captured')} need${count('captured') === 1 ? 's' : ''} an amount`);
    } else if (count('eligible')) {
        parts.push(`${count('eligible')} to confirm`);
    } else if (!qualifying) {
        const short = receipts
            .filter((r, i) => statuses[i] === 'not_eligible')
            .map((r) => ({ store: r.store_name || 'a store', amount: tax.shortfall(r, groups, minimum()) }))
            .sort((a, b) => a.amount - b.amount)[0];
        if (short) parts.push(`${yen(short.amount)} short at ${short.store}`);
    } else if (count('refunded') === qualifying) {
        parts.push('all refunded');
    } else if (count('refunded')) {
        parts.push(`${count('refunded')} refunded`);
    } else {
        parts.push('all confirmed');
    }
    return parts.join(' · ');
}

/** Owner on "Everyone": group totals and a card per person. Waiting = expected − received. */
function renderAdmin() {
    const all = [...S.receipts.values()];
    const sum = tax.totals(all, minimum());
    const waiting = sum.expected - sum.received;
    const pct = (value, of) => (of ? ((value / of) * 100).toFixed(1) : 0);
    const toRm = (y) => rm((y * rate()) / 100);
    $('rcGroupExpected').textContent = yen(sum.expected);
    $('rcGroupExpectedRm').textContent = `≈ ${toRm(sum.expected)}`;
    $('rcGroupReceived').textContent = yen(sum.received);
    $('rcGroupReceivedRm').textContent = toRm(sum.received);
    $('rcGroupWaiting').textContent = yen(waiting);
    $('rcGroupWaitingRm').textContent = toRm(waiting);
    $('rcGroupBarReceived').style.width = `${pct(sum.received, sum.expected)}%`;
    $('rcGroupBarWaiting').style.width = `${pct(waiting, sum.expected)}%`;

    $('rcPeople').innerHTML = S.shoppers.map((person) => {
        const mine = all.filter((r) => Number(r.user_id) === Number(person.user_id));
        const t = tax.totals(mine, minimum());
        const left = t.expected - t.received;
        return `<button type="button" class="rc-person" data-shopper="${person.user_id}">
            ${avatar(person)}
            <span class="rc-person-main">
                <span class="rc-person-name">${esc(displayName(person))}<span class="role-pill">${ROLE_LABEL[person.role] || person.role}</span></span>
                <span class="rc-person-line tnum">${esc(personLine(mine))}</span>
                <span class="rc-bar small" aria-hidden="true"><span class="got" style="width:${pct(t.received, t.expected)}%"></span><span class="wait" style="width:${pct(left, t.expected)}%"></span></span>
                <span class="rc-person-line tnum">${yen(t.received)} received · ${yen(left)} waiting</span>
            </span>
            <span class="rc-person-side">${t.expected ? `<span class="tnum rc-person-exp">${yen(t.expected)}</span><span class="rc-person-word">expected</span>` : '<span class="rc-person-word">No refund yet</span>'}</span>
        </button>`;
    }).join('');
}

export function renderReceipts() {
    const receipts = receiptsInView();
    const sel = selectedShopper();
    const admin = sel === 'all';
    $('rcAdmin').classList.toggle('hidden', !admin);
    $('rcMine').classList.toggle('hidden', admin);
    if (admin) {
        $('shopLede').textContent = 'Everyone’s tax refunds';
        renderAdmin();
        return;
    }
    $('shopLede').textContent = sel === 'all'
        ? 'Everyone’s receipts and tax refunds'
        : `${S.shoppers.find((p) => p.user_id === sel)?.is_me ? 'Your' : `${nameOf(sel)}’s`} receipts and tax refunds`;
    $('rcActions').classList.toggle('hidden', sel !== 'all' && !canManageShoppingOf(sel));
    renderSummary(receipts);
    renderFilters();
    renderForChips(receipts);
    renderList(receipts);
}

/** Switch the Shopping page between the list and receipts. */
export function renderShopTab() {
    $('v-shopping').dataset.tab = UI.shopTab;
    document.querySelectorAll('[data-shop-tab]').forEach((b) => b.setAttribute('aria-current', b.dataset.shopTab === UI.shopTab ? 'page' : 'false'));
    if (UI.shopTab === 'overview') renderOverview();
    else if (UI.shopTab === 'receipts') renderReceipts();
    else $('shopLede').textContent = 'Barang kirim for family back home';
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

/** Whose receipt a new snap belongs to: the person in view if I can manage theirs, else me. */
function snapOwner() {
    const sel = selectedShopper();
    return sel !== 'all' && canManageShoppingOf(sel) ? Number(sel) : S.me;
}

/** Keep the server copy of a receipt after an upload or save, keeping any local edits pending. */
function storeServerReceipt(data) {
    if (data?.id && !S.pending.has(`receipt:${data.id}`)) S.receipts.set(data.id, data);
}

export function uploadWaitingPhotos() {
    return flushPhotos((receipt) => { storeServerReceipt(receipt); renderReceipts(); readIfOpen(receipt?.id); });
}

/**
 * Save a snapped receipt straight away (photo on this phone, receipt in the
 * write queue), so it is never lost to a bad signal. The amount comes next.
 *
 * @param {Blob[]} blobs one or two parts
 */
async function saveSnapped(blobs) {
    const receipt = {
        id: uuid(),
        user_id: snapOwner(),
        store_name: null,
        branch: null,
        purchased_at: new Date().toISOString(),
        pre_tax_yen: null,
        tax_rate: 10,
        is_estimated: false,
        stage: 'captured',
        photo_parts: [],
        shopping_item_ids: [],
    };
    S.receipts.set(receipt.id, receipt);
    for (const [index, blob] of blobs.entries()) {
        await queuePhoto(receipt.id, index + 1, blob);
    }
    UI.receiptFilter = 'all';
    UI.receiptForWho = 'all';
    renderReceipts();
    write({
        key: `receipt:${receipt.id}`,
        method: 'POST',
        path: '/receipts',
        body: { id: receipt.id, trip_id: S.trip.id, user_id: receipt.user_id, purchased_at: receipt.purchased_at },
        onSaved: (data) => { storeServerReceipt(data?.data); uploadWaitingPhotos(); },
        revert: () => { S.receipts.delete(receipt.id); renderReceipts(); },
    });
    if (!navigator.onLine) toast('Photo saved on this phone. It uploads when you’re back online.');
    openReview(receipt.id);
    return receipt;
}

export function bindReceipts({ onSnap, onAirport } = {}) {
    onPhotosChanged(async () => {
        waitingUpload = await waitingReceiptIds();
        if (UI.shopTab === 'receipts' && UI.view === 'shopping') renderReceipts();
    });
    restoreLocalUrls();
    bindScanner();
    bindReview({ onChange: () => { renderReceipts(); uploadWaitingPhotos(); } });
    bindDeparture({ onChange: () => { if (UI.view === 'shopping') renderReceipts(); } });
    // Overview tiles open List or Receipts for the person being shown.
    bindOverview({
        onGo: (tab, userId) => {
            UI.shopTab = tab;
            if (isOwner()) UI.shopSel = userId ?? 'all';
            renderShop();
        },
    });
    window.addEventListener('online', () => uploadWaitingPhotos());

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
        const forChip = e.target.closest('[data-rc-for]');
        if (forChip) {
            UI.receiptForWho = forChip.dataset.rcFor;
            renderReceipts();
            return;
        }
        const mark = e.target.closest('[data-act="mark-refunded"]');
        if (mark) {
            const receipt = S.receipts.get(mark.closest('[data-id]').dataset.id);
            if (receipt && canManageShoppingOf(receipt.user_id)) openMarkRefunded(receipt);
            return;
        }
        const row = e.target.closest('.rc-row.openable');
        if (row) openReview(row.dataset.id);
    });

    $('rcGroups').addEventListener('keydown', (e) => {
        const row = e.target.closest('.rc-row.openable');
        if (row && e.target === row && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            openReview(row.dataset.id);
        }
    });

    $('rcSnap').addEventListener('click', () => {
        if (onSnap) {
            onSnap();
            return;
        }
        warmScanner(); // loads while the receipt is being framed
        openCamera(async (blobs) => saveSnapped(await scanPhotos(blobs)));
    });
    $('rcAirport').addEventListener('click', () => (onAirport ? onAirport() : openAirport()));

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

