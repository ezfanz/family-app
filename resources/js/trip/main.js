/* Trip app: boots the itinerary, shopping and ideas screens on /trips/{id}. */

import { api, setApiBase } from './api.js';
import { S, UI, canEdit, loadFull, mergeChanges } from './store.js';
import { $, ICON, toast } from './util.js';
import { renderHeader, renderPlan, bindItinerary, setActFormOpener, setAirportOpener, defaultDayId, scrollDayIntoView } from './itinerary.js';
import { openAirport } from './departure.js';
import { renderShop, bindShopping, setItemFormOpener, resetShopperToMe } from './shopping.js';
import { renderIdeas, bindIdeas, setIdeaHandlers } from './ideas.js';
import { bindSheets, openActForm, openItemForm, openIdeaForm, planIdea } from './sheets.js';
import { bindDayStrip } from './daystrip.js';
import { showLoader } from './loader.js';
import { bindImport } from './importer.js';
import { bindReceipts, uploadWaitingPhotos } from './receipts.js';
import { bindCamera } from './camera.js';
import { resetOverview } from './overview.js';
import { loadFx } from './fx.js';
import { setWriterHooks } from './writer.js';
import { net, initSync, loadCache, saveCache, clearTripStorage, flushQueue, queueLength, startPolling } from './sync.js';

const VIEWS = { plan: 'v-plan', shopping: 'v-shopping', ideas: 'v-ideas' };

export function setSync(kind, text) {
    const el = $('sync');
    el.className = `sync ${kind}`;
    el.querySelector('.txt').textContent = text;
    el.querySelector('.ico')?.remove();
    const dot = el.querySelector('.dot');
    const icon = kind === 'view' ? ICON.eye : (kind === 'off' ? ICON.offline : '');
    dot.classList.toggle('hidden', icon !== '');
    if (icon) dot.insertAdjacentHTML('afterend', `<span class="ico" aria-hidden="true" style="display:flex">${icon}</span>`);
}

let saving = false;
let savingTimer = null;

/** Show "Saving…" in the header while a change is on its way, unless it lands within 250ms. */
function onBusy(count) {
    clearTimeout(savingTimer);
    if (count > 0) {
        savingTimer = setTimeout(() => {
            saving = true;
            syncStatus();
        }, 250);
    } else if (saving) {
        saving = false;
        syncStatus();
    }
}

export function syncStatus() {
    const offline = !navigator.onLine || !net.online;
    $('offline').classList.toggle('hidden', !offline);
    const queued = queueLength();
    if (offline) {
        setSync('off', 'Offline, showing saved');
    } else if (queued) {
        setSync('warn', `Saving ${queued} change${queued === 1 ? '' : 's'}…`);
    } else if (saving) {
        setSync('saving', 'Saving…');
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
    if (UI.view === 'shopping' && previous !== 'shopping') {
        resetShopperToMe();
        UI.shopTab = 'overview';
        resetOverview();
        loadFx();
    }
    Object.entries(VIEWS).forEach(([view, id]) => $(id).classList.toggle('hidden', view !== UI.view));
    document.querySelectorAll('.tabs a').forEach((a) => a.setAttribute('aria-current', a.dataset.v === UI.view ? 'page' : 'false'));
    render();
    if (previous !== UI.view) window.scrollTo(0, 0);
    if (UI.view === 'plan') scrollDayIntoView();
}

let refreshing = null;

let stopped = false;

/**
 * Send queued writes, then pull changes made by others since the last sync
 * and merge them in. Falls back to a full reload when there is no sync cursor
 * yet, or when the server rejected queued writes and local state may be wrong.
 *
 * @returns {Promise<boolean>} true when the server answered
 */
export function refreshTrip() {
    if (refreshing) return refreshing;
    if (stopped) return Promise.resolve(false);
    const tripId = $('app').dataset.tripId;
    refreshing = (async () => {
        try {
            const flushed = await flushQueue();
            if (!flushed.ok) {
                throw new Error('offline');
            }
            if (S.serverTime && !flushed.rejected) {
                const roleChanged = mergeChanges(await api('GET', `/trips/${tripId}/changes?since=${encodeURIComponent(S.serverTime)}`));
                // A new role can see more (or less): fetch everything once.
                if (roleChanged) loadFull(await api('GET', `/trips/${tripId}`));
            } else {
                loadFull(await api('GET', `/trips/${tripId}`));
            }
            net.online = true;
            saveCache();
            syncStatus();
            safeRender();
            uploadWaitingPhotos();
            return true;
        } catch (error) {
            if (error.status === 404) {
                stopped = true;
                clearTripStorage();
                showLoadError('You’re not in this trip any more.');
                return false;
            }
            if (!error.status || error.retryable) {
                net.online = false;
            }
            syncStatus();
            return false;
        } finally {
            refreshing = null;
        }
    })();
    return refreshing;
}

/** Every [data-refresh] button gets the latest trip; on Shopping it also gets today's rate. */
function bindRefreshButton() {
    document.querySelectorAll('[data-refresh]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.getAttribute('aria-busy') === 'true') return;
            button.setAttribute('aria-busy', 'true');
            const loader = showLoader('Loading');
            const [ok] = await Promise.all([
                refreshTrip(),
                button.dataset.refresh === 'shopping' ? loadFx(true).catch(() => null) : null,
            ]);
            await loader.finish();
            button.removeAttribute('aria-busy');
            toast(ok ? 'Up to date.' : 'Couldn’t reach the server. Showing what you have.');
        });
    });
}

function showLoadError(message) {
    $('dayTitle').textContent = 'Couldn’t load the trip';
    $('dayMeta').textContent = message;
    setSync('warn', 'Not connected');
}

export async function bootTrip(root) {
    setApiBase(root.dataset.apiBase);
    S.me = Number(root.dataset.userId);

    setActFormOpener(openActForm);
    setAirportOpener(openAirport);
    setItemFormOpener(openItemForm);
    setIdeaHandlers({ openIdeaForm, planIdea, rerender: render });
    bindItinerary(render);
    bindShopping();
    bindReceipts();
    bindCamera();
    bindIdeas();
    bindSheets(render);
    bindDayStrip();
    bindRefreshButton();
    bindImport({ base: root.dataset.apiBase, onImported: () => refreshTrip() });

    document.addEventListener('focusout', () => {
        if (pendingRender) {
            pendingRender = false;
            setTimeout(render, 0);
        }
    });
    window.addEventListener('hashchange', route);
    setInterval(() => { if (UI.view === 'plan') safeRender(); }, 60000);

    initSync({
        tripId: root.dataset.tripId,
        userId: root.dataset.userId,
        onStatus: () => {
            syncStatus();
            safeRender();
        },
    });

    setWriterHooks({ onBusy });

    // Show the saved copy straight away, then catch up with the server.
    if (loadCache()) {
        UI.dayId = defaultDayId();
        route();
        syncStatus();
        await refreshTrip();
        if (!S.days.some((d) => d.id === UI.dayId)) {
            UI.dayId = defaultDayId();
            safeRender();
        }
    } else {
        const loader = showLoader('Loading');
        try {
            loadFull(await api('GET', `/trips/${root.dataset.tripId}`));
            net.online = true;
            saveCache();
            await loader.finish();
        } catch (error) {
            await loader.finish();
            if (error.status !== 0) {
                showLoadError(error.status === 404 ? 'You’re not in this trip any more.' : 'Something went wrong. Try again.');
                return;
            }
            net.online = false;
            showLoadError('You’re offline and this trip isn’t saved on this device yet.');
            startPolling(firstLoadRetry);
            return;
        }
        UI.dayId = defaultDayId();
        route();
        syncStatus();
    }

    startPolling(refreshTrip);
    if (root.dataset.flash) toast(root.dataset.flash);
}

/** Before anything is cached, keep trying to load when the connection returns. */
async function firstLoadRetry() {
    if (S.trip) {
        return refreshTrip();
    }
    try {
        loadFull(await api('GET', `/trips/${$('app').dataset.tripId}`));
        net.online = true;
        saveCache();
        UI.dayId = defaultDayId();
        route();
        syncStatus();
        return true;
    } catch {
        return false;
    }
}
