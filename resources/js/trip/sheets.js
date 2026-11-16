/* Bottom sheets for adding and editing stops, shopping items and ideas. */

import { S, UI, canEdit, shoppingList } from './store.js';
import { $, fmtDate, weekday, rm, uuid, toast } from './util.js';
import { write } from './writer.js';
import { regions, saveIdea } from './ideas.js';

let rerender = () => {};

function guardEdit() {
    if (!canEdit()) {
        toast('You can view this trip but not edit it.');
        return false;
    }
    return true;
}

function armDelete(button, label, action) {
    if (button.dataset.armed) {
        action();
        return;
    }
    button.dataset.armed = '1';
    button.textContent = 'Tap again to delete';
}

function resetDelete(button, label) {
    delete button.dataset.armed;
    button.textContent = label;
}

function invalid(input, message) {
    input.setAttribute('aria-invalid', 'true');
    input.focus();
    toast(message);
}

/* ---------------- stops ---------------- */

const actDlg = () => $('actDlg');
let editingAct = null;
let planningIdeaId = null;

export function openActForm(id, preset = {}, ideaId = null) {
    if (!guardEdit()) return;
    const f = $('actForm').elements;
    const a = id ? S.acts.get(id) : { day_id: UI.dayId, time: '', name: '', transport: '', meal: '', ticket: '', notes: '', places: [], is_halal: false, ...preset };
    if (!a) return;
    editingAct = id;
    planningIdeaId = ideaId;

    f.day.innerHTML = S.days.map((d) => `<option value="${d.id}">Day ${d.day_number} · ${weekday(d.date, true)} ${fmtDate(d.date)}${d.city ? ` · ${d.city}` : ''}</option>`).join('');
    f.day.value = String(a.day_id ?? UI.dayId);
    f.time.value = id ? a.time : '';
    f.name.value = a.name || '';
    f.places.value = (a.places || []).join('\n');
    f.transport.value = a.transport || '';
    f.meal.value = a.meal || '';
    f.ticket.value = a.ticket || '';
    f.halal.checked = !!a.is_halal;
    f.notes.value = a.notes || '';
    [f.name, f.time].forEach((el) => el.removeAttribute('aria-invalid'));

    $('actDlgTitle').textContent = id ? 'Edit stop' : 'Add a stop';
    resetDelete($('actDel'), 'Delete this stop');
    $('actDel').closest('.danger-row').classList.toggle('hidden', !id);
    actDlg().showModal();
    if (!id) f.name.focus();
}

function submitAct(e) {
    e.preventDefault();
    if (!guardEdit()) return;
    const f = $('actForm').elements;
    const name = f.name.value.trim().slice(0, 160);
    if (!name) return invalid(f.name, 'Give the stop a name.');
    if (!f.time.value) return invalid(f.time, 'Pick a time.');

    const data = {
        day_id: Number(f.day.value),
        time: f.time.value.slice(0, 5),
        name,
        transport: f.transport.value.trim().slice(0, 200),
        meal: f.meal.value,
        ticket: f.ticket.value.trim().slice(0, 40),
        notes: f.notes.value.trim().slice(0, 1000),
        places: f.places.value.split('\n').map((s) => s.trim()).filter(Boolean).slice(0, 10),
        is_halal: f.halal.checked,
    };

    if (editingAct) {
        const act = S.acts.get(editingAct);
        const before = { ...act };
        Object.assign(act, data);
        write({
            key: `act:${act.id}`, method: 'PATCH', path: `/activities/${act.id}`, body: data,
            revert: () => { S.acts.set(before.id, before); rerender(); },
            onSaved: (res) => { S.acts.set(res.data.id, res.data); },
        });
        toast('Stop updated.');
    } else {
        const id = uuid();
        S.acts.set(id, { id, ...data, status: 'pending', sort_order: 9999 });
        UI.open.add(id);
        write({
            key: `act:${id}`, method: 'POST', path: '/activities', body: { id, ...data },
            revert: () => { S.acts.delete(id); rerender(); },
            onSaved: (res) => { S.acts.set(res.data.id, res.data); },
        });
        if (planningIdeaId) {
            const idea = S.ideas.get(planningIdeaId);
            if (idea) {
                idea.planned_day_id = data.day_id;
                saveIdea(idea, { planned_day_id: data.day_id });
            }
        }
        toast('Stop added.');
    }

    UI.dayId = data.day_id;
    actDlg().close();
    if (UI.view !== 'plan') {
        window.location.hash = '#plan';
    } else {
        rerender();
    }
}

function deleteAct() {
    const act = S.acts.get(editingAct);
    if (!act) return;
    S.acts.delete(act.id);
    write({
        key: `act:${act.id}`, method: 'DELETE', path: `/activities/${act.id}`,
        revert: () => { S.acts.set(act.id, act); rerender(); },
    });
    actDlg().close();
    rerender();
    toast('Stop deleted.');
}

/* ---------------- shopping items ---------------- */

const itemDlg = () => $('itemDlg');
let editingItem = null;

function updateItemRm() {
    const yenValue = Number($('itemForm').elements.est.value) || 0;
    const r = Number(S.trip.exchange_rate_rm_per_100_yen) || 0;
    $('itemRm').textContent = yenValue ? `About ${rm((yenValue * r) / 100, 0)} at your current rate.` : 'Leave the estimate empty if you don’t know yet.';
}

export function openItemForm(id) {
    if (!guardEdit()) return;
    const f = $('itemForm').elements;
    const it = id ? S.items.get(id) : { name: '', for_whom: '', qty: 1, estimated_yen: '' };
    if (!it) return;
    editingItem = id;
    f.name.value = it.name;
    f.for_whom.value = it.for_whom || '';
    f.qty.value = it.qty;
    f.est.value = it.estimated_yen || '';
    f.name.removeAttribute('aria-invalid');
    $('itemDlgTitle').textContent = id ? 'Edit item' : 'Add an item';
    resetDelete($('itemDel'), 'Delete this item');
    $('itemDel').closest('.danger-row').classList.toggle('hidden', !id);
    updateItemRm();
    itemDlg().showModal();
    if (!id) f.name.focus();
}

function submitItem(e) {
    e.preventDefault();
    if (!guardEdit()) return;
    const f = $('itemForm').elements;
    const name = f.name.value.trim().slice(0, 160);
    if (!name) return invalid(f.name, 'Give the item a name.');
    const data = {
        name,
        for_whom: f.for_whom.value.trim().slice(0, 80) || null,
        qty: Math.min(999, Math.max(1, Math.round(Number(f.qty.value) || 1))),
        estimated_yen: Math.max(0, Math.round(Number(f.est.value) || 0)),
    };

    if (editingItem) {
        const item = S.items.get(editingItem);
        const before = { ...item };
        Object.assign(item, data);
        write({
            key: `item:${item.id}`, method: 'PATCH', path: `/shopping-items/${item.id}`, body: data,
            revert: () => { S.items.set(before.id, before); rerender(); },
        });
        toast('Item updated.');
    } else {
        const id = uuid();
        const last = shoppingList().at(-1);
        S.items.set(id, { id, ...data, actual_yen: null, is_bought: false, sort_order: (last?.sort_order ?? 0) + 1 });
        write({
            key: `item:${id}`, method: 'POST', path: '/shopping-items', body: { id, trip_id: S.trip.id, ...data },
            revert: () => { S.items.delete(id); rerender(); },
            onSaved: (res) => { S.items.set(res.data.id, res.data); },
        });
        toast('Item added.');
    }
    itemDlg().close();
    rerender();
}

function deleteItem() {
    const item = S.items.get(editingItem);
    if (!item) return;
    S.items.delete(item.id);
    write({
        key: `item:${item.id}`, method: 'DELETE', path: `/shopping-items/${item.id}`,
        revert: () => { S.items.set(item.id, item); rerender(); },
    });
    itemDlg().close();
    rerender();
    toast('Item deleted.');
}

/* ---------------- ideas ---------------- */

const ideaDlg = () => $('ideaDlg');
let editingIdea = null;

export function openIdeaForm(id) {
    if (!guardEdit()) return;
    const f = $('ideaForm').elements;
    const idea = id ? S.ideas.get(id) : { name: '', region: regions()[0] || '', city: '', area: '' };
    if (!idea) return;
    editingIdea = id;
    $('regionList').innerHTML = regions().map((r) => `<option value="${r.replace(/"/g, '&quot;')}"></option>`).join('');
    f.name.value = idea.name;
    f.region.value = idea.region;
    f.city.value = idea.city || '';
    f.area.value = idea.area || '';
    [f.name, f.region].forEach((el) => el.removeAttribute('aria-invalid'));
    $('ideaDlgTitle').textContent = id ? 'Edit idea' : 'Add an idea';
    resetDelete($('ideaDel'), 'Delete this idea');
    $('ideaDel').closest('.danger-row').classList.toggle('hidden', !id);
    ideaDlg().showModal();
    if (!id) f.name.focus();
}

async function submitIdea(e) {
    e.preventDefault();
    if (!guardEdit()) return;
    const f = $('ideaForm').elements;
    const name = f.name.value.trim().slice(0, 160);
    const region = f.region.value.trim().slice(0, 32);
    if (!name) return invalid(f.name, 'Give the place a name.');
    if (!region) return invalid(f.region, 'Pick a group, e.g. Kansai.');
    const data = { name, region, city: f.city.value || null, area: f.area.value.trim().slice(0, 80) || null };

    if (editingIdea) {
        const idea = S.ideas.get(editingIdea);
        const before = { ...idea };
        Object.assign(idea, data);
        saveIdea(idea, data, () => { S.ideas.set(before.id, before); rerender(); });
        ideaDlg().close();
        rerender();
        toast('Idea updated.');
        return;
    }

    ideaDlg().close();
    const res = await write({ key: 'idea:new', method: 'POST', path: `/trips/${S.trip.id}/ideas`, body: data });
    if (res?.data) {
        S.ideas.set(res.data.id, res.data);
        rerender();
        toast('Idea added.');
    }
}

function deleteIdea() {
    const idea = S.ideas.get(editingIdea);
    if (!idea) return;
    S.ideas.delete(idea.id);
    write({
        key: `idea:${idea.id}`, method: 'DELETE', path: `/ideas/${idea.id}`,
        revert: () => { S.ideas.set(idea.id, idea); rerender(); },
    });
    ideaDlg().close();
    rerender();
    toast('Idea deleted.');
}

export function planIdea(idea) {
    openActForm(null, { name: idea.name, places: [idea.name] }, idea.id);
}

/* ---------------- wiring ---------------- */

export function bindSheets(render) {
    rerender = render;

    document.querySelectorAll('dialog.sheet').forEach((dlg) => {
        dlg.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dlg.close()));
        // Tap on the dimmed backdrop closes the sheet.
        dlg.addEventListener('click', (e) => {
            if (e.target === dlg) dlg.close();
        });
    });

    $('actForm').addEventListener('submit', submitAct);
    $('actDel').addEventListener('click', (e) => armDelete(e.currentTarget, 'Delete this stop', deleteAct));
    $('itemForm').addEventListener('submit', submitItem);
    $('itemForm').elements.est.addEventListener('input', updateItemRm);
    $('itemDel').addEventListener('click', (e) => armDelete(e.currentTarget, 'Delete this item', deleteItem));
    $('ideaForm').addEventListener('submit', submitIdea);
    $('ideaDel').addEventListener('click', (e) => armDelete(e.currentTarget, 'Delete this idea', deleteIdea));
}
