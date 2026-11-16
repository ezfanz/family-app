/*
 * Check receipt: store, date and time, one amount (before tax or total paid),
 * the tax rate, whose receipt it is and optional shopping links, with a live
 * eligibility banner. Works offline: saves go through the write queue.
 *
 * Phase 2 (automatic reading) can call markLowConfidence() with the fields a
 * ReceiptReader was unsure about; they get the amber "Check this" state.
 */

import { S, isOwner, canManageShoppingOf } from './store.js';
import { $, esc, yen, rm, fmtDate, toast } from './util.js';
import { apiUrl } from './api.js';
import { write } from './writer.js';
import { rate } from './shopping.js';
import * as tax from './taxcalc.js';
import { openCamera } from './camera.js';
import { queuePhoto, localPhotoUrl } from './photo-queue.js';

let editingId = null;
let linked = [];
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
    renderOwners(receipt);
    renderPhoto(receipt);
    renderLinks();
    fillStoreSuggestions();
    renderLive();
    $('rvDlg').showModal();
    if (!receipt.store_name) setTimeout(() => $('rvStore').focus(), 50);
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
        revert: () => { S.receipts.set(receipt.id, before); onChange(); },
    });
    toast('Receipt saved.');
}

/** Mirror the server: linked items are bought; one linked item gets the receipt total as Paid ¥. */
function markItemsBought(receipt) {
    const items = linked.map((id) => S.items.get(id)).filter(Boolean);
    for (const item of items) {
        item.is_bought = true;
        if (items.length === 1 && item.actual_yen == null) item.actual_yen = receipt.pre_tax_yen + tax.taxOf(receipt);
    }
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
    openCamera(async ([blob]) => {
        if (blob) await queuePhoto(id, part, blob);
        onChange();
        openReview(id);
    });
}

/**
 * @param {{ onChange: () => void }} hooks re-render the receipts list after a change
 */
export function bindReview(hooks) {
    onChange = hooks.onChange;
    form().addEventListener('input', renderLive);
    form().addEventListener('change', (e) => {
        if (e.target.name === 'owner') {
            linked = [];
            renderLinks();
        }
        if (e.target.closest('#rvPickList')) {
            linked = [...$('rvPickList').querySelectorAll('input:checked')].map((input) => input.value);
            renderLinks();
            $('rvPick').open = true;
        }
        renderLive();
    });
    $('rvLinks').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-unlink]');
        if (!chip) return;
        linked = linked.filter((id) => id !== chip.dataset.unlink);
        renderLinks();
    });
    form().addEventListener('submit', (e) => {
        e.preventDefault();
        save();
    });
    $('rvDelete').addEventListener('click', remove);
    $('rvRetake').addEventListener('click', () => retake(1));
    $('rvPart2').addEventListener('click', () => retake(2));
}
