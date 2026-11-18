/*
 * The bell: your @mentions on this trip. The unread count comes with every
 * sync; the list loads when the bell opens. Tapping one opens that stop on
 * its day, with the comment in view.
 */

import { S, UI } from './store.js';
import { $, esc, fmtDate } from './util.js';
import { api } from './api.js';
import { avatar } from './shopping.js';

let onGo = () => {};
let items = [];

const dlg = () => $('bellDlg');

/** Badge on the bell: hidden at 0, "9+" above nine. */
export function renderBell() {
    const count = Number(S.unread) || 0;
    $('bellBadge').hidden = count === 0;
    $('bellBadge').textContent = count > 9 ? '9+' : String(count);
    $('bellBtn').classList.toggle('has-unread', count > 0);
    $('bellBtn').setAttribute('aria-label', count ? `Notifications, ${count} unread` : 'Notifications');
}

function ago(iso) {
    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (!(mins >= 1)) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    if (mins < 60 * 24) return `${Math.round(mins / 60)}h ago`;
    if (mins < 60 * 24 * 7) return `${Math.round(mins / (60 * 24))}d ago`;
    return fmtDate(String(iso).slice(0, 10));
}

function renderList(state = 'ready') {
    const list = $('bellList');
    $('bellReadAll').hidden = !items.some((n) => !n.read);
    if (state === 'loading') {
        list.innerHTML = '<li class="bell-empty">Loading…</li>';
        return;
    }
    if (state === 'offline') {
        list.innerHTML = '<li class="bell-empty">Notifications need internet. Try again when you’re online.</li>';
        return;
    }
    if (!items.length) {
        list.innerHTML = '<li class="bell-empty"><strong>No notifications yet</strong>When someone @mentions you in a comment, it shows up here.</li>';
        return;
    }
    list.innerHTML = items.map((n) => {
        const actor = { user_id: n.actor_id, name: n.actor_name };
        const where = [`Day ${n.day_number}`, n.city, n.time].filter(Boolean).join(' · ');
        return `<li><button type="button" class="bell-item${n.read ? '' : ' unread'}" data-note="${esc(n.id)}">
            ${avatar(actor, 'cm-ava')}
            <span class="bell-text">
                <span class="bell-line"><strong>${esc(n.actor_name)}</strong> mentioned you on <strong>${esc(n.stop_name)}</strong></span>
                <span class="bell-where">${esc(where)} · ${esc(ago(n.created_at))}</span>
                <span class="bell-excerpt">“${esc(n.excerpt)}”</span>
            </span>
            ${n.read ? '' : '<span class="bell-dot" aria-label="Unread"></span>'}
        </button></li>`;
    }).join('');
}

async function openBell() {
    dlg().showModal();
    renderList(items.length ? 'ready' : 'loading');
    try {
        const res = await api('GET', `/trips/${S.trip.id}/notifications`);
        items = res?.data || [];
        S.unread = Number(res?.unread_count) || 0;
        renderBell();
        renderList();
    } catch {
        renderList(items.length ? 'ready' : 'offline');
    }
}

function markRead(ids) {
    items = items.map((n) => (ids === null || ids.includes(n.id) ? { ...n, read: true } : n));
    S.unread = items.filter((n) => !n.read).length;
    renderBell();
    renderList();
    api('POST', `/trips/${S.trip.id}/notifications/read`, ids === null ? {} : { ids })
        .then((res) => { S.unread = Number(res?.unread_count) || 0; renderBell(); })
        .catch(() => {});
}

/** Open the stop the comment is on, on its day, and bring the thread into view. */
function goTo(n) {
    UI.dayId = n.day_id;
    UI.open.add(n.activity_id);
    const switching = window.location.hash !== '#plan';
    if (switching) window.location.hash = '#plan';
    onGo();
    // Changing the hash re-renders a moment later; scroll once the stop is on screen.
    setTimeout(() => {
        const stop = document.querySelector(`#rail .stop[data-id="${CSS.escape(String(n.activity_id))}"]`);
        const target = stop?.querySelector(`[data-comment="${CSS.escape(String(n.comment_id))}"]`) || stop?.querySelector('.cm-thread') || stop;
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.classList.add('flash-in');
        setTimeout(() => target?.classList.remove('flash-in'), 1800);
    }, switching ? 350 : 60);
}

/**
 * @param {{ onGo: () => void }} hooks re-render after jumping to a stop
 */
export function bindBell(hooks) {
    onGo = hooks.onGo;
    $('bellBtn').addEventListener('click', openBell);
    $('bellReadAll').addEventListener('click', () => markRead(null));
    $('bellList').addEventListener('click', (e) => {
        const button = e.target.closest('[data-note]');
        if (!button) return;
        const n = items.find((item) => item.id === button.dataset.note);
        if (!n) return;
        if (!n.read) markRead([n.id]);
        dlg().close();
        goTo(n);
    });
}
