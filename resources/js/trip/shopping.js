/*
 * Shopping, after the E-Itinerary design: everyone keeps their own list and budget.
 * Balance = budget − spent, where spent is what was actually paid (Paid ¥ × rate).
 * The trip owner (admin) also gets "Everyone" totals and can open anyone's list.
 */

import { S, UI, canEdit, isOwner, canManageShoppingOf, shoppingList } from './store.js';
import { $, esc, yen, rm, debounce, debounceKeyed, toast, confirmAction } from './util.js';
import { api } from './api.js';
import { write } from './writer.js';
import { renderFx, bindFx, liveRate } from './fx.js';
import { renderShopTab } from './receipts.js';

const AVATAR_COLOURS = ['#2A3B8F', '#6A3E9A', '#00776F', '#1F5591', '#B7791F', '#C2255C', '#4A8410', '#8A5A00'];
export const ROLE_LABEL = { owner: 'Owner', editor: 'Editor', viewer: 'Viewer' };

let openItemForm = () => {};
export function setItemFormOpener(fn) {
    openItemForm = fn;
}

/** Shopping follows today's Bank Negara rate unless an owner/editor switched to a fixed rate. */
export const autoRate = () => S.trip?.exchange_rate_auto !== false;

/** RM per ¥100 used for totals: today's rate when automatic (or as a fallback), else the trip's own. */
export const rate = () => (autoRate() && liveRate()) || Number(S.trip.exchange_rate_rm_per_100_yen) || liveRate() || 0;
const toRm = (yenValue) => (yenValue * rate()) / 100;

export const initials = (name) => String(name || '?').trim().slice(0, 2).toUpperCase();

function colourOf(userId) {
    const index = S.shoppers.findIndex((p) => p.user_id === userId);
    return AVATAR_COLOURS[(index < 0 ? 0 : index) % AVATAR_COLOURS.length];
}

export function displayName(person) {
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
    UI.forWho = 'all';
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

export function avatar(person, size = '') {
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

function renderOwnerPick(sel) {
    renderChips(sel);
    const showing = S.shoppers.find((p) => p.user_id === sel);
    $('shopViewing').textContent = sel === 'all' ? 'Everyone' : (showing ? displayName(showing) : 'Your list');
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
    renderResults();
}

/** Google search for an item: store + name + details finds the right product. */
export function googleUrl(item, images = false) {
    const query = [item.store, item.name, item.details].filter(Boolean).join(' ');
    return `https://www.google.com/search?q=${encodeURIComponent(query)}${images ? '&tbm=isch' : ''}`;
}

const SEARCH_ICON = '<svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>';
const BIN_ICON = '<svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/></svg>';

/** Delete an item straight away, with Undo in the toast. */
export function deleteItemWithUndo(item, after = renderShop) {
    S.items.delete(item.id);
    after();
    write({
        key: `item:${item.id}`, method: 'DELETE', path: `/shopping-items/${item.id}`,
        revert: () => { S.items.set(item.id, item); after(); },
    });
    toast(`Deleted “${item.name}”.`, {
        action: 'Undo',
        onAction: async () => {
            S.items.set(item.id, item);
            after();
            try {
                const res = await api('PATCH', `/shopping-items/${item.id}/restore`);
                if (res?.data) S.items.set(res.data.id, res.data);
            } catch {
                toast('Couldn’t bring it back. Try again when you’re online.');
                S.items.delete(item.id);
                after();
            }
        },
    });
}

function metaText(item) {
    const parts = [item.store, item.details].filter(Boolean);
    const paid = Number(item.actual_yen) || 0;
    const est = Number(item.estimated_yen) || 0;
    if (paid > 0) {
        // The line's sum: price each × quantity.
        parts.push(`paid ${yen(paid)} ≈ ${rm(toRm(paid), 2)}`);
    } else {
        parts.push(est ? `est ${yen(est)} ≈ ${rm(toRm(est), 0)}` : 'no estimate');
    }
    return parts.join(' · ');
}

/** Price for one, from the stored total paid. */
const paidEach = (item) => {
    const paid = Number(item.actual_yen) || 0;
    return paid > 0 ? Math.round(paid / (Number(item.qty) || 1)) : '';
};

/** − qty + under the item name; plain "Qty n" when you can't change it. */
function qtyHtml(item, editable) {
    const qty = Number(item.qty) || 1;
    if (!editable) {
        return `<div class="qty-step readonly"><span class="qty-val tnum">Qty ${qty}</span></div>`;
    }
    return `<div class="qty-step" role="group" aria-label="Quantity of ${esc(item.name)}">
        <button type="button" data-act="qty" data-step="-1" aria-label="One less ${esc(item.name)}"${qty <= 1 ? ' disabled' : ''}>−</button>
        <span class="qty-val tnum" aria-live="polite">${qty}</span>
        <button type="button" data-act="qty" data-step="1" aria-label="One more ${esc(item.name)}"${qty >= 999 ? ' disabled' : ''}>+</button>
    </div>`;
}

/* ---------- "For who" groups and filter ---------- */

const UNASSIGNED = '__none__';

/** "For Mak", "for mak " and "Mak" are the same person. */
export function forWhoLabel(value) {
    const text = String(value ?? '').trim().replace(/^for\s+/i, '').replace(/\s+/g, ' ');
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}
const forWhoKey = (value) => forWhoLabel(value).toLowerCase() || UNASSIGNED;

/** Groups in display order: names A–Z, "Not assigned" last. */
function forWhoGroups(items) {
    const groups = new Map();
    for (const item of items) {
        const key = forWhoKey(item.for_whom);
        if (!groups.has(key)) groups.set(key, { key, label: key === UNASSIGNED ? 'Not assigned' : forWhoLabel(item.for_whom), items: [] });
        groups.get(key).items.push(item);
    }
    return [...groups.values()].sort((a, b) => {
        if (a.key === UNASSIGNED) return 1;
        if (b.key === UNASSIGNED) return -1;
        return a.label.localeCompare(b.label);
    });
}

function groupMoney(items) {
    let paid = 0;
    let est = 0;
    for (const item of items) {
        paid += Number(item.actual_yen) || 0;
        est += Number(item.estimated_yen) || 0;
    }
    const parts = [`${items.length} item${items.length === 1 ? '' : 's'}`];
    if (paid > 0) parts.push(`paid ${rm(toRm(paid), 2)}`);
    if (est > 0) parts.push(`est ${rm(toRm(est), 0)}`);
    return parts.join(' · ');
}

function renderForChips(groups) {
    const nav = $('forChips');
    // Only worth showing once at least one item says who it is for.
    const useful = groups.some((g) => g.key !== UNASSIGNED);
    nav.classList.toggle('hidden', !useful);
    if (!useful) {
        UI.forWho = 'all';
        return;
    }
    if (UI.forWho !== 'all' && !groups.some((g) => g.key === UI.forWho)) UI.forWho = 'all';
    const total = groups.reduce((sum, g) => sum + g.items.length, 0);
    const chip = (key, label, count) => `<button type="button" class="chip for-chip${key === UNASSIGNED ? ' none' : ''}" data-for="${esc(key)}" aria-pressed="${UI.forWho === key}">${esc(label)}<span class="chip-count tnum">${count}</span></button>`;
    nav.innerHTML = chip('all', 'All', total) + groups.map((g) => chip(g.key, g.label, g.items.length)).join('');
}

/** Label of the active "for who" filter, to prefill a new item; '' when none. */
export function activeForWhoLabel() {
    if (UI.forWho === 'all' || UI.forWho === UNASSIGNED) return '';
    const match = shoppingList().find((i) => forWhoKey(i.for_whom) === UI.forWho);
    return match ? forWhoLabel(match.for_whom) : '';
}

/** Names already used for "for who" on the list being viewed, as suggestions in the item sheet. */
export function forWhoNames() {
    const sel = selectedShopper();
    const items = sel === 'all' ? itemsOf(S.me) : itemsOf(sel);
    return [...new Set(items.map((i) => forWhoLabel(i.for_whom)).filter(Boolean))].sort((a, b) => a.localeCompare(b));
}

function itemRowHtml(item, editable) {
    return `
        <li class="${item.is_bought ? 'bought' : ''}" data-id="${item.id}"><div class="shop-row">
            <label class="check"><input type="checkbox" data-act="buy" ${item.is_bought ? 'checked' : ''} ${editable ? '' : 'disabled'} aria-label="Bought ${esc(item.name)}"></label>
            <button type="button" class="item-main" data-act="edit-item"${editable ? '' : ' aria-disabled="true" tabindex="-1"'}>
                <span class="item-name">${esc(item.name)}</span>
                <span class="item-meta tnum">${esc(metaText(item))}</span>
            </button>
            ${qtyHtml(item, editable)}
            <a class="search-btn" href="${esc(googleUrl(item))}" target="_blank" rel="noopener" aria-label="Search Google for ${esc(item.name)}" title="Search on Google">${SEARCH_ICON}</a>
            ${editable ? `<button type="button" class="del-btn" data-act="delete-item" aria-label="Delete ${esc(item.name)}" title="Delete">${BIN_ICON}</button>` : ''}
            <div class="paid"><span aria-hidden="true">¥ each</span><input class="tnum" inputmode="numeric" pattern="[0-9]*" placeholder="0" data-paid="${item.id}" value="${paidEach(item)}" aria-label="Price paid for one ${esc(item.name)}, in yen" ${editable ? '' : 'readonly'}></div>
        </div></li>`;
}

/* ---------- Search & filter form ---------- */

const STATUS_LABEL = { to_buy: 'To buy', bought: 'Bought' };

export const filterActive = () => {
    const f = UI.filter;
    return Boolean(f.q || f.forWho || f.store || f.status !== 'all');
};

function matchesFilter(item) {
    const f = UI.filter;
    if (f.status === 'to_buy' && item.is_bought) return false;
    if (f.status === 'bought' && !item.is_bought) return false;
    if (f.forWho && forWhoKey(item.for_whom) !== forWhoKey(f.forWho)) return false;
    if (f.store && !String(item.store || '').toLowerCase().includes(f.store.toLowerCase())) return false;
    if (f.q) {
        const haystack = [item.name, item.store, item.details, item.for_whom, item.notes].join(' ').toLowerCase();
        if (!f.q.toLowerCase().split(/\s+/).every((word) => haystack.includes(word))) return false;
    }
    return true;
}

function filterDescription() {
    const f = UI.filter;
    const parts = [];
    if (f.q) parts.push(`“${f.q}”`);
    if (f.forWho) parts.push(`For ${forWhoLabel(f.forWho)}`);
    if (f.store) parts.push(f.store);
    if (f.status !== 'all') parts.push(STATUS_LABEL[f.status]);
    return parts.join(' · ');
}

/** Items whose names and stores feed the form's suggestions. */
function filterScopeItems(sel = selectedShopper()) {
    return sel === 'all' ? shoppingList() : itemsOf(sel);
}

/** Whose list is picked in the open drawer; applied only on Apply. */
let draftShopper = null;
const drawerOpen = () => $('filterDrawer').open;

function fillFilterSuggestions(sel = selectedShopper()) {
    const items = filterScopeItems(sel);
    const names = [...new Set(items.map((i) => forWhoLabel(i.for_whom)).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    const stores = [...new Set(items.map((i) => String(i.store || '').trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    // Keep a filter that is on even if no item uses that name any more.
    const current = UI.filter.forWho ? forWhoLabel(UI.filter.forWho) : '';
    if (current && !names.some((n) => forWhoKey(n) === forWhoKey(current))) names.unshift(current);
    $('fFor').innerHTML = '<option value="">Anyone</option>'
        + names.map((v) => `<option value="${esc(v)}"${forWhoKey(v) === forWhoKey(current) && current ? ' selected' : ''}>For ${esc(v)}</option>`).join('');
    $('fStoreList').innerHTML = stores.map((v) => `<option value="${esc(v)}"></option>`).join('');
}

/** How many filters are on: search, for who, store, status (shown on the tab). */
function activeFilterCount() {
    const f = UI.filter;
    return [f.q, f.forWho, f.store, f.status !== 'all'].filter(Boolean).length;
}

function renderFilterSummary() {
    $('filterSummary').textContent = filterActive() ? filterDescription() : 'All items';
    const count = activeFilterCount();
    $('filterTab').classList.toggle('active', count > 0);
    $('filterTabBadge').hidden = count === 0;
    $('filterTabBadge').textContent = String(count);
    $('filterTab').setAttribute('aria-label', count ? `Filter, ${count} on: ${filterDescription()}` : 'Filter and whose list');
}

/** Owner on "Everyone": matching items from every member's list, grouped by person. */
function renderResults() {
    const box = $('shopResults');
    const active = filterActive();
    box.classList.toggle('hidden', !active);
    if (!active) return;

    const all = shoppingList();
    const matches = all.filter(matchesFilter);
    $('resultsCount').textContent = `${matches.length} of ${all.length} items match · ${filterDescription()}`;
    if (!matches.length) {
        $('resultsList').innerHTML = '<li class="empty-list">No items match. Try a different name or clear the filter.</li>';
        return;
    }
    $('resultsList').innerHTML = S.shoppers.map((p) => {
        const items = matches.filter((i) => Number(i.user_id) === Number(p.user_id));
        if (!items.length) return '';
        return `<li class="for-head"><h3>${esc(displayName(p))}</h3><span class="tnum">${esc(groupMoney(items))}</span></li>`
            + items.map((item) => itemRowHtml(item, canManageShoppingOf(item.user_id))).join('');
    }).join('');
}

function renderPersonList(person) {
    const editable = canManageShoppingOf(person.user_id);
    const allItems = itemsOf(person.user_id);
    const active = filterActive();
    const items = active ? allItems.filter(matchesFilter) : allItems;

    $('filterBar').classList.toggle('hidden', !active);
    if (active) $('filterBarText').textContent = `Showing ${items.length} of ${allItems.length} items · ${filterDescription()}`;

    if (!allItems.length) {
        $('forChips').classList.add('hidden');
        $('shopList').innerHTML = `<li class="empty-list">Nothing on ${person.is_me ? 'your' : `${esc(person.name)}’s`} list yet.</li>`;
        return;
    }
    if (!items.length) {
        $('forChips').classList.add('hidden');
        $('shopList').innerHTML = '<li class="empty-list">No items match this filter.</li>';
        return;
    }

    const groups = forWhoGroups(items);
    renderForChips(groups);
    const showHeadings = groups.some((g) => g.key !== UNASSIGNED);
    const shown = UI.forWho === 'all' ? groups : groups.filter((g) => g.key === UI.forWho);

    $('shopList').innerHTML = shown.map((g) => {
        const heading = showHeadings
            ? `<li class="for-head${g.key === UNASSIGNED ? ' none' : ''}"><h3>${g.key === UNASSIGNED ? 'Not assigned' : `For ${esc(g.label)}`}</h3><span class="tnum">${esc(groupMoney(g.items))}</span></li>`
            : '';
        return heading + g.items.map((item) => itemRowHtml(item, editable)).join('');
    }).join('');
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
    // While the drawer is open, keep showing the person picked there (not yet applied).
    if (owner) renderOwnerPick(drawerOpen() && draftShopper !== null ? draftShopper : sel);

    const auto = autoRate();
    document.querySelectorAll('[data-rate]').forEach((input) => {
        if (document.activeElement !== input) input.value = rate();
        input.readOnly = !canEdit() || auto;
        input.title = auto ? 'Following today’s rate. To type your own, turn off “Today’s exchange rate” in Account.' : '';
    });

    const person = S.shoppers.find((p) => p.user_id === sel);
    $('shopAll').classList.toggle('hidden', sel !== 'all');
    $('shopPerson').classList.toggle('hidden', !person);
    if (sel === 'all') renderAll();
    else if (person) renderPerson(person);
    renderFilterSummary();
    renderFx();
    renderShopTab();
}

/** Set the trip's shopping rate (RM per ¥100), e.g. from today's Bank Negara rate. */
export function setTripRate(value) {
    if (!canEdit()) return;
    S.trip.exchange_rate_rm_per_100_yen = Math.max(0, Number(value) || 0);
    document.querySelectorAll('[data-rate]').forEach((input) => { input.value = S.trip.exchange_rate_rm_per_100_yen; });
    renderShop();
    saveRate();
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

/** Quantity changes are batched so tapping + a few times sends one update. */
const saveQty = debounceKeyed((id) => {
    const item = S.items.get(id);
    if (item) saveItem(item, { qty: item.qty, estimated_yen: item.estimated_yen, actual_yen: item.actual_yen });
}, 600);

/** Change quantity by one; estimate and paid are totals, so both scale with it. */
function stepQty(item, step) {
    const current = Number(item.qty) || 1;
    const next = Math.min(999, Math.max(1, current + step));
    if (next === current) return;
    const est = Number(item.estimated_yen) || 0;
    if (est > 0) item.estimated_yen = Math.round((est / current) * next);
    const paid = Number(item.actual_yen) || 0;
    if (paid > 0) item.actual_yen = Math.round((paid / current) * next);
    item.qty = next;
    renderShop();
    saveQty(item.id);
}

const savePaid = debounceKeyed((id) => {
    const item = S.items.get(id);
    if (item) saveItem(item, { actual_yen: item.actual_yen, is_bought: item.is_bought });
}, 700);

export function bindShopping() {
    bindFx({ onUseRate: setTripRate, onLoaded: () => { if (S.trip) renderShop(); }, isAuto: autoRate });

    $('v-shopping').addEventListener('click', (e) => {
        const pick = e.target.closest('[data-shopper]');
        if (pick && isOwner() && pick.closest('#filterDrawer')) {
            // In the drawer: only a draft until Apply.
            draftShopper = pick.dataset.shopper === 'all' ? 'all' : Number(pick.dataset.shopper);
            renderOwnerPick(draftShopper);
            fillFilterSuggestions(draftShopper);
            return;
        }
        if (pick && isOwner()) {
            UI.shopSel = pick.dataset.shopper === 'all' ? 'all' : Number(pick.dataset.shopper);
            UI.forWho = 'all';
            renderShop();
            if (pick.classList.contains('person-card')) window.scrollTo({ top: 0 });
            return;
        }
        const forChip = e.target.closest('[data-for]');
        if (forChip) {
            UI.forWho = forChip.dataset.for;
            renderShop();
            return;
        }
        const step = e.target.closest('[data-act="qty"]');
        if (step) {
            const item = S.items.get(step.closest('li').dataset.id);
            if (item && canManageShoppingOf(item.user_id)) stepQty(item, Number(step.dataset.step));
            return;
        }
        const del = e.target.closest('[data-act="delete-item"]');
        if (del) {
            const item = S.items.get(del.closest('li').dataset.id);
            if (!item || !canManageShoppingOf(item.user_id)) return;
            confirmAction({
                title: `Delete “${item.name}”?`,
                text: 'It comes off your shopping list. You can undo right after.',
                ok: 'Delete item',
            }).then((sure) => { if (sure && S.items.has(item.id)) deleteItemWithUndo(item); });
            return;
        }
        const edit = e.target.closest('[data-act="edit-item"]');
        if (edit) {
            const item = S.items.get(edit.closest('li').dataset.id);
            if (item && canManageShoppingOf(item.user_id)) openItemForm(item.id);
        }
    });

    // Delegated on the whole view so rows in the filter results work too.
    $('v-shopping').addEventListener('change', (e) => {
        if (e.target.dataset.act !== 'buy') return;
        const item = S.items.get(e.target.closest('li').dataset.id);
        if (!item || !canManageShoppingOf(item.user_id)) return;
        const before = item.is_bought;
        item.is_bought = e.target.checked;
        renderShop();
        saveItem(item, { is_bought: item.is_bought }, () => { item.is_bought = before; renderShop(); });
    });

    $('v-shopping').addEventListener('input', (e) => {
        const id = e.target.dataset.paid;
        if (!id) return;
        const item = S.items.get(id);
        if (!item || !canManageShoppingOf(item.user_id)) return;
        // The box is the price for one; the stored amount is the total for the quantity.
        const each = Math.max(0, Math.round(Number(e.target.value.replace(/[^0-9]/g, '')) || 0));
        item.actual_yen = each ? Math.min(10_000_000, each * (Number(item.qty) || 1)) : null;
        // Same rule as the server: an item counts towards the day Paid ¥ was first entered.
        if (item.actual_yen != null && !item.paid_at) item.paid_at = new Date().toISOString();
        const row = e.target.closest('li');
        if (each > 0 && !item.is_bought) {
            item.is_bought = true;
            row.classList.add('bought');
            row.querySelector('[data-act="buy"]').checked = true;
        }
        row.querySelector('.item-meta').textContent = metaText(item);
        renderNumbersOnly(); // keep the list as is so the input keeps focus
        savePaid(id);
    });

    document.querySelectorAll('[data-rate]').forEach((input) => {
        input.addEventListener('input', () => {
            if (!canEdit()) return;
            if (autoRate()) return;
            const typed = Number(input.value);
            // Half-typed or cleared ("" / "0" / "2."): keep the current rate until a real one is entered.
            if (!(typed > 0)) return;
            S.trip.exchange_rate_rm_per_100_yen = typed;
            document.querySelectorAll('[data-rate]').forEach((other) => { if (other !== input) other.value = input.value; });
            renderNumbersOnly();
            renderFx();
            saveRate();
        });
        // Leaving the box empty or at 0 puts the rate in use back.
        input.addEventListener('blur', () => {
            if (!(Number(input.value) > 0)) input.value = rate();
        });
    });

    document.querySelectorAll('[data-rate-auto]').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            if (!canEdit()) {
                toggle.checked = autoRate();
                return;
            }
            // Switching to a fixed rate starts from the rate in use now (today's), so nothing jumps.
            const current = rate();
            const before = { auto: S.trip.exchange_rate_auto, rate: S.trip.exchange_rate_rm_per_100_yen };
            S.trip.exchange_rate_auto = toggle.checked;
            if (!toggle.checked && current > 0) S.trip.exchange_rate_rm_per_100_yen = current;
            renderShop();
            write({
                key: 'trip:settings',
                method: 'PATCH',
                path: `/trips/${S.trip.id}`,
                body: {
                    exchange_rate_auto: S.trip.exchange_rate_auto,
                    ...(S.trip.exchange_rate_rm_per_100_yen > 0 ? { exchange_rate_rm_per_100_yen: Number(S.trip.exchange_rate_rm_per_100_yen) } : {}),
                },
                revert: () => {
                    S.trip.exchange_rate_auto = before.auto;
                    S.trip.exchange_rate_rm_per_100_yen = before.rate;
                    renderShop();
                },
            });
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

    bindFilter();
}

/** Put what is applied now into the drawer's fields, so closing without Apply loses nothing. */
function fillDrawerFromApplied() {
    const form = $('filterForm');
    const f = UI.filter;
    draftShopper = selectedShopper();
    if (isOwner()) renderOwnerPick(draftShopper);
    fillFilterSuggestions(draftShopper);
    form.elements.q.value = f.q;
    form.elements.store.value = f.store;
    form.querySelector(`input[name="status"][value="${f.status}"]`).checked = true;
}

/** Scroll so the list's top (or the Everyone results) is in view after a change. */
function showListTop() {
    const target = selectedShopper() === 'all' ? (filterActive() ? $('shopResults') : $('shopAll')) : $('shopList').previousElementSibling;
    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function bindFilter() {
    const form = $('filterForm');
    const drawer = $('filterDrawer');

    $('filterTab').addEventListener('click', () => {
        fillDrawerFromApplied();
        drawer.showModal();
        $('filterDrawerTitle').focus();
    });

    // Close without applying: ✕, Back/Esc, or a tap on the dimmed page.
    const discard = () => {
        if (drawer.open) drawer.close();
        draftShopper = null;
        renderFilterSummary();
    };
    $('filterClose').addEventListener('click', discard);
    drawer.addEventListener('cancel', (e) => { e.preventDefault(); discard(); });
    drawer.addEventListener('click', (e) => { if (e.target === drawer) discard(); });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const data = new FormData(form);
        UI.filter = {
            q: String(data.get('q') || '').trim(),
            forWho: String(data.get('for_who') || '').trim(),
            store: String(data.get('store') || '').trim(),
            status: String(data.get('status') || 'all'),
        };
        if (isOwner() && draftShopper !== null) UI.shopSel = draftShopper;
        UI.forWho = 'all';
        drawer.close();
        draftShopper = null;
        renderShop();
        showListTop();
    });

    const reset = () => {
        form.reset();
        UI.filter = { q: '', forWho: '', store: '', status: 'all' };
        UI.forWho = 'all';
        if (drawer.open) drawer.close();
        draftShopper = null;
        renderShop();
    };
    $('filterClear').addEventListener('click', () => {
        reset();
        toast('Filter reset. Showing all items.');
    });
    $('v-shopping').addEventListener('click', (e) => {
        if (e.target.closest('[data-filter-clear]')) reset();
    });
}

export function computeTotals() {
    return money(S.shoppers.reduce((sum, p) => sum + (Number(p.budget_rm) || 0), 0), shoppingList());
}
