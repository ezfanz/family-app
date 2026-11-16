/* Shopping list with RM totals. Balance = banked in − spent − estimate still to buy. */

import { S, canEdit, shoppingList } from './store.js';
import { $, esc, yen, rm, debounce, debounceKeyed } from './util.js';
import { write } from './writer.js';

let openItemForm = () => {};
export function setItemFormOpener(fn) {
    openItemForm = fn;
}

const rate = () => Number(S.trip.exchange_rate_rm_per_100_yen) || 0;

function metaText(item) {
    const parts = [`×${item.qty}`];
    const est = Number(item.estimated_yen) || 0;
    parts.push(est ? `est ${yen(est)} ≈ ${rm((est * rate()) / 100, 0)}` : 'no estimate');
    if (item.for_whom) parts.push(item.for_whom);
    return parts.join(' · ');
}

export function computeTotals(items = shoppingList(), r = rate()) {
    let estimatedYen = 0;
    let spentYen = 0;
    let stillToBuyYen = 0;
    let bought = 0;
    for (const item of items) {
        const est = Number(item.estimated_yen) || 0;
        estimatedYen += est;
        if (item.is_bought) {
            bought += 1;
            spentYen += Number(item.actual_yen) || est;
        } else {
            stillToBuyYen += est;
        }
    }
    const deposit = Number(S.trip.deposit_rm) || 0;
    return {
        estimated: (estimatedYen * r) / 100,
        spent: (spentYen * r) / 100,
        balance: deposit - ((spentYen + stillToBuyYen) * r) / 100,
        deposit,
        bought,
        count: items.length,
    };
}

function renderTotals() {
    const t = computeTotals();
    $('sEst').textContent = rm(t.estimated);
    $('sSpent').textContent = rm(t.spent);
    const bal = $('sBal');
    if (!t.deposit) {
        bal.textContent = '–';
        bal.className = 'v tnum';
        $('sNote').textContent = 'Type how much she banked in to see the balance.';
    } else {
        bal.textContent = rm(t.balance);
        bal.className = `v tnum ${t.balance < 0 ? 'neg' : 'pos'}`;
        $('sNote').textContent = t.balance < 0
            ? 'Not enough. Ask her to bank in more, or drop an item.'
            : 'Banked in, minus spent and still to buy.';
    }
    $('shopCount').textContent = `${t.count} item${t.count === 1 ? '' : 's'} · ${t.bought} bought`;
}

function renderList() {
    const editable = canEdit();
    $('shopList').innerHTML = shoppingList().map((item) => `
        <li class="${item.is_bought ? 'bought' : ''}" data-id="${item.id}"><div class="shop-row">
            <label class="check"><input type="checkbox" data-act="buy" ${item.is_bought ? 'checked' : ''} ${editable ? '' : 'disabled'} aria-label="Bought ${esc(item.name)}"></label>
            <button type="button" class="item-main" data-act="edit-item"${editable ? '' : ' aria-disabled="true" tabindex="-1"'}>
                <span class="item-name">${esc(item.name)}</span>
                <span class="item-meta tnum" data-meta>${esc(metaText(item))}</span>
            </button>
            <div class="paid"><span aria-hidden="true">Paid ¥</span><input class="tnum" inputmode="numeric" pattern="[0-9]*" placeholder="0" data-paid="${item.id}" value="${item.actual_yen ?? ''}" aria-label="Paid in yen for ${esc(item.name)}" ${editable ? '' : 'readonly'}></div>
        </div></li>`).join('');
}

export function renderShop() {
    const editable = canEdit();
    const rateInput = $('rate');
    const depositInput = $('deposit');
    if (document.activeElement !== rateInput) rateInput.value = rate();
    if (document.activeElement !== depositInput) depositInput.value = Number(S.trip.deposit_rm) || '';
    rateInput.readOnly = !editable;
    depositInput.readOnly = !editable;
    $('addItem').classList.toggle('hidden', !editable);
    renderList();
    renderTotals();
}

function saveItem(item, changes, revert) {
    write({
        key: `item:${item.id}`,
        method: 'PATCH',
        path: `/shopping-items/${item.id}`,
        body: changes,
        revert,
    });
}

const saveSettings = debounce(() => {
    write({
        key: 'trip:settings',
        method: 'PATCH',
        path: `/trips/${S.trip.id}`,
        body: { exchange_rate_rm_per_100_yen: rate(), deposit_rm: Number(S.trip.deposit_rm) || 0 },
    });
}, 600);

const savePaid = debounceKeyed((id) => {
    const item = S.items.get(id);
    if (item) {
        saveItem(item, { actual_yen: item.actual_yen, is_bought: item.is_bought });
    }
}, 700);

export function bindShopping() {
    $('shopList').addEventListener('change', (e) => {
        if (e.target.dataset.act !== 'buy') return;
        const item = S.items.get(e.target.closest('li').dataset.id);
        if (!item) return;
        const before = item.is_bought;
        item.is_bought = e.target.checked;
        renderShop();
        saveItem(item, { is_bought: item.is_bought }, () => { item.is_bought = before; renderShop(); });
    });

    $('shopList').addEventListener('input', (e) => {
        const id = e.target.dataset.paid;
        if (!id) return;
        const item = S.items.get(id);
        if (!item) return;
        const value = Math.max(0, Math.round(Number(e.target.value.replace(/[^0-9]/g, '')) || 0));
        item.actual_yen = value || null;
        if (value > 0 && !item.is_bought) {
            item.is_bought = true;
            const row = e.target.closest('li');
            row.classList.add('bought');
            row.querySelector('[data-act="buy"]').checked = true;
        }
        renderTotals(); // keep the list as is so the input keeps focus
        savePaid(id);
    });

    $('shopList').addEventListener('click', (e) => {
        const b = e.target.closest('[data-act="edit-item"]');
        if (b && canEdit()) openItemForm(b.closest('li').dataset.id);
    });

    const onSetting = () => {
        if (!canEdit()) return;
        S.trip.exchange_rate_rm_per_100_yen = Math.max(0, Number($('rate').value) || 0);
        S.trip.deposit_rm = Math.max(0, Number($('deposit').value) || 0);
        renderList();
        renderTotals();
        saveSettings();
    };
    $('rate').addEventListener('input', onSetting);
    $('deposit').addEventListener('input', onSetting);
    $('addItem').addEventListener('click', () => openItemForm(null));
}
