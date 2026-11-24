/*
 * Check receipt: store, date and time, one amount (before tax or total paid),
 * the tax rate, whose receipt it is and optional shopping links, with a live
 * eligibility banner. Works offline: saves go through the write queue.
 *
 * Phase 2 (automatic reading) can call markLowConfidence() with the fields a
 * ReceiptReader was unsure about; they get the amber "Check this" state.
 */

import { S, isOwner, canManageShoppingOf } from './store.js';
import { $, esc, yen, rm, fmtDate, toast, uuid } from './util.js';
import { api, apiUrl } from './api.js';
import { write } from './writer.js';
import { rate, forWhoNames } from './shopping.js';
import * as tax from './taxcalc.js';
import { openCamera } from './camera.js';
import { scanPhotos, warmScanner } from './scanner.js';
import { queuePhoto, localPhotoUrl } from './photo-queue.js';

let editingId = null;
let linked = [];
/** Receipt lines (by index) the person unticked in "Add to shopping list". */
let addSkipped = new Set();
/** Whether "For who" was typed, so changing whose receipt it is leaves it alone. */
let addForTyped = false;
let onChange = () => {};

const minimum = () => Number(S.taxRules?.min_pre_tax_yen) || 5000;
const JAPAN = { timeZone: 'Asia/Tokyo' };
const japanDate = (iso) => new Intl.DateTimeFormat('en-CA', { ...JAPAN, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(iso));
const japanTime = (iso) => new Intl.DateTimeFormat('en-GB', { ...JAPAN, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date(iso));
const form = () => $('rvForm');
const radio = (name) => form().querySelector(`input[name="${name}"]:checked`)?.value;

/** The receipt as it would be saved, from what is on screen. */
function draft() {
    const receipt = S.receipts.get(editingId);
    const amount = Math.round(Number($('rvAmount').value)) || 0;
    const taxRate = Number(radio('tax_rate')) || 10;
    const isTotal = radio('amount_kind') === 'total';
    const date = $('rvDate').value;
    const time = $('rvTime').value || '12:00';
    return {
        ...receipt,
        user_id: Number(radio('owner') || receipt.user_id),
        store_name: $('rvStore').value.trim() || null,
        branch: $('rvBranch').value.trim() || null,
        purchased_at: date ? new Date(`${date}T${time}:00+09:00`).toISOString() : receipt.purchased_at,
        pre_tax_yen: amount > 0 ? (isTotal ? tax.estimatePreTax(amount, taxRate) : amount) : null,
        tax_rate: taxRate,
        is_estimated: isTotal && amount > 0,
    };
}

const BANNER_ICON = {
    info: '<svg class="i" width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
    ok: '<svg class="i" width="22" height="22" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>',
    short: '<svg class="i" width="22" height="22" viewBox="0 0 24 24"><path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17h.01"/></svg>',
};

function setBanner(tone, title, text) {
    $('rvBanner').className = `rv-banner ${tone === 'info' ? '' : tone}`.trim();
    $('rvBannerIcon').innerHTML = BANNER_ICON[tone];
    $('rvBannerTitle').textContent = title;
    $('rvBannerText').textContent = text;
}

function renderBanner(current) {
    const others = [...S.receipts.values()].filter((r) => r.id !== current.id && Number(r.user_id) === Number(current.user_id));
    const groups = tax.groupTotals([...others, current]);
    const total = groups.get(tax.groupKey(current)) || 0;
    const sameGroup = others.filter((r) => tax.groupKey(r) === tax.groupKey(current) && r.pre_tax_yen != null);
    const short = Math.max(0, minimum() - total);

    if (current.pre_tax_yen == null || !current.store_name) {
        setBanner('info', 'Add the store and amount', `Spend ${yen(minimum())} or more before tax at one store in one day (Japan time) to qualify.`);
        return;
    }
    const leaveBy = tax.leaveBy(current, Number(S.taxRules?.days_to_export) || 90);
    const withOthers = sameGroup.length
        ? `With your ${sameGroup.map((r) => japanTime(r.purchased_at)).join(', ')} receipt${sameGroup.length > 1 ? 's' : ''} here today, you’ve spent ${yen(total)} before tax at this store.`
        : `${yen(total)} before tax at this store today.`;
    if (short === 0) {
        setBanner('ok', 'Eligible for tax refund', `${withOthers} ${yen(minimum())} is the minimum. Take the goods out of Japan by ${fmtDate(leaveBy)} ${leaveBy.slice(0, 4)}.`);
    } else {
        setBanner('short', `${yen(short)} more to qualify`, `${withOthers} Spend ${yen(minimum())} before tax at this store on the same day to get the tax back.`);
    }
}

function renderLive() {
    const current = draft();
    renderBanner(current);
    const estimated = current.is_estimated;
    $('rvEstimate').classList.toggle('hidden', !estimated);
    if (estimated) $('rvEstimate').textContent = `About ${yen(current.pre_tax_yen)} before tax (total ÷ ${current.tax_rate === 8 ? '1.08' : '1.10'}).`;
    const refund = current.pre_tax_yen != null ? tax.taxOf(current) : 0;
    $('rvExpected').innerHTML = `${yen(refund)} <span>≈ ${rm((refund * rate()) / 100)}</span>`;
}

function renderPhoto(receipt) {
    const local = localPhotoUrl(receipt.id, 1);
    const remote = (receipt.photo_parts || []).includes(1) ? apiUrl(`/receipts/${receipt.id}/photos/1`) : null;
    const src = local || remote;
    $('rvThumb').innerHTML = src ? `<img src="${esc(src)}" alt="Receipt photo">` : '<span class="rc-thumb blank" aria-hidden="true"><span></span><span></span><span></span><span></span></span>';
    const hasPart2 = Boolean(localPhotoUrl(receipt.id, 2)) || (receipt.photo_parts || []).includes(2);
    $('rvPhotoTitle').textContent = src ? (hasPart2 ? 'Photo saved, 2 parts' : 'Photo saved') : 'No photo yet';
    $('rvPhotoSub').textContent = src ? 'Kept until the refund arrives' : 'Snap it so you have it at customs';
    $('rvRetake').textContent = src ? 'Retake' : 'Snap photo';
    $('rvPart2').hidden = !src || hasPart2;
}

function renderOwners(receipt) {
    const show = isOwner() && S.shoppers.length > 1;
    $('rvOwnerSet').classList.toggle('hidden', !show);
    if (!show) return;
    $('rvOwners').innerHTML = S.shoppers.map((p) => `<label class="pill"><input type="radio" name="owner" value="${p.user_id}" ${Number(p.user_id) === Number(receipt.user_id) ? 'checked' : ''}><span>${esc(p.is_me ? `${p.name} (you)` : p.name)}</span></label>`).join('');
}

function ownerItems() {
    const ownerId = Number(radio('owner') || S.receipts.get(editingId)?.user_id);
    return [...S.items.values()].filter((i) => Number(i.user_id) === ownerId);
}

function renderLinks() {
    $('rvLinks').innerHTML = linked
        .map((id) => S.items.get(id))
        .filter(Boolean)
        .map((item) => `<button type="button" class="rv-link" data-unlink="${esc(item.id)}" aria-label="Unlink ${esc(item.name)}"><svg class="i" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>${esc(item.name)}</button>`)
        .join('');
    const items = ownerItems();
    $('rvPickList').innerHTML = items.length
        ? items.map((item) => `<label class="check-row"><input type="checkbox" value="${esc(item.id)}" ${linked.includes(item.id) ? 'checked' : ''}><span><strong>${esc(item.name)}</strong><small>${esc([item.store, item.for_whom ? `For ${item.for_whom}` : '', item.is_bought ? 'Bought' : ''].filter(Boolean).join(' · ') || 'On the list')}</small></span></label>`).join('')
        : '<p class="muted" style="margin:8px 0 0">Nothing on this shopping list yet.</p>';
}

/* ---------- Add the lines read off the receipt to the shopping list ---------- */

const lineName = (line) => String(line.name_en || line.name_ja || '').trim().slice(0, 160);
const sameName = (a, b) => a.trim().toLowerCase() === b.trim().toLowerCase();

/** Lines from the reading, each marked when a linked item already has its name. */
function addLines() {
    const reading = S.receipts.get(editingId)?.reading;
    const names = linked.map((id) => S.items.get(id)?.name).filter(Boolean);
    return (reading?.items || [])
        .map((line, index) => ({ line, index, name: lineName(line), onList: names.some((n) => sameName(n, lineName(line))) }))
        .filter((row) => row.name);
}

const ownerName = () => S.shoppers.find((p) => Number(p.user_id) === Number(radio('owner') || S.receipts.get(editingId)?.user_id))?.name || '';

/** Lines that will be added on save. */
function linesToAdd() {
    if (!$('rvAddOn').checked) return [];
    return addLines().filter((row) => !row.onList && !addSkipped.has(row.index));
}

function renderAdd() {
    const rows = addLines();
    const fresh = rows.filter((row) => !row.onList);
    $('rvAdd').hidden = rows.length === 0;
    if (!rows.length) return;

    if (!addForTyped) $('rvAddFor').value = ownerName();
    $('rvAddForList').innerHTML = forWhoNames().map((v) => `<option value="${esc(v)}"></option>`).join('');
    $('rvAddBody').hidden = !$('rvAddOn').checked || fresh.length === 0;

    const picked = linesToAdd().length;
    const who = $('rvAddFor').value.trim();
    $('rvAddNote').textContent = !fresh.length
        ? 'Everything on this receipt is on the list'
        : $('rvAddOn').checked
            ? `${picked} item${picked === 1 ? '' : 's'} go on ${who ? `the list for ${who}` : 'the list'}, ticked as bought`
            : 'Off: only the receipt is saved';
    $('rvAddCount').textContent = `${picked} of ${fresh.length} selected`;
    const allOn = fresh.every((row) => !addSkipped.has(row.index));
    $('rvAddAll').textContent = allOn ? 'Clear all' : 'Select all';
    $('rvAddAll').hidden = fresh.length < 2;

    $('rvAddList').innerHTML = rows.map(({ line, index, name, onList }) => {
        const japanese = line.name_en && line.name_ja ? `<small lang="ja">${esc(line.name_ja)}</small>` : '';
        const qty = line.qty > 1 ? `<span class="rv-ai-qty">×${line.qty}</span>` : '';
        const price = Number(line.price_yen) > 0 ? `<span>${yen(line.price_yen)}</span>` : '';
        const side = onList ? '<span class="rv-add-done">On the list</span>' : `${qty}${price}`;
        return `<li><label class="rv-add-row${onList ? ' done' : ''}">
            <input type="checkbox" data-add-line="${index}" ${onList || !addSkipped.has(index) ? 'checked' : ''} ${onList ? 'disabled' : ''}>
            <span class="rv-ai-name"><strong>${esc(name)}</strong>${japanese}</span>
            <span class="rv-ai-price tnum">${side}</span>
        </label></li>`;
    }).join('');
}

/**
 * Put the chosen lines on the owner's list now (works offline) and link them.
 * @return {object[]} what the server needs to create the same items
 */
function addChosenLines(ownerId, storeName) {
    const forWhom = $('rvAddFor').value.trim();
    let sortOrder = Math.max(0, ...[...S.items.values()].map((i) => Number(i.sort_order) || 0));
    return linesToAdd().map(({ line, name }) => {
        const price = Math.round(Number(line.price_yen));
        const item = {
            id: uuid(),
            name,
            details: line.name_en && line.name_ja ? String(line.name_ja).slice(0, 160) : null,
            qty: Math.min(999, Math.max(1, Math.round(Number(line.qty)) || 1)),
            price_yen: price > 0 ? Math.min(10_000_000, price) : null,
            reduced_rate: Boolean(line.reduced_rate),
        };
        sortOrder += 1;
        S.items.set(item.id, {
            id: item.id,
            user_id: ownerId,
            name: item.name,
            details: item.details,
            store: storeName,
            for_whom: forWhom || null,
            category: item.reduced_rate ? 'snacks_food' : 'other',
            qty: item.qty,
            estimated_yen: item.price_yen ?? 0,
            actual_yen: item.price_yen,
            is_bought: true,
            paid_at: new Date().toISOString(),
            sort_order: sortOrder,
        });
        linked.push(item.id);
        return item;
    });
}

function fillStoreSuggestions() {
    const names = new Set([...S.receipts.values()].map((r) => r.store_name).concat([...S.items.values()].map((i) => i.store)).filter(Boolean));
    $('rvStoreList').innerHTML = [...names].sort((a, b) => a.localeCompare(b)).map((name) => `<option value="${esc(name)}"></option>`).join('');
}

/** Phase 2: highlight fields an automatic reader was unsure about. */
export function markLowConfidence(fieldNames = []) {
    form().querySelectorAll('[data-field]').forEach((field) => {
        const low = fieldNames.includes(field.dataset.field);
        field.classList.toggle('low', low);
        field.querySelector('.low-hint')?.remove();
        if (low) field.insertAdjacentHTML('beforeend', '<span class="low-hint"><svg class="i" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>Check this: hard to read</span>');
    });
}

export function openReview(receiptId) {
    const receipt = S.receipts.get(receiptId);
    if (!receipt || !canManageShoppingOf(receipt.user_id)) return;
    editingId = receiptId;
    linked = [...(receipt.shopping_item_ids || [])];
    addSkipped = new Set();
    addForTyped = false;
    $('rvAddOn').checked = true;

    form().reset();
    $('rvStore').value = receipt.store_name || '';
    $('rvBranch').value = receipt.branch || '';
    $('rvDate').value = japanDate(receipt.purchased_at);
    $('rvTime').value = japanTime(receipt.purchased_at);
    $('rvAmount').value = receipt.pre_tax_yen ?? '';
    form().querySelector(`input[name="tax_rate"][value="${receipt.tax_rate === 8 ? 8 : 10}"]`).checked = true;
    $('rvTitle').textContent = receipt.pre_tax_yen == null ? 'Check receipt' : 'Edit receipt';
    delete $('rvDelete').dataset.armed;
    $('rvDelete').textContent = 'Delete this receipt';
    $('rvPick').open = false;
    markLowConfidence([]);
    touched = new Set();
    aiCard('');
    renderOwners(receipt);
    renderPhoto(receipt);
    renderLinks();
    fillStoreSuggestions();
    renderLive();
    renderAdd();
    $('rvDlg').showModal();
    const willRead = S.taxRules?.auto_read && isBlank(receipt);
    if (receipt.reading?.items?.length || receipt.reading?.summary) {
        // Read before: show the summary again (no new reading, no cost).
        doneCard({ ...receipt.reading, fields: { store_name: receipt.store_name, branch: receipt.branch } });
    } else if (willRead && !hasServerPhoto(receipt)) {
        if (navigator.onLine) aiCard('busy', { title: 'Uploading the photo…', sub: 'Then Claude reads it and translates it to English' });
        else aiCard('warn', { title: 'Reading starts when you’re back online', sub: 'The photo is saved on this phone.' });
    }
    autoRead(receiptId);
    // Typing straight away is only useful when nothing will be read for them.
    if (!receipt.store_name && !willRead) setTimeout(() => $('rvStore').focus(), 50);
}

function save() {
    const receipt = S.receipts.get(editingId);
    const next = draft();
    if (!next.store_name) {
        toast('Add the store name.');
        $('rvStore').focus();
        return;
    }
    if (next.pre_tax_yen == null) {
        toast('Add the amount in yen.');
        $('rvAmount').focus();
        return;
    }
    const before = { ...receipt };
    const newItems = addChosenLines(Number(next.user_id), next.store_name);
    const forWhom = $('rvAddFor').value.trim();
    const body = {
        store_name: next.store_name,
        branch: next.branch,
        purchased_at: next.purchased_at,
        pre_tax_yen: next.pre_tax_yen,
        tax_rate: next.tax_rate,
        is_estimated: next.is_estimated,
        shopping_item_ids: linked,
    };
    if (Number(next.user_id) !== Number(receipt.user_id)) body.user_id = next.user_id;
    if (newItems.length) {
        body.new_items = newItems;
        body.new_items_for = forWhom || null;
    }

    const saved = { ...next, stage: receipt.stage === 'captured' ? 'checked' : receipt.stage, shopping_item_ids: [...linked] };
    S.receipts.set(receipt.id, saved);
    markItemsBought(saved);
    $('rvDlg').close();
    onChange();
    write({
        key: `receipt:${receipt.id}`,
        method: 'PATCH',
        path: `/receipts/${receipt.id}`,
        body,
        onSaved: (data) => { if (data?.data) S.receipts.set(receipt.id, data.data); onChange(); },
        revert: () => {
            S.receipts.set(receipt.id, before);
            newItems.forEach((item) => S.items.delete(item.id));
            onChange();
        },
    });
    toast(newItems.length
        ? `Receipt saved. ${newItems.length} item${newItems.length === 1 ? '' : 's'} added to the list${forWhom ? ` for ${forWhom}` : ''}.`
        : 'Receipt saved.');
}

/**
 * Split a total so the shares add up to it exactly: by what each line already shows
 * as paid (when all have a price), else by estimate, else evenly.
 * Mirrors App\Services\ReceiptPaidSync::split(); keep the two in step.
 */
export function splitTotal(total, lines) {
    if (!lines.length) return [];
    const paid = lines.map((l) => Math.max(0, Number(l.paid) || 0));
    const est = lines.map((l) => Math.max(0, Number(l.estimated) || 0));
    let weights = lines.map(() => 1);
    if (!paid.includes(0)) weights = paid;
    else if (est.reduce((a, b) => a + b, 0) > 0) weights = est;
    const sum = weights.reduce((a, b) => a + b, 0);
    let running = 0;
    let given = 0;
    return weights.map((w) => {
        running += w;
        const upTo = Math.round((total * running) / sum);
        const share = upTo - given;
        given = upTo;
        return share;
    });
}

/** Mirror the server: linked items are bought and share the receipt total as Paid ¥. */
function markItemsBought(receipt) {
    const items = linked.map((id) => S.items.get(id)).filter(Boolean)
        .sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0) || String(a.id).localeCompare(String(b.id)));
    const total = receipt.pre_tax_yen == null ? null : Number(receipt.pre_tax_yen) + tax.taxOf(receipt);
    const shares = total == null ? [] : splitTotal(total, items.map((i) => ({ paid: i.actual_yen, estimated: i.estimated_yen })));
    items.forEach((item, index) => {
        item.is_bought = true;
        if (total == null) return;
        item.actual_yen = shares[index];
        if (!item.paid_at) item.paid_at = new Date().toISOString();
    });
}

function remove() {
    const receipt = S.receipts.get(editingId);
    if (!receipt) return;
    const button = $('rvDelete');
    if (!button.dataset.armed) {
        button.dataset.armed = '1';
        button.textContent = 'Tap again to delete';
        return;
    }
    S.receipts.delete(receipt.id);
    $('rvDlg').close();
    onChange();
    write({
        key: `receipt:${receipt.id}`,
        method: 'DELETE',
        path: `/receipts/${receipt.id}`,
        revert: () => { S.receipts.set(receipt.id, receipt); onChange(); },
    });
    toast('Receipt deleted.');
}

function retake(part) {
    const id = editingId;
    $('rvDlg').close();
    warmScanner();
    openCamera(async ([blob]) => {
        if (blob) {
            const [scan] = await scanPhotos([blob]);
            await queuePhoto(id, part, scan);
        }
        onChange();
        openReview(id);
    });
}

/* ---------- Automatic reading ---------- */

const READ_ICON = {
    busy: '<svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9"/></svg>',
    done: '<svg class="i" width="18" height="18" viewBox="0 0 24 24"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>',
    warn: '<svg class="i" width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
};

/** Form fields the person has typed in since opening; a reading never overwrites them. */
let touched = new Set();
const reading = new Set();

const SHOW_ITEMS = 4;
let shownReading = null;
let showAllItems = false;

/** The receipt summary card: busy (reading), done (summary and items) or warn (a short message). */
function aiCard(state, { title = '', sub = '', result = null } = {}) {
    const card = $('rvAi');
    card.hidden = !state;
    if (!state) return;
    card.className = `rv-ai ${state}`;
    $('rvAiIcon').innerHTML = READ_ICON[state] || '';
    $('rvAiTitle').textContent = title;
    $('rvAiSub').textContent = sub;
    $('rvAiSub').hidden = !sub;
    $('rvAiSkel').hidden = state !== 'busy';
    shownReading = result;
    showAllItems = false;
    $('rvAiSummary').textContent = result?.summary || '';
    $('rvAiSummary').hidden = !result?.summary;
    renderAiItems();
}

function renderAiItems() {
    const items = shownReading?.items || [];
    const visible = showAllItems ? items : items.slice(0, SHOW_ITEMS);
    $('rvAiItems').innerHTML = visible.map((item) => {
        const english = item.name_en || item.name_ja;
        const japanese = item.name_en && item.name_ja ? `<small lang="ja">${esc(item.name_ja)}</small>` : '';
        const qty = item.qty > 1 ? `<span class="rv-ai-qty">×${item.qty}</span>` : '';
        const reduced = item.reduced_rate ? '<span class="rv-ai-tag" title="8% food and drink">8%</span>' : '';
        return `<li><div class="rv-ai-name"><strong>${esc(english)}</strong>${japanese}</div><div class="rv-ai-price tnum">${reduced}${qty}<span>${yen(item.price_yen)}</span></div></li>`;
    }).join('');
    $('rvAiItems').hidden = !items.length;
    const more = $('rvAiMore');
    more.hidden = items.length <= SHOW_ITEMS;
    more.textContent = showAllItems ? 'Show fewer' : `Show all ${items.length} items`;
    more.setAttribute('aria-expanded', String(showAllItems));
}

const itemCount = (n) => `${n} item${n === 1 ? '' : 's'}`;

/** Title and subtitle for a finished reading. */
function doneCard(result, flagged = 0) {
    const fields = result?.fields || {};
    const store = [fields.store_name || $('rvStore').value, fields.branch || $('rvBranch').value].filter(Boolean).join(' · ');
    const parts = [result?.items?.length ? `${itemCount(result.items.length)}, translated from Japanese` : 'Read from the photo'];
    if (flagged) parts.push(`check ${flagged === 1 ? 'the highlighted field' : `${flagged} highlighted fields`}`);
    aiCard('done', { title: store || 'Receipt summary', sub: parts.join(' · '), result });
}

const hasServerPhoto = (receipt) => (receipt?.photo_parts || []).includes(1);
const isBlank = (receipt) => receipt && !receipt.store_name && receipt.pre_tax_yen == null;

/** Fill what the reader found into fields the person hasn't typed in. */
function applyReading({ fields = {}, confidence = {} }) {
    const set = (name, input, value) => {
        if (value == null || value === '' || touched.has(name)) return false;
        input.value = value;
        return true;
    };
    let filled = 0;
    filled += set('store_name', $('rvStore'), fields.store_name);
    filled += set('branch', $('rvBranch'), fields.branch);
    filled += set('date', $('rvDate'), fields.date);
    filled += set('time', $('rvTime'), fields.time);
    if (fields.amount_yen && !touched.has('pre_tax_yen')) {
        $('rvAmount').value = fields.amount_yen;
        const kind = form().querySelector(`input[name="amount_kind"][value="${fields.amount_kind === 'total' ? 'total' : 'pre'}"]`);
        if (kind) kind.checked = true;
        filled++;
    }
    if (fields.tax_rate && !touched.has('tax_rate')) {
        const rateInput = form().querySelector(`input[name="tax_rate"][value="${fields.tax_rate}"]`);
        if (rateInput) rateInput.checked = true;
    }

    const min = Number(S.taxRules?.reader_min_confidence) || 0.75;
    const unsure = Object.entries({ store_name: 'store_name', branch: 'branch', date: 'date', time: 'time', amount_yen: 'pre_tax_yen', tax_rate: 'tax_rate' })
        .filter(([key, field]) => fields[key] != null && (confidence[key] ?? 0) < min && !touched.has(field))
        .map(([, field]) => field);
    const missing = ['store_name', 'amount_yen'].filter((key) => fields[key] == null).map((key) => (key === 'amount_yen' ? 'pre_tax_yen' : key));
    const flagged = [...unsure, ...missing.filter((f) => !touched.has(f))];
    markLowConfidence(flagged);
    renderLive();
    return { filled, flagged: flagged.length };
}

/**
 * Ask the server to read the receipt photo, then fill the open form.
 * Only for new receipts (no store or amount saved yet) with an uploaded photo.
 */
async function autoRead(receiptId) {
    const receipt = S.receipts.get(receiptId);
    if (!S.taxRules?.auto_read || !hasServerPhoto(receipt) || !isBlank(receipt) || reading.has(receiptId)) return;
    if (!navigator.onLine) {
        aiCard('warn', { title: 'Reading starts when you’re back online', sub: 'The photo is saved on this phone.' });
        return;
    }
    reading.add(receiptId);
    aiCard('busy', { title: 'Reading the receipt…', sub: 'Translating it to English and filling in the form' });
    try {
        const res = await api('POST', `/receipts/${receiptId}/read`);
        const result = res?.data || {};
        const stored = S.receipts.get(receiptId);
        if (stored && (result.items?.length || result.summary)) {
            stored.reading = { summary: result.summary || '', items: result.items || [], model: result.model || null };
        }
        if (editingId !== receiptId || !$('rvDlg').open) return;
        const { filled, flagged } = applyReading(result);
        if (filled || result.items?.length) {
            doneCard(result, flagged);
            renderAdd();
        } else {
            aiCard('warn', { title: 'Couldn’t read this receipt clearly', sub: 'Type the store and amount below, or retake the photo in better light.' });
        }
    } catch (error) {
        if (editingId !== receiptId) return;
        aiCard('warn', error?.status === 0
            ? { title: 'Reading starts when you’re back online', sub: 'The photo is saved on this phone.' }
            : { title: 'Couldn’t read it this time', sub: 'Type the details in below.' });
    } finally {
        reading.delete(receiptId);
    }
}

/** After a photo uploads: read it if its Check receipt is the one on screen. */
export function readIfOpen(receiptId) {
    if (receiptId && receiptId === editingId && $('rvDlg').open) autoRead(receiptId);
}

/**
 * @param {{ onChange: () => void }} hooks re-render the receipts list after a change
 */
export function bindReview(hooks) {
    onChange = hooks.onChange;
    const FIELD_OF = { amount: 'pre_tax_yen', amount_kind: 'pre_tax_yen' };
    const noteTyped = (e) => {
        if (e.isTrusted && e.target.name) touched.add(FIELD_OF[e.target.name] || e.target.name);
    };
    form().addEventListener('input', noteTyped);
    form().addEventListener('change', noteTyped);
    form().addEventListener('input', renderLive);
    form().addEventListener('change', (e) => {
        if (e.target.name === 'owner') {
            linked = [];
            renderLinks();
            renderAdd();
        }
        if (e.target.closest('#rvPickList')) {
            linked = [...$('rvPickList').querySelectorAll('input:checked')].map((input) => input.value);
            renderLinks();
            renderAdd();
            $('rvPick').open = true;
        }
        const line = e.target.closest('[data-add-line]');
        if (line) {
            const index = Number(line.dataset.addLine);
            if (line.checked) addSkipped.delete(index);
            else addSkipped.add(index);
            renderAdd();
        }
        if (e.target.id === 'rvAddOn') renderAdd();
        renderLive();
    });
    $('rvLinks').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-unlink]');
        if (!chip) return;
        linked = linked.filter((id) => id !== chip.dataset.unlink);
        renderLinks();
        renderAdd();
    });
    $('rvAddFor').addEventListener('input', () => {
        addForTyped = true;
        renderAdd();
    });
    $('rvAddAll').addEventListener('click', () => {
        const fresh = addLines().filter((row) => !row.onList);
        const allOn = fresh.every((row) => !addSkipped.has(row.index));
        addSkipped = allOn ? new Set(fresh.map((row) => row.index)) : new Set();
        renderAdd();
    });
    form().addEventListener('submit', (e) => {
        e.preventDefault();
        save();
    });
    $('rvDelete').addEventListener('click', remove);
    $('rvRetake').addEventListener('click', () => retake(1));
    $('rvPart2').addEventListener('click', () => retake(2));
    $('rvAiMore').addEventListener('click', () => {
        showAllItems = !showAllItems;
        renderAiItems();
    });
}
