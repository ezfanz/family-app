/* Trip app: boots the itinerary, shopping and ideas screens on /trips/{id}. */

import { api, setApiBase } from './api.js';
import { S, UI, canEdit, loadFull, mergeChanges } from './store.js';
import { $, ICON, toast } from './util.js';
import { renderHeader, renderPlan, bindItinerary, setActFormOpener, defaultDayId, scrollDayIntoView } from './itinerary.js';
import { renderShop, bindShopping, setItemFormOpener } from './shopping.js';
import { renderIdeas, bindIdeas, setIdeaHandlers } from './ideas.js';
import { bindSheets, openActForm, openItemForm, openIdeaForm, planIdea } from './sheets.js';
import { bindDayStrip } from './daystrip.js';
import { showLoader } from './loader.js';

const VIEWS = { plan: 'v-plan', shopping: 'v-shopping', ideas: 'v-ideas' };

export function setSync(kind, text) {
    const el = $('sync');
    el.className = `sync ${kind}`;
    el.querySelector('.txt').textContent = text;
    el.querySelector('.ico')?.remove();
    const dot = el.querySelector('.dot');
    const icon = kind === 'view' ? ICON.eye : (kind === 'offline' ? ICON.offline : '');
    dot.classList.toggle('hidden', icon !== '');
    if (icon) dot.insertAdjacentHTML('afterend', `<span class="ico" aria-hidden="true" style="display:flex">${icon}</span>`);
}

export function syncStatus() {
    if (!navigator.onLine) {
        setSync('offline', 'Offline, showing saved');
    } else if (!canEdit()) {
        setSync('view', 'View only');
    } else {
        setSync('on', 'Shared with group');
    }
}

export function render() {
    if (!S.trip) return;
    renderHeader();
    if (UI.view === 'plan') renderPlan();
    else if (UI.view === 'shopping') renderShop();
    else renderIdeas();
}

let pendingRender = false;

/** Re-render unless the user is typing; then wait until they leave the field. */
export function safeRender() {
    const active = document.activeElement;
    if (active && (active.tagName === 'TEXTAREA' || active.tagName === 'INPUT' || active.tagName === 'SELECT')) {
        pendingRender = true;
        return;
    }
    render();
}

function route() {
    const hash = window.location.hash.replace('#', '');
    const previous = UI.view;
    UI.view = VIEWS[hash] ? hash : 'plan';
    Object.entries(VIEWS).forEach(([view, id]) => $(id).classList.toggle('hidden', view !== UI.view));
    document.querySelectorAll('.tabs a').forEach((a) => a.setAttribute('aria-current', a.dataset.v === UI.view ? 'page' : 'false'));
    render();
    if (previous !== UI.view) window.scrollTo(0, 0);
    if (UI.view === 'plan') scrollDayIntoView();
}

let refreshing = null;

/**
 * Pull changes made by others since the last sync and merge them in.
 * Falls back to a full reload when there is no sync cursor yet.
 *
 * @returns {Promise<boolean>} true when the server answered
 */
export function refreshTrip() {
    if (refreshing) return refreshing;
    const tripId = $('app').dataset.tripId;
    refreshing = (async () => {
        try {
            if (S.serverTime) {
                mergeChanges(await api('GET', `/trips/${tripId}/changes?since=${encodeURIComponent(S.serverTime)}`));
            } else {
                loadFull(await api('GET', `/trips/${tripId}`));
            }
            syncStatus();
            safeRender();
            return true;
        } catch {
            setSync('offline', 'Offline, showing saved');
            return false;
        } finally {
            refreshing = null;
        }
    })();
    return refreshing;
}

function bindRefreshButton() {
    const button = $('refreshBtn');
    button.addEventListener('click', async () => {
        button.setAttribute('aria-busy', 'true');
        const loader = showLoader('Loading');
        const ok = await refreshTrip();
        await loader.finish();
        button.removeAttribute('aria-busy');
        toast(ok ? 'Up to date.' : 'Couldn’t reach the server. Showing what you have.');
    });
}

function showLoadError(message) {
    $('dayTitle').textContent = 'Couldn’t load the trip';
    $('dayMeta').textContent = message;
    setSync('warn', 'Not connected');
}

export async function bootTrip(root, { beforeLoad, afterLoad } = {}) {
    setApiBase(root.dataset.apiBase);

    setActFormOpener(openActForm);
    setItemFormOpener(openItemForm);
    setIdeaHandlers({ openIdeaForm, planIdea });
    bindItinerary(render);
    bindShopping();
    bindIdeas();
    bindSheets(render);
    bindDayStrip();
    bindRefreshButton();

    document.addEventListener('focusout', () => {
        if (pendingRender) {
            pendingRender = false;
            setTimeout(render, 0);
        }
    });
    window.addEventListener('hashchange', route);
    setInterval(() => { if (UI.view === 'plan') safeRender(); }, 60000);

    const restored = beforeLoad?.() ?? false;
    if (restored) {
        UI.dayId = defaultDayId();
        route();
    }

    const loader = restored ? null : showLoader('Loading');
    try {
        loadFull(await api('GET', `/trips/${root.dataset.tripId}`));
        await loader?.finish();
    } catch (error) {
        await loader?.finish();
        if (!restored) {
            showLoadError(error.status === 404 ? 'You’re not in this trip any more.' : 'Check your connection and try again.');
        }
        afterLoad?.(false, error);
        return;
    }

    if (!restored || !S.days.some((d) => d.id === UI.dayId)) {
        UI.dayId = defaultDayId();
    }
    restored ? safeRender() : route();
    syncStatus();
    if (root.dataset.flash) toast(root.dataset.flash);
    afterLoad?.(true);
}
