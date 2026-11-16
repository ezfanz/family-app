/*
 * Group sync and offline tolerance.
 *
 * - Cache: the last trip payload lives in localStorage (per trip and per user,
 *   because notes are private) so the app renders instantly and works offline.
 * - Queue: writes that fail because the network or server is unavailable are
 *   stored in order and retried. While anything is queued, new writes join the
 *   queue too so the server sees them in the order they were made.
 * - Polling: every 20 seconds while the tab is visible; paused when hidden.
 */

import { api } from './api.js';
import { S, snapshot, restore } from './store.js';
import { markPending, clearPending, setWriterHooks } from './writer.js';
import { toast, debounce } from './util.js';

const VERSION = 1;
const POLL_MS = 20000;

export const net = { online: navigator.onLine, flushing: false };

let cacheKey = '';
let queueKey = '';
let queue = [];
let statusChanged = () => {};

function readJson(key) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeJson(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        /* storage full or blocked: the app still works, just without the cache */
    }
}

const persistQueue = () => writeJson(queueKey, queue);

export function initSync({ tripId, userId, onStatus }) {
    cacheKey = `tabi:v${VERSION}:trip:${tripId}:u${userId}`;
    queueKey = `tabi:v${VERSION}:queue:${tripId}:u${userId}`;
    statusChanged = onStatus;
    queue = readJson(queueKey) || [];
    queue.forEach((op) => markPending(op.key));

    setWriterHooks({
        shouldQueue: () => queue.length > 0 || !navigator.onLine,
        enqueue,
        onRetryable: (op) => {
            net.online = false;
            enqueue(op);
            return true;
        },
        onChange: saveCacheSoon,
    });
}

export function queueLength() {
    return queue.length;
}

/** Whether a write for this record is waiting in the offline queue. */
export function isQueued(key) {
    return queue.some((op) => op.key === key);
}

/* ---------------- cache ---------------- */

export function loadCache() {
    const saved = readJson(cacheKey);
    if (!saved?.trip) {
        return false;
    }
    try {
        restore(saved);
        return true;
    } catch {
        return false;
    }
}

export function saveCache() {
    if (S.trip) {
        writeJson(cacheKey, snapshot());
    }
}

export const saveCacheSoon = debounce(saveCache, 400);

export function clearTripStorage() {
    try {
        localStorage.removeItem(cacheKey);
        localStorage.removeItem(queueKey);
    } catch {
        /* ignore */
    }
    queue = [];
}

/** Remove every cached trip and queue on this device (used on log out). */
export function clearAllTabiStorage() {
    try {
        Object.keys(localStorage).filter((k) => k.startsWith('tabi:')).forEach((k) => localStorage.removeItem(k));
    } catch {
        /* ignore */
    }
}

/* ---------------- queue ---------------- */

/** Store a write for later. Consecutive edits to the same record are merged. */
export function enqueue(op) {
    const clean = { key: op.key, method: op.method, path: op.path, body: op.body };
    const lastSamePath = queue.findLast((q) => q.path === clean.path);
    const mergeable = (clean.method === 'PATCH' || clean.method === 'PUT') && lastSamePath?.method === clean.method;

    if (mergeable) {
        lastSamePath.body = clean.method === 'PUT' ? clean.body : { ...lastSamePath.body, ...clean.body };
    } else {
        queue.push(clean);
        markPending(clean.key);
    }

    if (queue.length === 1 && !mergeable) {
        toast('Saved on this device. It will sync when you’re back online.');
    }
    persistQueue();
    saveCacheSoon();
    statusChanged();

    // Queued behind earlier writes while still online: send them shortly.
    if (net.online && navigator.onLine) {
        setTimeout(flushQueue, 300);
    }
}

/**
 * Send queued writes in order. Stops at the first network/server failure.
 *
 * @returns {Promise<{ ok: boolean, rejected: number }>}
 */
export async function flushQueue() {
    if (net.flushing || queue.length === 0) {
        return { ok: true, rejected: 0 };
    }
    net.flushing = true;
    let rejected = 0;
    let ok = true;
    try {
        while (queue.length) {
            const op = queue[0];
            try {
                await api(op.method, op.path, op.body);
            } catch (error) {
                if (error.retryable) {
                    net.online = false;
                    ok = false;
                    break;
                }
                // 403/404/409/422: the server will never accept this one.
                if (!(op.method === 'DELETE' && error.status === 404)) {
                    rejected += 1;
                }
            }
            queue.shift();
            clearPending(op.key);
            persistQueue();
            net.online = true;
        }
    } finally {
        net.flushing = false;
        statusChanged();
    }
    if (rejected) {
        toast(`${rejected} change${rejected === 1 ? '' : 's'} couldn’t be saved and ${rejected === 1 ? 'was' : 'were'} undone.`);
    }
    return { ok, rejected };
}

/* ---------------- polling ---------------- */

export function startPolling(refresh) {
    const visible = () => document.visibilityState === 'visible';
    setInterval(() => {
        if (visible()) refresh();
    }, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (visible()) refresh();
    });
    window.addEventListener('online', () => {
        net.online = true;
        refresh();
    });
    window.addEventListener('offline', () => {
        net.online = false;
        statusChanged();
    });
}
