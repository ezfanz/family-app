/*
 * Receipt photos waiting to upload. Kept in IndexedDB (localStorage is too
 * small for images), uploaded when the receipt exists on the server and
 * the phone is online. A photo is only removed after the server has it.
 */

import { apiUrl } from './api.js';

const DB_NAME = 'tabi-receipts';
const STORE = 'photos';
const MAX_EDGE = 1600;
const QUALITY = 0.8;

/** Object URLs of photos taken on this phone, so they show before upload. key = "<receiptId>:<part>" */
const localUrls = new Map();
const listeners = new Set();
let flushing = null;

function openDb() {
    return new Promise((resolve, reject) => {
        if (!('indexedDB' in window)) {
            reject(new Error('IndexedDB is not available'));
            return;
        }
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'key' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function tx(mode, run) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE, mode);
        const result = run(transaction.objectStore(STORE));
        transaction.oncomplete = () => resolve(result?.result ?? result);
        transaction.onerror = () => reject(transaction.error);
    });
}

const notify = () => listeners.forEach((fn) => fn());

/** Called whenever the set of waiting photos changes or one finishes uploading. */
export function onPhotosChanged(fn) {
    listeners.add(fn);
}

/**
 * Hook for hiding passport details before a photo leaves the phone.
 * Tax-free receipts can print the passport number and name.
 * TODO: blur or crop the passport block once we can find it (Phase 2 reader or a crop tool).
 *
 * @param {CanvasRenderingContext2D} context
 * @param {number} width
 * @param {number} height
 */
function redactPassportDetails(context, width, height) {}

/**
 * Shrink a photo to at most 1600 px on its long edge as JPEG (about 250 KB).
 *
 * @param {Blob|HTMLVideoElement} source
 * @returns {Promise<Blob>}
 */
export async function preparePhoto(source) {
    let image = source;
    let width;
    let height;
    if (source instanceof HTMLVideoElement) {
        width = source.videoWidth;
        height = source.videoHeight;
    } else {
        image = await createImageBitmap(source, { imageOrientation: 'from-image' }).catch(() => loadImage(source));
        width = image.width;
        height = image.height;
    }
    const scale = Math.min(1, MAX_EDGE / Math.max(width, height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);
    const context = canvas.getContext('2d');
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    redactPassportDetails(context, canvas.width, canvas.height);
    return new Promise((resolve, reject) => canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not read the photo'))), 'image/jpeg', QUALITY));
}

function loadImage(blob) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Could not open that photo'));
        img.src = URL.createObjectURL(blob);
    });
}

/** Save a photo on this phone straight away; it uploads later. */
export async function queuePhoto(receiptId, part, blob) {
    const key = `${receiptId}:${part}`;
    const previous = localUrls.get(key);
    if (previous) URL.revokeObjectURL(previous);
    localUrls.set(key, URL.createObjectURL(blob));
    try {
        await tx('readwrite', (store) => store.put({ key, receiptId, part, blob, createdAt: Date.now() }));
    } catch {
        // Private mode without IndexedDB: keep it in memory for this visit.
        memoryQueue.set(key, { key, receiptId, part, blob });
    }
    notify();
}

const memoryQueue = new Map();

async function allQueued() {
    let stored = [];
    try {
        stored = await tx('readonly', (store) => store.getAll());
    } catch {
        stored = [];
    }
    return [...stored, ...memoryQueue.values()];
}

async function remove(key) {
    memoryQueue.delete(key);
    try {
        await tx('readwrite', (store) => store.delete(key));
    } catch {
        // nothing stored
    }
}

/** Receipt ids that still have a photo waiting to upload. */
export async function waitingReceiptIds() {
    return new Set((await allQueued()).map((item) => item.receiptId));
}

/** Object URL for a photo taken on this phone (before or after upload), if any. */
export function localPhotoUrl(receiptId, part = 1) {
    return localUrls.get(`${receiptId}:${part}`) || null;
}

/** Fill object URLs for photos still in IndexedDB after a reload. */
export async function restoreLocalUrls() {
    for (const item of await allQueued()) {
        if (!localUrls.has(item.key)) localUrls.set(item.key, URL.createObjectURL(item.blob));
    }
    notify();
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/**
 * Upload every waiting photo. A photo whose receipt is not on the server yet
 * (404, still in the offline queue) waits for the next try.
 *
 * @param {(receipt: object) => void} onUploaded receives the updated receipt from the server
 */
export function flushPhotos(onUploaded = () => {}) {
    if (flushing) return flushing;
    flushing = (async () => {
        for (const item of await allQueued()) {
            if (!navigator.onLine) break;
            const form = new FormData();
            form.append('part', String(item.part));
            form.append('photo', item.blob, `receipt-${item.part}.jpg`);
            let response;
            try {
                response = await fetch(apiUrl(`/receipts/${item.receiptId}/photos`), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    body: form,
                });
            } catch {
                break; // offline or server down: try again later
            }
            if (response.ok) {
                const body = await response.json().catch(() => null);
                await remove(item.key);
                if (body?.data) onUploaded(body.data);
            } else if (response.status === 422 || response.status === 403) {
                await remove(item.key); // the server will never take this one
            } else if (response.status >= 500 || response.status === 429 || response.status === 419) {
                break;
            }
            // 404: receipt not created yet; leave it for later.
        }
        notify();
    })().finally(() => { flushing = null; });
    return flushing;
}
