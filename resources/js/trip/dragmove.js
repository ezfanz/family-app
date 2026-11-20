/*
 * Move a stop to another day by dragging it.
 *
 * Touch: press and hold a stop (about a third of a second) until it lifts,
 * then drag. Mouse: press and drag. While dragging, a tray of all days
 * slides up from the bottom (the day strip may be scrolled out of view);
 * the day tiles at the top accept a drop too. Let go on a day to move the
 * stop there at the same time, with Undo. Let go anywhere else, or press
 * Escape, to cancel. Editors and owners only.
 */

import { S, canEdit, dayById } from './store.js';
import { $, esc, fmtDate, weekday, cityColor, cityLetter, toast } from './util.js';
import { write } from './writer.js';

const HOLD_MS = 350;
const SLOP = 8;
const EDGE = 70;

let onMoved = () => {};
let press = null; // { id, x, y, timer, pointerId, mouse }
let drag = null; // { id, ghost, target, startDay }
let suppressClick = false;

const dayLabel = (day) => `Day ${day.day_number}${day.city ? ` · ${day.city}` : ''}`;

function trayHtml(currentDayId) {
    const chips = S.days.map((d) => `<button type="button" class="drop-day${d.id === currentDayId ? ' is-here' : ''}" data-drop-day="${d.id}" style="--c:${cityColor(d.city)}" ${d.id === currentDayId ? 'aria-disabled="true"' : ''}>
        <span class="drop-day-n tnum">${d.day_number}</span>
        <span class="drop-day-c">${esc(cityLetter(d.city))}</span>
        <span class="drop-day-d tnum">${esc(fmtDate(d.date))}</span>
    </button>`).join('');
    return `<div class="drop-tray-head" id="dropTrayHead">Drop on a day to move it</div><div class="drop-tray-grid">${chips}</div>`;
}

function startDrag(e) {
    const act = S.acts.get(press.id);
    if (!act) return;
    const stop = document.querySelector(`#rail .stop[data-id="${CSS.escape(press.id)}"]`);
    drag = { id: press.id, target: null, startDay: act.day_id, stop };
    navigator.vibrate?.(12);

    const ghost = document.createElement('div');
    ghost.className = 'drag-ghost';
    ghost.innerHTML = `<span class="tnum">${esc(act.time || '')}</span><strong>${esc(act.name)}</strong>`;
    document.body.appendChild(ghost);
    drag.ghost = ghost;
    moveGhost(e.clientX, e.clientY);

    const tray = $('dropTray');
    tray.innerHTML = trayHtml(act.day_id);
    tray.hidden = false;
    requestAnimationFrame(() => tray.classList.add('open'));

    stop?.classList.add('dragging');
    document.body.classList.add('dragging-stop');
    $('days').querySelectorAll('.daybtn').forEach((b) => b.classList.toggle('drop-ok', Number(b.dataset.day) !== act.day_id));
}

function moveGhost(x, y) {
    // Above the finger, so the day being aimed at stays visible.
    drag.ghost.style.transform = `translate(${Math.round(x - 40)}px, ${Math.round(y - 84)}px)`;
}

/** The day under the finger: a tray chip or a day tile (not the stop's own day). */
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
    if (hit) {
        hit.el.classList.add('drop-hover');
        const day = dayById(hit.id);
        head.textContent = `Move to ${dayLabel(day)} · ${weekday(day.date, true)} ${fmtDate(day.date)}`;
        head.classList.add('ready');
    } else {
        head.textContent = 'Drop on a day to move it';
        head.classList.remove('ready');
    }
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
    document.querySelectorAll('.drop-hover, .drop-ok').forEach((el) => el.classList.remove('drop-hover', 'drop-ok'));
    document.body.classList.remove('dragging-stop');
    done?.stop?.classList.remove('dragging');
    done?.ghost.remove();
    const tray = $('dropTray');
    tray.classList.remove('open');
    setTimeout(() => { if (!drag) tray.hidden = true; }, 200);
    if (commit && done?.target) moveStop(done.id, done.startDay, done.target);
}

function moveStop(id, fromDay, toDay, quiet = false) {
    const act = S.acts.get(id);
    const day = dayById(toDay);
    if (!act || !day) return;
    act.day_id = toDay;
    onMoved();
    write({
        key: `act:${id}`,
        method: 'PATCH',
        path: `/activities/${id}`,
        body: { day_id: toDay },
        onSaved: (res) => { if (res?.data) S.acts.set(res.data.id, res.data); },
        revert: () => { act.day_id = fromDay; onMoved(); },
    });
    if (quiet) return;
    toast(`Moved “${act.name}” to ${dayLabel(day)}.`, {
        action: 'Undo',
        onAction: () => moveStop(id, toDay, fromDay, true),
    });
}

function cancelPress() {
    if (press?.timer) clearTimeout(press.timer);
    press = null;
}

/**
 * @param {{ onMoved: () => void }} hooks re-render after a stop changes day
 */
export function bindStopDrag(hooks) {
    onMoved = hooks.onMoved;
    const rail = $('rail');

    rail.addEventListener('pointerdown', (e) => {
        if (!canEdit() || drag || e.button > 0) return;
        const head = e.target.closest('.stop > .card > .head, .stop .head');
        if (!head || e.target.closest('a, input, textarea, select, [data-act="cycle"]')) return;
        const id = head.closest('.stop').dataset.id;
        const mouse = e.pointerType === 'mouse';
        press = { id, x: e.clientX, y: e.clientY, pointerId: e.pointerId, mouse, timer: null };
        // Touch and pen: hold to lift. Mouse: start as soon as it moves.
        if (!mouse) press.timer = setTimeout(() => { if (press) startDrag(e); }, HOLD_MS);
    });

    const dragTo = (x, y) => {
        moveGhost(x, y);
        highlight(dayAt(x, y));
        autoScroll(x, y);
    };

    document.addEventListener('pointermove', (e) => {
        if (press && !drag) {
            const moved = Math.hypot(e.clientX - press.x, e.clientY - press.y) > SLOP;
            if (moved && press.mouse) startDrag(e);
            else if (moved) cancelPress(); // the finger is scrolling, not dragging
        }
        if (!drag) return;
        e.preventDefault();
        dragTo(e.clientX, e.clientY);
    }, { passive: false });

    // Touch: keep the page still under a lifted stop and follow the finger even if
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
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && drag) finish(false); });

    // The tap that ends a drag must not also open or close the stop.
    rail.addEventListener('click', (e) => {
        if (suppressClick) {
            e.stopPropagation();
            e.preventDefault();
        }
    }, true);
    // No long-press menu (copy, look up) on stops for editors.
    rail.addEventListener('contextmenu', (e) => { if (canEdit() && e.target.closest('.stop .head')) e.preventDefault(); });
}
