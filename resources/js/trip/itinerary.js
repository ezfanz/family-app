/* Itinerary: header progress, day strip, now/next banner and the rail timeline. */

import { S, UI, canEdit, dayActs, allActs, dayById } from './store.js';
import { $, esc, fmtDate, weekday, zonedNow, toMins, daysBetween, cityColor, cityLetter, mapUrl, ICON, debounceKeyed } from './util.js';
import { write } from './writer.js';
import { isQueued } from './sync.js';

const WORD = { pending: 'Not done', done: 'Done', skipped: 'Skipped' };
const NEXT = { pending: 'done', done: 'skipped', skipped: 'pending' };

let openActForm = () => {};
export function setActFormOpener(fn) {
    openActForm = fn;
}

const tz = () => S.trip?.timezone || 'Asia/Tokyo';

export function todayDay() {
    const { date } = zonedNow(tz());
    return S.days.find((d) => d.date === date) || null;
}

export function defaultDayId() {
    const today = todayDay();
    if (today) {
        return today.id;
    }
    if (!S.days.length) {
        return null;
    }
    const { date } = zonedNow(tz());
    return date < S.days[0].date ? S.days[0].id : S.days[S.days.length - 1].id;
}

function nowNext() {
    const day = todayDay();
    if (!day) {
        return { day: null, now: null, next: null };
    }
    const { mins } = zonedNow(tz());
    let now = null;
    let next = null;
    for (const act of dayActs(day.id)) {
        if (toMins(act.time) <= mins) {
            now = act;
        } else {
            next = act;
            break;
        }
    }
    return { day, now, next };
}

export function renderHeader() {
    const t = S.trip;
    $('tripTitle').textContent = t.name;
    $('tripSub').textContent = `${fmtDate(t.start_date)} – ${fmtDate(t.end_date)} · ${S.days.length} day${S.days.length === 1 ? '' : 's'}`;
    const acts = allActs();
    const finished = acts.filter((a) => a.status !== 'pending').length;
    const pct = acts.length ? Math.round((finished / acts.length) * 100) : 0;
    $('tripBar').style.width = `${pct}%`;
    $('tripProgress').setAttribute('aria-valuenow', String(pct));
    $('tripProgress').setAttribute('aria-valuetext', `${finished} of ${acts.length} stops done or skipped`);
}

function renderDays() {
    $('days').innerHTML = S.days.map((d) => {
        const acts = dayActs(d.id);
        const finished = acts.filter((a) => a.status !== 'pending').length;
        const pct = acts.length ? Math.round((finished / acts.length) * 100) : 0;
        const current = UI.dayId === d.id;
        const where = d.city ? `, ${d.city}` : '';
        return `<button type="button" class="daybtn" data-day="${d.id}" aria-current="${current}" aria-label="Day ${d.day_number}, ${weekday(d.date, true)} ${fmtDate(d.date)}${esc(where)}" style="--c:${cityColor(d.city)}">
            <span class="badge"><small>${esc(cityLetter(d.city))}</small><strong class="tnum">${d.day_number}</strong></span>
            <span class="dt tnum">${fmtDate(d.date)}</span>
            <span class="ring"><b style="width:${pct}%"></b></span></button>`;
    }).join('');
}

function renderBanner() {
    const { date } = zonedNow(tz());
    const first = S.days[0];
    let html = '';
    if (first && date < first.date) {
        const diff = daysBetween(date, first.date);
        const firstStop = dayActs(first.id)[0];
        const stopText = firstStop ? ` First stop: ${esc(firstStop.name)} at ${esc(firstStop.time)}, ${weekday(first.date, true)} ${fmtDate(first.date)}.` : '';
        html = `<div class="nownext plain"><span class="v"><b>${diff} day${diff === 1 ? '' : 's'} to go.</b>${stopText}</span></div>`;
    } else {
        const nn = nowNext();
        if (nn.day) {
            const rows = [];
            if (nn.now) {
                rows.push(`<span class="k now">Now</span><span class="v now"><span class="tnum">${esc(nn.now.time)}</span>${esc(nn.now.name)}</span>`);
            }
            if (nn.next) {
                rows.push(`<span class="k">Next</span><span class="v"><span class="tnum">${esc(nn.next.time)}</span>${esc(nn.next.name)}</span>`);
            }
            html = rows.length
                ? `<div class="nownext" aria-label="Today">${rows.join('')}</div>`
                : '<div class="nownext plain"><span class="v">Nothing scheduled right now.</span></div>';
        }
    }
    $('banner').innerHTML = html;
}

function stopHtml(a, day, nn) {
    const status = a.status || 'pending';
    const isToday = nn.day && nn.day.id === day.id;
    const isNow = isToday && nn.now?.id === a.id && status === 'pending';
    const isNext = isToday && nn.next?.id === a.id;
    const open = UI.open.has(a.id);
    const note = S.notes.get(a.id) || '';

    const tags = [];
    if (a.meal) tags.push(`<span class="tag">${esc(a.meal)}</span>`);
    if (a.is_halal) tags.push('<span class="tag halal">Halal</span>');
    if (a.ticket) tags.push(`<span class="tag ticket">${esc(a.ticket)}</span>`);
    if (note.trim()) tags.push('<span class="tag note">Has note</span>');
    if (isQueued(`act:${a.id}`) || isQueued(`status:${a.id}`) || isQueued(`note:${a.id}`)) tags.push('<span class="tag sync">Waiting to sync</span>');

    let detail = '';
    if (open) {
        const places = (a.places || []).map((p) => `<a href="${esc(mapUrl(p, day.city))}" target="_blank" rel="noopener">${ICON.pin}<span>${esc(p)}</span></a>`).join('');
        const segs = ['pending', 'done', 'skipped'].map((k) => `<button type="button" data-act="set" data-v="${k}" aria-pressed="${status === k}">${WORD[k]}</button>`).join('');
        detail = `<div class="detail rise" id="d-${a.id}">
            ${a.transport ? `<div><span class="k">Getting there</span><p>${esc(a.transport)}</p></div>` : ''}
            ${a.notes ? `<div><span class="k">Notes</span><p>${esc(a.notes)}</p></div>` : ''}
            ${places ? `<div><span class="k">Places</span><div class="places">${places}</div></div>` : ''}
            <fieldset class="plain"><legend class="k">Status</legend><div class="seg">${segs}</div></fieldset>
            <div><label class="k" for="note-${a.id}">My note</label><textarea id="note-${a.id}" class="note-input" data-note="${a.id}" rows="2" maxlength="1000" placeholder="Only you can see this">${esc(note)}</textarea></div>
            ${canEdit() ? '<button type="button" class="text-link" data-act="edit">Edit or delete this stop</button>' : ''}
        </div>`;
    }

    const label = isNow ? '<span class="now-label">Now</span>' : (isNext ? '<span class="now-label next">Next</span>' : '');
    return `<li class="stop${isNow ? ' is-now' : ''}" data-s="${status}" data-id="${a.id}">
        <div class="line" aria-hidden="true"></div>
        <div class="time tnum">${esc(a.time)}</div>
        <div class="track"><button type="button" class="dotbtn" data-act="cycle" aria-label="${esc(`${a.name}: ${WORD[status]}. Tap to change.`)}"><span class="dot${isNow ? ' pulse' : ''}">${ICON.tick}<span class="dash"></span></span></button></div>
        <div class="card">
            <button type="button" class="head" data-act="toggle" aria-expanded="${open}"${open ? ` aria-controls="d-${a.id}"` : ''}>
                ${label}<span class="name">${esc(a.name)}</span>${tags.length ? `<span class="tags">${tags.join('')}</span>` : ''}
            </button>${detail}
        </div></li>`;
}

function renderRail() {
    const day = dayById(UI.dayId);
    if (!day) {
        return;
    }
    const acts = dayActs(day.id);
    const nn = nowNext();
    const editable = canEdit();

    $('dayTitle').textContent = day.city ? `Day ${day.day_number} · ${day.city}` : `Day ${day.day_number}`;
    const meta = [`${weekday(day.date)} ${fmtDate(day.date)}`];
    if (day.title) meta.push(day.title);
    meta.push(acts.length ? `${acts.length} stop${acts.length === 1 ? '' : 's'}` : 'No stops yet');
    $('dayMeta').textContent = meta.join(' · ');

    const today = todayDay();
    $('todayBtn').classList.toggle('hidden', !today || today.id === day.id);
    $('addAct').classList.toggle('hidden', !editable);

    const rail = $('rail');
    rail.style.setProperty('--c', cityColor(day.city));
    rail.innerHTML = acts.map((a) => stopHtml(a, day, nn)).join('');

    const empty = acts.length === 0;
    $('emptyDay').classList.toggle('hidden', !empty);
    $('emptyDay').style.setProperty('--c', cityColor(day.city));
    $('emptyAdd').classList.toggle('hidden', !editable);
    $('emptyText').textContent = editable
        ? `Add a stop, or pick one from your${day.city ? ` ${day.city}` : ''} ideas.`
        : 'The organisers haven’t added stops for this day yet.';
    $('addBottom').classList.toggle('hidden', empty || !editable);
}

export function renderPlan() {
    renderDays();
    renderBanner();
    renderRail();
}

export function scrollDayIntoView() {
    const strip = $('days');
    const button = strip.querySelector(`.daybtn[data-day="${UI.dayId}"]`);
    if (button) {
        strip.scrollLeft = button.offsetLeft - strip.offsetLeft - (strip.clientWidth - button.offsetWidth) / 2;
    }
    window.dispatchEvent(new Event('days:scrolled'));
}

function setStatus(id, status, rerender) {
    const act = S.acts.get(id);
    if (!act || act.status === status) {
        return;
    }
    const before = act.status;
    act.status = status;
    rerender();
    write({
        key: `status:${id}`,
        method: 'PATCH',
        path: `/activities/${id}/status`,
        body: { status },
        revert: () => { act.status = before; rerender(); },
    });
}

const saveNote = debounceKeyed((id, note) => {
    write({ key: `note:${id}`, method: 'PUT', path: `/activities/${id}/note`, body: { note } });
}, 700);

export function bindItinerary(rerender) {
    $('days').addEventListener('click', (e) => {
        const b = e.target.closest('.daybtn');
        if (!b) return;
        UI.dayId = Number(b.dataset.day);
        renderPlan();
        scrollDayIntoView();
    });

    $('todayBtn').addEventListener('click', () => {
        UI.dayId = defaultDayId();
        renderPlan();
        scrollDayIntoView();
        document.querySelector('.stop.is-now')?.scrollIntoView({ block: 'center' });
    });

    const add = () => openActForm(null);
    $('addAct').addEventListener('click', add);
    $('addActBottom').addEventListener('click', add);
    $('emptyAdd').addEventListener('click', add);

    $('rail').addEventListener('click', (e) => {
        const el = e.target.closest('[data-act]');
        if (!el) return;
        const id = el.closest('.stop').dataset.id;
        const act = el.dataset.act;
        if (act === 'toggle') {
            UI.open.has(id) ? UI.open.delete(id) : UI.open.add(id);
            renderRail();
        } else if (act === 'cycle') {
            setStatus(id, NEXT[S.acts.get(id)?.status || 'pending'], rerender);
        } else if (act === 'set') {
            setStatus(id, el.dataset.v, rerender);
        } else if (act === 'edit') {
            openActForm(id);
        }
    });

    $('rail').addEventListener('input', (e) => {
        const id = e.target.dataset.note;
        if (!id) return;
        const note = e.target.value.slice(0, 1000);
        S.notes.set(id, note);
        saveNote(id, note);
    });
}
