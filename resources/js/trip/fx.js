/*
 * Yen ⇄ Ringgit converter on the Shopping page. Today's rate comes from
 * Bank Negara Malaysia via /api/exchange-rates/jpy-myr (cached on the server
 * and on this device, so it still works offline). Owners and editors can
 * copy it into the trip's shopping rate with one tap.
 */

import { api } from './api.js';
import { S, canEdit } from './store.js';
import { $, rm } from './util.js';

const CACHE_KEY = 'tabi:fx:jpy-myr';
const MAX_AGE_MS = 3 * 60 * 60 * 1000;

let live = null; // { rm_per_100_yen, date, session, source, fetched_at }
let lastEdited = 'yen';
let setTripRate = () => {};

const tripRate = () => Number(S.trip?.exchange_rate_rm_per_100_yen) || 0;
const activeRate = () => live?.rm_per_100_yen || tripRate();
const parse = (value) => Number(String(value).replace(/[^0-9.]/g, '')) || 0;

function readCache() {
    try {
        return JSON.parse(localStorage.getItem(CACHE_KEY) || 'null');
    } catch {
        return null;
    }
}

function writeCache(value) {
    try {
        localStorage.setItem(CACHE_KEY, JSON.stringify(value));
    } catch {
        /* storage blocked */
    }
}

function formatDate(iso) {
    const d = new Date(`${iso}T00:00:00`);
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

function convert() {
    const rate = activeRate();
    if (!rate) return;
    if (lastEdited === 'yen') {
        const yen = parse($('fxYen').value);
        $('fxRm').value = yen ? ((yen * rate) / 100).toFixed(2) : '';
    } else {
        const ringgit = parse($('fxRm').value);
        $('fxYen').value = ringgit ? String(Math.round((ringgit * 100) / rate)) : '';
    }
}

export function renderFx() {
    const rate = activeRate();
    if (live) {
        const session = live.session ? `, ${live.session.slice(0, 2)}:${live.session.slice(2)}` : '';
        $('fxSource').textContent = `${live.source} · ${formatDate(live.date)}${session}`;
    } else if (rate) {
        $('fxSource').textContent = 'Using the trip’s rate (today’s rate not loaded)';
    }
    $('fxRateLine').textContent = rate
        ? `1 JPY = ${(rate / 100).toFixed(5)} MYR · ${rm(rate, 4)} per ¥100`
        : '';
    $('fxSummary').textContent = rate ? `${rm(rate, 4)} per ¥100` : 'Today’s rate';

    // Start with ¥1,000 so the converter never opens empty.
    const yenInput = $('fxYen');
    if (!yenInput.value && !$('fxRm').value && document.activeElement !== yenInput) {
        yenInput.value = '1000';
        lastEdited = 'yen';
    }

    const compare = $('fxCompare');
    const trip = tripRate();
    const differs = live && Math.abs(trip - live.rm_per_100_yen) >= 0.005;
    compare.classList.toggle('hidden', !differs);
    if (differs) {
        $('fxCompareText').textContent = `Your list uses ${rm(trip, 2)} per ¥100.`;
        $('fxUse').classList.toggle('hidden', !canEdit());
    }
    convert();
}

export async function loadFx(force = false) {
    const cached = readCache();
    if (cached && !live) live = cached;
    const fresh = cached && Date.now() - new Date(cached.fetched_at).getTime() < MAX_AGE_MS;
    if (fresh && !force) {
        renderFx();
        return;
    }

    $('fxRefresh').setAttribute('aria-busy', 'true');
    try {
        const res = await api('GET', `/exchange-rates/jpy-myr${force ? '?fresh=1' : ''}`);
        live = res.data;
        writeCache(live);
    } catch {
        if (!live) $('fxSource').textContent = 'Couldn’t get today’s rate. Using the trip’s rate.';
    } finally {
        $('fxRefresh').removeAttribute('aria-busy');
        renderFx();
    }
}

export function bindFx({ onUseRate }) {
    setTripRate = onUseRate;
    $('fxYen').addEventListener('input', () => { lastEdited = 'yen'; convert(); });
    $('fxRm').addEventListener('input', () => { lastEdited = 'rm'; convert(); });
    $('fxRefresh').addEventListener('click', () => loadFx(true));
    $('fxUse').addEventListener('click', () => {
        if (live && canEdit()) {
            setTripRate(live.rm_per_100_yen);
            renderFx();
        }
    });
}
