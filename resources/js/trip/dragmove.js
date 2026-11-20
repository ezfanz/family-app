/*
 * Drag and drop on the itinerary (editors and owners).
 *
 * - A stop onto another day: moves it there at the same time.
 * - A day tile onto another day: swaps the two days' plans (city, title,
 *   stops, planned ideas); the dates stay where they are.
 *
 * Touch: press and hold until it lifts, then drag. Mouse: a stop drags right
 * away; a day tile needs a short hold (a plain drag scrolls the day strip).
 * While dragging, a tray of every day slides up from the bottom (the strip
 * may be out of view), and the day tiles at the top take a drop too. Let go
 * on a day to do it, with Undo; anywhere else, or Escape, cancels.
 */

import { S, canEdit, dayById, dayActs } from './store.js';
import { $, esc, fmtDate, weekday, cityColor, cityLetter, toast } from './util.js';
import { write } from './writer.js';

const HOLD_MS = 380;
const SLOP = 8;
const EDGE = 70;
const TIP_KEY = 'tabi:tip:drag-v1';

let onChanged = () => {};
let press = null; // { kind, id, x, y, timer, mouse, el }
let drag = null; // { kind, id, ghost, target, startDay, el }
let suppressClick = false;

const dayLabel = (day) => `Day ${day.day_number}${day.city ? ` · ${day.city}` : ''}`;

function trayHtml(kind, currentDayId) {
    const chips = S.days.map((d) => `<button type="button" class="drop-day${d.id === currentDayId ? ' is-here' : ''}" data-drop-day="${d.id}" style="--c:${cityColor(d.city)}" ${d.id === currentDayId ? 'aria-disabled="true"' : ''}>
        <span class="drop-day-n tnum">${d.day_number}</span>
        <span class="drop-day-c">${esc(cityLetter(d.city))}</span>
        <span class="drop-day-d tnum">${esc(fmtDate(d.date))}</span>
    </button>`).join('');
    const idle = kind === 'day' ? 'Drop on another day to swap the two plans' : 'Drop on a day to move it';
    return `<div class="drop-tray-head" id="dropTrayHead" data-idle="${esc(idle)}">${esc(idle)}</div><div class="drop-tray-grid">${chips}</div>`;
}

function ghostHtml() {
    if (drag.kind === 'day') {
        const day = dayById(drag.id);
        const count = dayActs(day.id).length;
        return `<span class="ghost-day" style="--c:${cityColor(day.city)}">${day.day_number}</span><strong>${esc(day.city || 'Day')} · ${count} stop${count === 1 ? '' : 's'}</strong>`;
    }
    const act = S.acts.get(drag.id);
    return `<span class="tnum">${esc(act.time || '')}</span><strong>${esc(act.name)}</strong>`;
}

function startDrag(x, y) {
    const { kind, id, el } = press;
    const startDay = kind === 'day' ? id : S.acts.get(id)?.day_id;
    if (!startDay || (kind === 'day' && !dayById(id))) return;
    el?.classList.remove('pressing');
    drag = { kind, id, target: null, startDay, el };
    if (navigator.userActivation?.hasBeenActive) navigator.vibrate?.(12);

    const ghost = document.createElement('div');
    ghost.className = `drag-ghost${kind === 'day' ? ' is-day' : ''}`;
    document.body.appendChild(ghost);
    drag.ghost = ghost;
    ghost.innerHTML = `<span class="ghost-main">${ghostHtml()}</span><em class="ghost-target" hidden></em>`;
    moveGhost(x, y);

    const tray = $('dropTray');
    tray.innerHTML = trayHtml(kind, startDay);
    tray.hidden = false;
    requestAnimationFrame(() => tray.classList.add('open'));

    el?.classList.add('dragging');
    document.body.classList.add('dragging-stop');
    $('days').querySelectorAll('.daybtn').forEach((b) => b.classList.toggle('drop-ok', Number(b.dataset.day) !== startDay));
}

function moveGhost(x, y) {
    // Above the finger, so the day being aimed at stays visible.
    drag.ghost.style.transform = `translate(${Math.round(x - 40)}px, ${Math.round(y - 84)}px)`;
}

/** The day under the finger: a tray chip or a day tile (not the one being dragged from). */
function dayAt(x, y) {
    const el = document.elementFromPoint(x, y);
    const target = el?.closest('[data-drop-day], #days .daybtn');
    if (!target) return null;
    const id = Number(target.dataset.dropDay || target.dataset.day);
    return id && id !== drag.startDay ? { id, el: target } : null;
}

function highlight(hit) {
    document.querySelectorAll('.drop-hover').forEach((el) => el.classList.remove('drop-hover'));
    drag.target = hit?.id ?? null;
    const head = $('dropTrayHead');
    const target = drag.ghost.querySelector('.ghost-target');
    if (!hit) {
        head.textContent = head.dataset.idle;
        head.classList.remove('ready');
        target.hidden = true;
        return;
    }
    const to = dayById(hit.id);
    // The card covers the tray header near the bottom, so it says where it goes too.
    target.textContent = `${drag.kind === 'day' ? '⇄ Swap with' : '→ Move to'} ${dayLabel(to)}`;
    target.hidden = false;
    hit.el.classList.add('drop-hover');
    const day = dayById(hit.id);
    if (drag.kind === 'day') {
        const from = dayById(drag.startDay);
        head.textContent = `Swap Day ${from.day_number} ${from.city ? `(${from.city})` : ''} ⇄ Day ${day.day_number} ${day.city ? `(${day.city})` : ''}`;
    } else {
        head.textContent = `Move to ${dayLabel(day)} · ${weekday(day.date, true)} ${fmtDate(day.date)}`;
    }
    head.classList.add('ready');
}

/** Scroll the page (and the day strip) when the finger nears an edge. */
function autoScroll(x, y) {
    if (y < EDGE) window.scrollBy(0, -Math.ceil((EDGE - y) / 4));
    const strip = $('days').getBoundingClientRect();
    if (y >= strip.top && y <= strip.bottom) {
        if (x < strip.left + EDGE) $('days').scrollLeft -= 10;
        else if (x > strip.right - EDGE) $('days').scrollLeft += 10;
    }
}

function finish(commit) {
    const done = drag;
    drag = null;
    press = null;
    document.querySelectorAll('.drop-hover, .drop-ok, .pressing').forEach((el) => el.classList.remove('drop-hover', 'drop-ok', 'pressing'));
    document.body.classList.remove('dragging-stop');
    done?.el?.classList.remove('dragging');
    done?.ghost.remove();
    const tray = $('dropTray');
    tray.classList.remove('open');
    setTimeout(() => { if (!drag) tray.hidden = true; }, 200);
    if (!commit || !done?.target) return;
    if (done.kind === 'day') swapDays(done.startDay, done.target);
    else moveStop(done.id, done.startDay, done.target);
    dismissTip();
}

function moveStop(id, fromDay, toDay, quiet = false) {
    const act = S.acts.get(id);
    const day = dayById(toDay);
    if (!act || !day) return;
    act.day_id = toDay;
    onChanged();
    write({
        key: `act:${id}`,
        method: 'PATCH',
        path: `/activities/${id}`,
        body: { day_id: toDay },
        onSaved: (res) => { if (res?.data) S.acts.set(res.data.id, res.data); },
        revert: () => { act.day_id = fromDay; onChanged(); },
    });
    if (quiet) return;
    toast(`Moved “${act.name}” to ${dayLabel(day)}.`, {
        action: 'Undo',
        onAction: () => moveStop(id, toDay, fromDay, true),
    });
}

/** Mirror the server: swap city, title, stops (not the tax refund stop) and planned ideas. */
function swapLocally(a, b) {
    const dayA = dayById(a);
    const dayB = dayById(b);
    [dayA.city, dayB.city] = [dayB.city, dayA.city];
    [dayA.title, dayB.title] = [dayB.title, dayA.title];
    for (const act of S.acts.values()) {
        if (act.system_key) continue;
        if (act.day_id === a) act.day_id = b;
        else if (act.day_id === b) act.day_id = a;
    }
    for (const idea of S.ideas.values()) {
        if (idea.planned_day_id === a) idea.planned_day_id = b;
        else if (idea.planned_day_id === b) idea.planned_day_id = a;
    }
}

function swapDays(a, b, quiet = false) {
    const dayA = dayById(a);
    const dayB = dayById(b);
    if (!dayA || !dayB) return;
    swapLocally(a, b);
    onChanged();
    write({
        key: `swap:${Math.min(a, b)}:${Math.max(a, b)}:${Date.now()}`,
        method: 'POST',
        path: `/trips/${S.trip.id}/days/swap`,
        body: { day_id: a, with_day_id: b },
        revert: () => { swapLocally(a, b); onChanged(); },
    });
    if (quiet) return;
    toast(`Swapped Day ${dayA.day_number} and Day ${dayB.day_number}. Dates stay the same.`, {
        action: 'Undo',
        onAction: () => swapDays(a, b, true),
    });
}

function cancelPress() {
    if (press?.timer) clearTimeout(press.timer);
    press?.el?.classList.remove('pressing');
    press = null;
}

/* ---------- One-time tip ---------- */

function tipDismissed() {
    try {
        return localStorage.getItem(TIP_KEY) === '1';
    } catch {
        return false;
    }
}

function dismissTip() {
    try {
        localStorage.setItem(TIP_KEY, '1');
    } catch {
        /* storage blocked */
    }
    const tip = $('dragTip');
    if (tip) tip.hidden = true;
}

/** Show the drag tip to editors until they dismiss it or use drag once. */
export function renderDragTip() {
    const tip = $('dragTip');
    if (tip) tip.hidden = !canEdit() || tipDismissed() || S.days.length < 2;
}

/**
 * @param {{ onChanged: () => void }} hooks re-render after a move or swap
 */
export function bindDragMove(hooks) {
    onChanged = hooks.onChanged;
    $('dragTipClose')?.addEventListener('click', dismissTip);

    const begin = (e, kind, id, el, holdForMouse) => {
        const mouse = e.pointerType === 'mouse';
        press = { kind, id, x: e.clientX, y: e.clientY, mouse, el, timer: null };
        const x = e.clientX;
        const y = e.clientY;
        if (!mouse || holdForMouse) {
            el.classList.add('pressing');
            press.timer = setTimeout(() => { if (press) startDrag(x, y); }, HOLD_MS);
        }
    };

    $('rail').addEventListener('pointerdown', (e) => {
        if (!canEdit() || drag || e.button > 0) return;
        const head = e.target.closest('.stop .head');
        if (!head || e.target.closest('a, input, textarea, select, [data-act="cycle"]')) return;
        const stop = head.closest('.stop');
        begin(e, 'stop', stop.dataset.id, stop, false);
    });

    $('days').addEventListener('pointerdown', (e) => {
        if (!canEdit() || drag || e.button > 0) return;
        const tile = e.target.closest('.daybtn');
        if (!tile) return;
        begin(e, 'day', Number(tile.dataset.day), tile, true);
    });

    const dragTo = (x, y) => {
        moveGhost(x, y);
        highlight(dayAt(x, y));
        autoScroll(x, y);
    };

    document.addEventListener('pointermove', (e) => {
        if (press && !drag) {
            const moved = Math.hypot(e.clientX - press.x, e.clientY - press.y) > SLOP;
            if (moved && press.mouse && !press.timer) startDrag(e.clientX, e.clientY);
            else if (moved) cancelPress(); // scrolling (or strip dragging), not a drag
        }
        if (!drag) return;
        e.preventDefault();
        dragTo(e.clientX, e.clientY);
    }, { passive: false });

    // Touch: keep the page still under a lifted item and follow the finger even if
    // the browser stops sending pointer events.
    document.addEventListener('touchmove', (e) => {
        if (!drag) return;
        e.preventDefault();
        const touch = e.touches[0];
        if (touch) dragTo(touch.clientX, touch.clientY);
    }, { passive: false });

    const drop = (commit) => {
        suppressClick = true;
        setTimeout(() => { suppressClick = false; }, 300);
        finish(commit);
    };
    document.addEventListener('pointerup', () => (drag ? drop(true) : cancelPress()));
    document.addEventListener('touchend', () => { if (drag) drop(true); });
    // A browser may cancel the pointer once a touch moves; the touch events carry on.
    document.addEventListener('pointercancel', (e) => {
        if (!drag) cancelPress();
        else if (e.pointerType === 'mouse') drop(false);
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drag) drop(false); });

    // The tap that ends a drag must not also open a stop or pick a day.
    for (const area of [$('rail'), $('days')]) {
        area.addEventListener('click', (e) => {
            if (suppressClick) {
                e.stopPropagation();
                e.preventDefault();
            }
        }, true);
        area.addEventListener('contextmenu', (e) => {
            if (canEdit() && e.target.closest('.stop .head, .daybtn')) e.preventDefault();
        });
    }
}

