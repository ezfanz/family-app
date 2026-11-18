/* Itinerary: header progress, day strip, now/next banner and the rail timeline. */

import { S, UI, canEdit, isOwner, dayActs, allActs, dayById, commentsOn } from './store.js';
import { avatar } from './shopping.js';
import { $, esc, fmtDate, weekday, zonedNow, toMins, daysBetween, cityColor, cityLetter, mapUrl, ICON, debounceKeyed, uuid, toast, confirmAction } from './util.js';
import { write } from './writer.js';
import { isQueued } from './sync.js';

const WORD = { pending: 'Not done', done: 'Done', skipped: 'Skipped' };
const NEXT = { pending: 'done', done: 'skipped', skipped: 'pending' };

let openActForm = () => {};
export function setActFormOpener(fn) {
    openActForm = fn;
}

let openAirport = () => {};
/** The tax refund stop on the last day opens the airport checklist. */
export function setAirportOpener(fn) {
    openAirport = fn;
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

/* ---------- City filter ---------- */

const NO_CITY = '__none__';
const cityKey = (day) => day.city || NO_CITY;

/** Cities in trip order, with how many days each has. */
function tripCities() {
    const cities = new Map();
    S.days.forEach((d) => cities.set(cityKey(d), (cities.get(cityKey(d)) || 0) + 1));
    return [...cities].map(([key, count]) => ({ key, count }));
}

function visibleDays() {
    return UI.city === 'all' ? S.days : S.days.filter((d) => cityKey(d) === UI.city);
}

function renderCityChips() {
    const cities = tripCities();
    const box = $('cityChips');
    // A filter only helps when the trip covers more than one place.
    box.classList.toggle('hidden', cities.length < 2);
    if (cities.length < 2) {
        UI.city = 'all';
        return;
    }
    if (UI.city !== 'all' && !cities.some((c) => c.key === UI.city)) UI.city = 'all';
    const chip = (key, label, count, colour) => `<button type="button" class="chip city-chip" data-city="${esc(key)}" aria-pressed="${UI.city === key}"${colour ? ` style="--c:${colour}"` : ''}>
        ${colour ? '<span class="city-dot" aria-hidden="true"></span>' : ''}${esc(label)}<span class="chip-count tnum">${count}</span></button>`;
    box.innerHTML = chip('all', 'All days', S.days.length, '')
        + cities.map((c) => chip(c.key, c.key === NO_CITY ? 'No city' : c.key, c.count, cityColor(c.key === NO_CITY ? null : c.key))).join('');
}

/** Pick a city: keep the open day if it is there, else today's day there, else its first day. */
function selectCity(key) {
    UI.city = key;
    const days = visibleDays();
    if (!days.some((d) => d.id === UI.dayId)) {
        const today = todayDay();
        UI.dayId = (today && days.some((d) => d.id === today.id) ? today : days[0])?.id ?? UI.dayId;
    }
}

function renderDays() {
    renderCityChips();
    $('days').innerHTML = visibleDays().map((d) => {
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

/** Wide photo at the top of an opened stop, with its credit (required for Commons photos). */
function photoHtml(a) {
    if (!a.photo) return '';
    const { url, thumb_url: thumb, credit, link } = a.photo;
    const srcset = thumb && thumb !== url ? ` srcset="${esc(thumb)} 330w, ${esc(url)} 960w" sizes="(min-width: 720px) 620px, calc(100vw - 120px)"` : '';
    const caption = credit
        ? `<figcaption>${link ? `<a href="${esc(link)}" target="_blank" rel="noopener">${esc(credit)}</a>` : esc(credit)}</figcaption>`
        : '';
    return `<figure class="stop-photo"><img src="${esc(url)}"${srcset} alt="${esc(`Photo of ${a.name}`)}" loading="lazy" decoding="async" referrerpolicy="no-referrer">${caption}</figure>`;
}

/** "just now", "5m ago", "3h ago", "2d ago", else the date. */
function ago(iso) {
    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (!(mins >= 0) || mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    if (mins < 60 * 24) return `${Math.round(mins / 60)}h ago`;
    if (mins < 60 * 24 * 7) return `${Math.round(mins / (60 * 24))}d ago`;
    return fmtDate(String(iso).slice(0, 10));
}

const ICON_COMMENT = '<svg class="i" width="13" height="13" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/></svg>';

const memberOf = (userId) => S.members.find((m) => Number(m.user_id) === Number(userId))
    || S.shoppers.find((p) => Number(p.user_id) === Number(userId));

/** Comment text, escaped, with @Name of trip members highlighted. */
function bodyHtml(body) {
    let html = esc(body);
    const names = S.members.map((m) => m.name).filter(Boolean).sort((a, b) => b.length - a.length);
    for (const name of names) {
        const token = `@${esc(name)}`;
        html = html.split(token).join(`<span class="mention">${token}</span>`);
    }
    return html;
}

/**
 * Comments on a stop: a thread people post to and @mention each other in.
 * Owners read everyone's; others read their own and ones that mention them.
 */
function commentsHtml(a) {
    const list = commentsOn(a.id).map((c) => {
        const person = memberOf(c.user_id) || { user_id: c.user_id, name: 'Member' };
        const mine = Number(c.user_id) === Number(S.me);
        const waiting = isQueued(`comment:${c.id}`);
        return `<li class="cm${mine ? ' cm-own' : ''}" data-comment="${esc(c.id)}">
            ${avatar(person, 'cm-ava')}
            <div class="cm-body">
                <div class="cm-head"><strong>${esc(mine ? 'You' : person.name)}</strong><time datetime="${esc(c.created_at || '')}">${waiting ? 'Sending…' : esc(ago(c.created_at))}</time>${mine ? '<button type="button" class="cm-del" data-act="comment-delete">Delete</button>' : ''}</div>
                <p>${bodyHtml(c.body)}</p>
            </div>
        </li>`;
    }).join('');
    const me = memberOf(S.me) || { user_id: S.me, name: 'You' };
    const draft = UI.commentDrafts?.[a.id] || '';
    const hint = isOwner() ? 'Type @ to mention someone' : 'Type @ to mention · owners can read';
    return `<section class="cm-thread" aria-label="Comments">
        <span class="k">Comments${list ? ` <span class="cm-count">${commentsOn(a.id).length}</span>` : ''}</span>
        ${list ? `<ul class="cm-list">${list}</ul>` : '<p class="cm-empty">No comments yet. Start the discussion.</p>'}
        <div class="cm cm-compose" data-compose="${a.id}">
            ${avatar(me, 'cm-ava')}
            <div class="cm-body">
                <label class="sr-only" for="cm-${a.id}">Add a comment</label>
                <textarea id="cm-${a.id}" class="cm-input" data-comment-input="${a.id}" rows="1" maxlength="1000" placeholder="Add a comment…">${esc(draft)}</textarea>
                <ul class="mention-list" role="listbox" aria-label="Mention someone" hidden></ul>
                <div class="cm-compose-foot"><span>${esc(hint)}</span><button type="button" class="cm-send" data-act="comment-send"${draft.trim() ? '' : ' disabled'}><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l16-8-6 16-2-7z"/></svg>Send</button></div>
            </div>
        </div>
    </section>`;
}

function stopHtml(a, day, nn) {
    const status = a.status || 'pending';
    const isToday = nn.day && nn.day.id === day.id;
    const isNow = isToday && nn.now?.id === a.id && status === 'pending';
    const isNext = isToday && nn.next?.id === a.id;
    const open = UI.open.has(a.id);

    const tags = [];
    if (a.meal) tags.push(`<span class="tag">${esc(a.meal)}</span>`);
    if (a.is_halal) tags.push('<span class="tag halal">Halal</span>');
    if (a.ticket) tags.push(`<span class="tag ticket">${esc(a.ticket)}</span>`);
    const commentCount = commentsOn(a.id).length;
    if (commentCount) tags.push(`<span class="tag note">${ICON_COMMENT}${commentCount}<span class="sr-only"> comment${commentCount === 1 ? '' : 's'}</span></span>`);
    if (a.system_key === 'tax_refund') tags.push('<span class="tag ticket">Tax refund</span>');
    if (isQueued(`act:${a.id}`) || isQueued(`status:${a.id}`) || isQueued(`note:${a.id}`)) tags.push('<span class="tag sync">Waiting to sync</span>');

    let detail = '';
    if (open) {
        const places = (a.places || []).map((p) => `<a href="${esc(mapUrl(p, day.city))}" target="_blank" rel="noopener">${ICON.pin}<span>${esc(p)}</span></a>`).join('');
        const segs = ['pending', 'done', 'skipped'].map((k) => `<button type="button" data-act="set" data-v="${k}" aria-pressed="${status === k}">${WORD[k]}</button>`).join('');
        detail = `<div class="detail rise" id="d-${a.id}">
            ${photoHtml(a)}
            ${a.transport ? `<div><span class="k">Getting there</span><p>${esc(a.transport)}</p></div>` : ''}
            ${a.notes ? `<div><span class="k">Notes</span><p>${esc(a.notes)}</p></div>` : ''}
            ${places ? `<div><span class="k">Places</span><div class="places">${places}</div></div>` : ''}
            ${a.system_key === 'tax_refund' ? '<button type="button" class="btn primary compact" data-act="airport">Open airport checklist</button>' : ''}
            <fieldset class="plain"><legend class="k">Status</legend><div class="seg">${segs}</div></fieldset>
            ${commentsHtml(a)}
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
                <span class="head-text">${label}<span class="name">${esc(a.name)}</span>${tags.length ? `<span class="tags">${tags.join('')}</span>` : ''}</span>
                ${a.photo && !open ? `<img class="stop-thumb" src="${esc(a.photo.thumb_url)}" alt="" width="56" height="56" loading="lazy" decoding="async" referrerpolicy="no-referrer">` : ''}
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

/* ---------- Comments: send, delete, @mentions ---------- */

function autoGrow(box) {
    box.style.height = 'auto';
    box.style.height = `${Math.min(box.scrollHeight, 180)}px`;
}

/** Members other than you whose "@Name" is in the text (they get a notification). */
function mentionedIn(body) {
    return S.members
        .filter((m) => Number(m.user_id) !== Number(S.me) && m.name && body.includes(`@${m.name}`))
        .map((m) => Number(m.user_id));
}

function showMentions(box) {
    const list = box.parentElement.querySelector('.mention-list');
    const before = box.value.slice(0, box.selectionStart);
    const match = before.match(/(?:^|\s)@([^\s@]{0,24})$/);
    if (!match) {
        list.hidden = true;
        return;
    }
    const query = match[1].toLowerCase();
    const people = S.members
        .filter((m) => Number(m.user_id) !== Number(S.me) && m.name.toLowerCase().includes(query))
        .sort((a, b) => Number(!a.name.toLowerCase().startsWith(query)) - Number(!b.name.toLowerCase().startsWith(query)))
        .slice(0, 5);
    if (!people.length) {
        list.hidden = true;
        return;
    }
    list.innerHTML = people.map((m, i) => `<li role="option" data-mention="${m.user_id}" aria-selected="${i === 0}">${avatar(m, 'cm-ava')}<span>${esc(m.name)}</span></li>`).join('');
    list.hidden = false;
}

function pickMention(box, option) {
    const person = S.members.find((m) => String(m.user_id) === option.dataset.mention);
    if (!person) return;
    const caret = box.selectionStart;
    const before = box.value.slice(0, caret).replace(/@([^\s@]{0,24})$/, `@${person.name} `);
    box.value = before + box.value.slice(caret);
    box.setSelectionRange(before.length, before.length);
    box.focus();
    UI.commentDrafts[box.dataset.commentInput] = box.value;
    box.closest('.cm-compose').querySelector('[data-act="comment-send"]').disabled = false;
    box.parentElement.querySelector('.mention-list').hidden = true;
    autoGrow(box);
}

function sendComment(actId, rerender) {
    const box = document.querySelector(`[data-comment-input="${actId}"]`);
    const body = (box?.value ?? UI.commentDrafts[actId] ?? '').trim().slice(0, 1000);
    if (!body) return;
    const mentionIds = mentionedIn(body);
    const comment = { id: uuid(), activity_id: actId, user_id: S.me, body, mention_ids: mentionIds, created_at: new Date().toISOString() };
    S.comments.set(comment.id, comment);
    UI.commentDrafts[actId] = '';
    write({
        key: `comment:${comment.id}`,
        method: 'POST',
        path: `/activities/${actId}/comments`,
        body: { id: comment.id, body, mention_ids: mentionIds },
        onSaved: (res) => { if (res?.data) S.comments.set(res.data.id, res.data); },
        revert: () => { S.comments.delete(comment.id); UI.commentDrafts[actId] = body; rerender(); },
    });
    if (mentionIds.length) {
        const names = S.members.filter((m) => mentionIds.includes(Number(m.user_id))).map((m) => m.name);
        toast(`Sent. ${names.join(', ')} will get a notification.`);
    }
    rerender();
}

async function deleteComment(commentId, rerender) {
    const comment = S.comments.get(commentId);
    if (!comment) return;
    const sure = await confirmAction({ title: 'Delete this comment?', text: 'It’s removed for everyone in the trip.', ok: 'Delete comment' });
    if (!sure) return;
    S.comments.delete(commentId);
    write({
        key: `comment:${commentId}`,
        method: 'DELETE',
        path: `/comments/${commentId}`,
        revert: () => { S.comments.set(commentId, comment); rerender(); },
    });
    rerender();
    toast('Comment deleted.');
}

export function bindItinerary(rerender) {
    $('days').addEventListener('click', (e) => {
        const b = e.target.closest('.daybtn');
        if (!b) return;
        UI.dayId = Number(b.dataset.day);
        renderPlan();
        scrollDayIntoView();
    });

    $('cityChips').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-city]');
        if (!chip) return;
        selectCity(chip.dataset.city);
        renderPlan();
        scrollDayIntoView();
    });

    $('todayBtn').addEventListener('click', () => {
        UI.dayId = defaultDayId();
        if (UI.city !== 'all' && !visibleDays().some((d) => d.id === UI.dayId)) UI.city = 'all';
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
        } else if (act === 'airport') {
            openAirport();
        } else if (act === 'comment-send') {
            sendComment(id, rerender);
        } else if (act === 'comment-delete') {
            deleteComment(el.closest('[data-comment]').dataset.comment, rerender);
        }
    });

    // @mentions: suggest trip members while typing "@na…".
    $('rail').addEventListener('input', (e) => {
        const box = e.target.closest('[data-comment-input]');
        if (!box) return;
        const actId = box.dataset.commentInput;
        UI.commentDrafts[actId] = box.value;
        box.closest('.cm-compose').querySelector('[data-act="comment-send"]').disabled = !box.value.trim();
        autoGrow(box);
        showMentions(box);
    });
    $('rail').addEventListener('keydown', (e) => {
        const box = e.target.closest('[data-comment-input]');
        if (!box) return;
        const list = box.parentElement.querySelector('.mention-list');
        if (!list.hidden && (e.key === 'Enter' || e.key === 'Tab')) {
            const first = list.querySelector('[aria-selected="true"]') || list.querySelector('[data-mention]');
            if (first) {
                e.preventDefault();
                pickMention(box, first);
                return;
            }
        }
        if (!list.hidden && e.key === 'Escape') {
            list.hidden = true;
            return;
        }
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            sendComment(box.dataset.commentInput, rerender);
        }
    });
    // pointerdown so the box keeps focus (and the phone keyboard stays up).
    $('rail').addEventListener('pointerdown', (e) => {
        const option = e.target.closest('[data-mention]');
        if (!option) return;
        e.preventDefault();
        pickMention(option.closest('.cm-body').querySelector('[data-comment-input]'), option);
    });
    $('rail').addEventListener('focusout', (e) => {
        if (e.target.closest?.('[data-comment-input]')) {
            setTimeout(() => { e.target.parentElement?.querySelector('.mention-list')?.setAttribute('hidden', ''); }, 150);
        }
    });

    // A photo that fails to load (offline and never seen, or removed upstream) just disappears.
    $('rail').addEventListener('error', (e) => {
        if (e.target instanceof HTMLImageElement) {
            (e.target.closest('.stop-photo') || e.target).classList.add('broken');
        }
    }, true);

    $('rail').addEventListener('input', (e) => {
        const id = e.target.dataset.note;
        if (!id) return;
        const note = e.target.value.slice(0, 1000);
        S.notes.set(id, note);
        saveNote(id, note);
    });
}
