/* Small helpers shared by the trip screens. */

export const $ = (id) => document.getElementById(id);

export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DOW = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function utcDate(iso) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d));
}

/** "21 Nov" */
export function fmtDate(iso) {
    const d = utcDate(iso);
    return `${d.getUTCDate()} ${MON[d.getUTCMonth()]}`;
}

/** "Saturday" or "Sat" */
export function weekday(iso, short = false) {
    const name = DOW[utcDate(iso).getUTCDay()];
    return short ? name.slice(0, 3) : name;
}

export function daysBetween(fromIso, toIso) {
    return Math.round((utcDate(toIso) - utcDate(fromIso)) / 864e5);
}

/** Current date and minutes-since-midnight in the trip's timezone. */
export function zonedNow(timeZone = 'Asia/Tokyo') {
    try {
        const parts = Object.fromEntries(
            new Intl.DateTimeFormat('en-CA', {
                timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
            }).formatToParts(new Date()).map((p) => [p.type, p.value]),
        );
        return { date: `${parts.year}-${parts.month}-${parts.day}`, mins: Number(parts.hour) * 60 + Number(parts.minute) };
    } catch {
        const d = new Date(Date.now() + 9 * 3600e3);
        return { date: d.toISOString().slice(0, 10), mins: d.getUTCHours() * 60 + d.getUTCMinutes() };
    }
}

export function toMins(time) {
    const [h, m] = String(time || '00:00').split(':').map(Number);
    return h * 60 + m;
}

export const CITY = {
    Osaka: ['--osaka', 'O'],
    Nara: ['--nara', 'N'],
    Kyoto: ['--kyoto', 'K'],
    Tokyo: ['--tokyo', 'T'],
    Fuji: ['--fuji', 'F'],
};

export function cityColor(city) {
    return CITY[city] ? `var(${CITY[city][0]})` : 'var(--action)';
}

export function cityLetter(city) {
    return CITY[city] ? CITY[city][1] : (city ? city[0].toUpperCase() : '·');
}

export function mapUrl(place, area) {
    const where = area === 'Fuji' ? 'Yamanashi' : area;
    const query = where ? `${place} ${where}` : place;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

export function yen(n) {
    return `¥${Math.round(Number(n) || 0).toLocaleString('en-US')}`;
}

/** RM with two decimals for totals, whole ringgit for list hints. */
export function rm(n, decimals = 2) {
    const v = Number(n) || 0;
    const body = Math.abs(v).toLocaleString('en-MY', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    return `${v < 0 ? '−' : ''}RM${body}`;
}

export function uuid() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

let toastTimer;

/**
 * Short message above the tab bar. Optional action button, e.g. { action: 'Undo', onAction }.
 *
 * @param {string} message
 * @param {{ action?: string, onAction?: () => void, duration?: number }} [options]
 */
export function toast(message, options = {}) {
    const el = $('toast');
    if (!el) {
        return;
    }
    el.textContent = '';
    const text = document.createElement('span');
    text.className = 'toast-msg';
    text.textContent = message;
    el.appendChild(text);
    if (options.action && options.onAction) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'toast-act';
        button.textContent = options.action;
        button.addEventListener('click', () => {
            el.classList.add('hidden');
            options.onAction();
        }, { once: true });
        el.appendChild(button);
    }
    el.classList.toggle('with-action', Boolean(options.action));
    el.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.add('hidden'), options.duration ?? (options.action ? 6000 : 3500));
}

export function debounce(fn, wait) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

/** Debounce per key, so typing in two notes quickly saves both. */
export function debounceKeyed(fn, wait) {
    const timers = new Map();
    return (key, ...args) => {
        clearTimeout(timers.get(key));
        timers.set(key, setTimeout(() => {
            timers.delete(key);
            fn(key, ...args);
        }, wait));
    };
}

export const ICON = {
    tick: '<svg class="i" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" style="stroke:#FFFFFF;stroke-width:3.5"><path d="M5 12.5l4 4 10-10"/></svg>',
    pin: '<svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>',
    pinLg: '<svg class="i" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-6-5.6-6-11a6 6 0 0 1 12 0c0 5.4-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>',
    plus: '<svg class="i" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
    eye: '<svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/></svg>',
    offline: '<svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M8.5 8.6A5 5 0 0 0 6.5 18H17"/><path d="M10.6 6.2A5 5 0 0 1 17.6 10 3.5 3.5 0 0 1 20 16"/></svg>',
};
