/*
 * E-Itinerary service worker: lets the app open with no signal.
 *
 * - App files (Vite build, icons, font) are cached on install and served cache-first.
 *   Lazy chunks (the 10 MB receipt scanner) are cached the first time they are used.
 * - Pages (/, /trips, /trips/{id}, ...) are network-first with a short timeout and
 *   fall back to the last copy, so a trip you opened before opens offline.
 * - /api is never cached here; the app keeps trip data in localStorage itself.
 */

const VERSION = 'v4';
const STATIC_CACHE = `tabi-static-${VERSION}`;
const PAGE_CACHE = `tabi-pages-${VERSION}`;
const FONT_CACHE = `tabi-fonts-${VERSION}`;
/** Stop photos from Wikimedia Commons, kept across app updates so they work offline. */
const PHOTO_CACHE = 'tabi-photos';
const NETWORK_TIMEOUT_MS = 4000;

const CORE = [
    '/offline.html', '/manifest.webmanifest', '/favicon.ico',
    '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png', '/icons/apple-touch-icon.png',
];

async function buildAssets() {
    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-store' });
        const manifest = await response.json();
        const files = new Set();
        Object.values(manifest).forEach((entry) => {
            if (entry.isDynamicEntry) return; // lazy chunk: cached on first use instead
            if (entry.file) files.add(`/build/${entry.file}`);
            (entry.css || []).forEach((css) => files.add(`/build/${css}`));
        });
        return [...files];
    } catch {
        return [];
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll([...CORE, ...(await buildAssets())]);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [STATIC_CACHE, PAGE_CACHE, FONT_CACHE, PHOTO_CACHE];
        const names = await caches.keys();
        await Promise.all(names.filter((n) => n.startsWith('tabi-') && !keep.includes(n)).map((n) => caches.delete(n)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'clear-pages') {
        event.waitUntil(caches.delete(PAGE_CACHE));
    }
});

function withTimeout(promise, ms) {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('timeout')), ms);
        promise.then((value) => { clearTimeout(timer); resolve(value); }, (error) => { clearTimeout(timer); reject(error); });
    });
}

/** Copy a response so a followed redirect (e.g. "/" -> "/trips/1") can be cached and replayed. */
async function cacheable(response) {
    const body = await response.clone().blob();
    return new Response(body, { status: response.status, statusText: response.statusText, headers: response.headers });
}

/**
 * "/" and "/trips" redirect on the server, and navigation redirects can't be
 * cached, so offline they open the most recently saved trip instead.
 */
async function lastTripPage(cache, request) {
    const path = new URL(request.url).pathname;
    if (path !== '/' && path !== '/trips') {
        return null;
    }
    const keys = await cache.keys();
    const trip = keys.reverse().find((key) => /^\/trips\/\d+$/.test(new URL(key.url).pathname));
    return trip ? cache.match(trip) : null;
}

async function handlePage(request) {
    const cache = await caches.open(PAGE_CACHE);
    const network = fetch(request);
    try {
        const response = await withTimeout(network, NETWORK_TIMEOUT_MS);
        const finalPath = new URL(response.url).pathname;
        const isAuthPage = finalPath === '/login' || finalPath === '/register';
        if (response.ok && response.type !== 'opaqueredirect' && !isAuthPage) {
            // Re-put so the most recently opened trip is last in cache.keys().
            await cache.delete(request);
            await cache.put(request, await cacheable(response));
        } else if (isAuthPage) {
            await cache.delete(request);
        }
        return response;
    } catch {
        const cached = await cache.match(request, { ignoreSearch: true }) || await lastTripPage(cache, request);
        if (cached) return cached;
        // Slow but not dead: wait for the real answer rather than showing the offline page.
        try {
            return await network;
        } catch {
            return (await caches.match('/offline.html')) || new Response('Offline', { status: 503 });
        }
    }
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok || response.type === 'opaque') {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }
    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        if (url.pathname.startsWith('/api/') || url.pathname === '/sw.js') return;
        if (request.mode === 'navigate') {
            event.respondWith(handlePage(request));
            return;
        }
        if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/') || url.pathname === '/manifest.webmanifest' || url.pathname === '/favicon.ico') {
            event.respondWith(cacheFirst(request, STATIC_CACHE));
        }
        return;
    }

    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(cacheFirst(request, FONT_CACHE));
        return;
    }

    if (url.hostname === 'upload.wikimedia.org') {
        event.respondWith(cacheFirst(request, PHOTO_CACHE));
    }
});
