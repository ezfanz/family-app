/*
 * Shopping, after the E-Itinerary design: everyone keeps their own list and budget.
 * Balance = budget − spent, where spent is what was actually paid (Paid ¥ × rate).
 * The trip owner (admin) also gets "Everyone" totals and can open anyone's list.
 */

import { S, UI, canEdit, isOwner, canManageShoppingOf, shoppingList } from './store.js';
import { $, esc, yen, rm, debounce, debounceKeyed } from './util.js';
import { write } from './writer.js';

const AVATAR_COLOURS = ['#2A3B8F', '#6A3E9A', '#00776F', '#1F5591', '#B7791F', '#C2255C', '#4A8410', '#8A5A00'];
const ROLE_LABEL = { owner: 'Owner', editor: 'Editor', viewer: 'Viewer' };

let openItemForm = () => {};
export function setItemFormOpener(fn) {
    openItemForm = fn;
}

const rate = () => Number(S.trip.exchange_rate_rm_per_100_yen) || 0;
const toRm = (yenValue) => (yenValue * rate()) / 100;

export const initials = (name) => String(name || '?').trim().slice(0, 2).toUpperCase();

function colourOf(userId) {
    const index = S.shoppers.findIndex((p) => p.user_id === userId);
    return AVATAR_COLOURS[(index < 0 ? 0 : index) % AVATAR_COLOURS.length];
}

function displayName(person) {
    return person.is_me ? `${person.name} (you)` : person.name;
}

/** Which list is showing: your own by default; the owner can switch to "all" or anyone. */
export function selectedShopper() {
    const known = (id) => S.shoppers.some((p) => p.user_id === id);
    if (!isOwner()) {
        UI.shopSel = S.me;
    } else if (UI.shopSel !== 'all' && !known(UI.shopSel)) {
        UI.shopSel = known(S.me) ? S.me : 'all';
    }
    return UI.shopSel;
}

/** Opening the Shopping tab always starts on your own list. */
export function resetShopperToMe() {
    UI.shopSel = S.me;
}

function itemsOf(userId) {
    return shoppingList().filter((i) => Number(i.user_id) === Number(userId));
}

/** Money for one person or the whole group, as in the design's money() helper. */
export function money(budget, items) {
    let paidYen = 0;
    let leftYen = 0;
    let bought = 0;
    for (const item of items) {
        paidYen += Number(item.actual_yen) || 0;
        if (item.is_bought) bought += 1;
        else leftYen += Number(item.estimated_yen) || 0;
    }
    const spent = toRm(paidYen);
    const balance = budget - spent;
    const pct = budget > 0 ? (spent / budget) * 100 : (spent > 0 ? 100 : 0);
    const barColour = balance < 0 ? 'var(--now)' : (pct >= 85 ? '#B7791F' : 'var(--done)');
    return {
        budget, spent, balance, pct, bought, count: items.length, left: toRm(leftYen),
        barColour,
        usedText: balance < 0 ? `Over budget by ${rm(-balance)}` : `${Math.round(pct)}% of budget used`,
    };
}

function totalsHtml(m) {
    return `<div class="cells">
            <div><div class="muted">Budget</div><div class="v tnum">${rm(m.budget)}</div></div>
            <div><div class="muted">Spent</div><div class="v tnum">${rm(m.spent)}</div></div>
            <div><div class="muted">Balance</div><div class="v tnum ${m.balance < 0 ? 'neg' : 'pos'}">${rm(m.balance)}</div></div>
        </div>
        <div class="note">
            <div class="budget-bar" aria-hidden="true"><div style="width:${Math.min(100, m.pct).toFixed(1)}%;background:${m.barColour}"></div></div>
            <div class="bar-caption tnum"><span>${esc(m.usedText)}</span><span>Budget − spent = balance</span></div>
        </div>`;
}

function avatar(person, size = '') {
    return `<span class="avatar ${size}" aria-hidden="true" style="background:${colourOf(person.user_id)};color:#FFFFFF">${esc(initials(person.name))}</span>`;
}

function renderChips(sel) {
    const chips = [{ id: 'all', label: 'Everyone', ini: 'All' }]
        .concat(S.shoppers.map((p) => ({ id: p.user_id, label: displayName(p), ini: initials(p.name), colour: colourOf(p.user_id) })));
    $('shopChips').innerHTML = chips.map((c) => {
        const on = c.id === sel;
        const dot = c.id === 'all'
            ? `<span class="chip-ava all" aria-hidden="true">All</span>`
            : `<span class="chip-ava" aria-hidden="true" style="background:${c.colour}">${esc(c.ini)}</span>`;
        return `<button type="button" class="chip" data-shopper="${c.id}" aria-pressed="${on}">${dot}${esc(c.label)}</button>`;
    }).join('');
}

function renderAll() {
    let budget = 0;
    S.shoppers.forEach((p) => { budget += Number(p.budget_rm) || 0; });
    $('allTotals').innerHTML = totalsHtml(money(budget, shoppingList()));

    $('shopPeople').innerHTML = S.shoppers.map((p) => {
        const m = money(Number(p.budget_rm) || 0, itemsOf(p.user_id));
        return `<button type="button" class="person-card" data-shopper="${p.user_id}" aria-label="${esc(`${p.name}: spent ${rm(m.spent)} of ${rm(m.budget)}. Open list.`)}">
            ${avatar(p)}
            <span class="pc-main">
                <span class="pc-name">${esc(displayName(p))}<span class="role-pill">${ROLE_LABEL[p.role] || p.role}</span></span>
                <span class="pc-line tnum">Spent ${rm(m.spent)} of ${rm(m.budget)}</span>
                <span class="budget-bar small" aria-hidden="true"><span style="width:${Math.min(100, m.pct).toFixed(1)}%;background:${m.barColour}"></span></span>
            </span>
            <span class="pc-bal"><span class="tnum ${m.balance < 0 ? 'neg' : 'pos'}">${rm(Math.abs(m.balance))}</span><span>${m.balance < 0 ? 'over' : 'left'}</span></span>
        </button>`;
    }).join('');
}

function metaText(item) {
    const parts = [item.store, item.details].filter(Boolean);
    parts.push(`×${item.qty}`);
    const est = Number(item.estimated_yen) || 0;
    parts.push(est ? `est ${yen(est)} ≈ ${rm(toRm(est), 0)}` : 'no estimate');
    if (item.for_whom) parts.push(item.for_whom);
    return parts.join(' · ');
}

function renderPersonList(person) {
    const editable = canManageShoppingOf(person.user_id);
    const items = itemsOf(person.user_id);
    $('shopList').innerHTML = items.length ? items.map((item) => `
        <li class="${item.is_bought ? 'bought' : ''}" data-id="${item.id}"><div class="shop-row">
            <label class="check"><input type="checkbox" data-act="buy" ${item.is_bought ? 'checked' : ''} ${editable ? '' : 'disabled'} aria-label="Bought ${esc(item.name)}"></label>
            <button type="button" class="item-main" data-act="edit-item"${editable ? '' : ' aria-disabled="true" tabindex="-1"'}>
                <span class="item-name">${esc(item.name)}</span>
                <span class="item-meta tnum">${esc(metaText(item))}</span>
            </button>
            <div class="paid"><span aria-hidden="true">Paid ¥</span><input class="tnum" inputmode="numeric" pattern="[0-9]*" placeholder="0" data-paid="${item.id}" value="${item.actual_yen ?? ''}" aria-label="Paid in yen for ${esc(item.name)}" ${editable ? '' : 'readonly'}></div>
        </div></li>`).join('')
        : `<li class="empty-list">Nothing on ${person.is_me ? 'your' : `${esc(person.name)}’s`} list yet.</li>`;
}

function renderPersonTotals(person) {
    const m = money(Number(person.budget_rm) || 0, itemsOf(person.user_id));
    $('personTotals').innerHTML = totalsHtml(m);
    const toBuy = m.count - m.bought;
    let leftLine = '';
    if (m.count && toBuy === 0) {
        leftLine = 'Everything on the list is bought.';
    } else if (m.left > 0) {
        leftLine = `Still to buy, about ${rm(m.left)}. Not counted until paid.`;
    } else if (toBuy > 0) {
        leftLine = `${toBuy} item${toBuy === 1 ? '' : 's'} still to buy. Add estimates to see the cost.`;
    }
    $('personLeft').textContent = leftLine;
    $('shopCount').textContent = `${m.count} item${m.count === 1 ? '' : 's'} · ${m.bought} bought`;
}

function renderPerson(person) {
    const editable = canManageShoppingOf(person.user_id);
    const m = money(Number(person.budget_rm) || 0, itemsOf(person.user_id));
    $('personHead').innerHTML = `${avatar(person, 'lg')}
        <div><div class="ph-name">${esc(displayName(person))}</div>
        <div class="muted">${ROLE_LABEL[person.role] || person.role} · ${m.count} item${m.count === 1 ? '' : 's'} · ${m.bought} bought</div></div>`;
    const budgetInput = $('budget');
    if (document.activeElement !== budgetInput) budgetInput.value = Number(person.budget_rm) || '';
    budgetInput.readOnly = !editable;
    $('addItem').classList.toggle('hidden', !editable);
    renderPersonTotals(person);
    renderPersonList(person);
}

export function renderShop() {
    const sel = selectedShopper();
    const owner = isOwner();
    $('shopAdminNote').classList.toggle('hidden', !owner);
    $('shopChips').classList.toggle('hidden', !owner);
    if (owner) renderChips(sel);

    document.querySelectorAll('[data-rate]').forEach((input) => {
        if (document.activeElement !== input) input.value = rate();
        input.readOnly = !canEdit();
    });

    const person = S.shoppers.find((p) => p.user_id === sel);
    $('shopAll').classList.toggle('hidden', sel !== 'all');
    $('shopPerson').classList.toggle('hidden', !person);
    if (sel === 'all') renderAll();
    else if (person) renderPerson(person);
}

/** Refresh numbers without rebuilding inputs, so typing is not interrupted. */
function renderNumbersOnly() {
    const sel = selectedShopper();
    if (sel === 'all') {
        renderAll();
        return;
    }
    const person = S.shoppers.find((p) => p.user_id === sel);
    if (person) renderPersonTotals(person);
}

function saveItem(item, changes, revert) {
    write({ key: `item:${item.id}`, method: 'PATCH', path: `/shopping-items/${item.id}`, body: changes, revert });
}

const saveRate = debounce(() => {
    write({
        key: 'trip:settings',
        method: 'PATCH',
        path: `/trips/${S.trip.id}`,
        body: { exchange_rate_rm_per_100_yen: rate() },
    });
}, 600);

const saveBudget = debounceKeyed((userId) => {
    const person = S.shoppers.find((p) => p.user_id === userId);
    if (!person) return;
    write({
        key: `budget:${userId}`,
        method: 'PATCH',
        path: `/trips/${S.trip.id}/budgets/${userId}`,
        body: { budget_rm: Number(person.budget_rm) || 0 },
    });
}, 600);

const savePaid = debounceKeyed((id) => {
    const item = S.items.get(id);
    if (item) saveItem(item, { actual_yen: item.actual_yen, is_bought: item.is_bought });
}, 700);

export function bindShopping() {
    $('v-shopping').addEventListener('click', (e) => {
        const pick = e.target.closest('[data-shopper]');
        if (pick && isOwner()) {
            UI.shopSel = pick.dataset.shopper === 'all' ? 'all' : Number(pick.dataset.shopper);
            renderShop();
            if (pick.classList.contains('person-card')) window.scrollTo({ top: 0 });
            return;
        }
        const edit = e.target.closest('[data-act="edit-item"]');
        if (edit) {
            const item = S.items.get(edit.closest('li').dataset.id);
            if (item && canManageShoppingOf(item.user_id)) openItemForm(item.id);
        }
    });

    $('shopList').addEventListener('change', (e) => {
        if (e.target.dataset.act !== 'buy') return;
        const item = S.items.get(e.target.closest('li').dataset.id);
        if (!item || !canManageShoppingOf(item.user_id)) return;
        const before = item.is_bought;
        item.is_bought = e.target.checked;
        renderShop();
        saveItem(item, { is_bought: item.is_bought }, () => { item.is_bought = before; renderShop(); });
    });

    $('shopList').addEventListener('input', (e) => {
        const id = e.target.dataset.paid;
        if (!id) return;
        const item = S.items.get(id);
        if (!item || !canManageShoppingOf(item.user_id)) return;
        const value = Math.max(0, Math.round(Number(e.target.value.replace(/[^0-9]/g, '')) || 0));
        item.actual_yen = value || null;
        if (value > 0 && !item.is_bought) {
            item.is_bought = true;
            const row = e.target.closest('li');
            row.classList.add('bought');
            row.querySelector('[data-act="buy"]').checked = true;
        }
        renderNumbersOnly(); // keep the list as is so the input keeps focus
        savePaid(id);
    });

    document.querySelectorAll('[data-rate]').forEach((input) => {
        input.addEventListener('input', () => {
            if (!canEdit()) return;
            S.trip.exchange_rate_rm_per_100_yen = Math.max(0, Number(input.value) || 0);
            document.querySelectorAll('[data-rate]').forEach((other) => { if (other !== input) other.value = input.value; });
            renderNumbersOnly();
            saveRate();
        });
    });

    $('budget').addEventListener('input', () => {
        const userId = selectedShopper();
        const person = S.shoppers.find((p) => p.user_id === userId);
        if (!person || !canManageShoppingOf(userId)) return;
        person.budget_rm = Math.max(0, Number($('budget').value) || 0);
        renderNumbersOnly();
        saveBudget(userId);
    });

    $('addItem').addEventListener('click', () => openItemForm(null, selectedShopper()));
}

export function computeTotals() {
    return money(S.shoppers.reduce((sum, p) => sum + (Number(p.budget_rm) || 0), 0), shoppingList());
}
