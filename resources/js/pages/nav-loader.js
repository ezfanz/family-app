/*
 * Shows the fill-text loader whenever the browser has to wait for the server:
 * following a link to another page or submitting a normal form (log in, join,
 * change role, log out...). In-page actions on the trip (tick, note, add stop)
 * stay instant and are not covered.
 *
 * Opt out with data-no-loader on a link or form. Set the word with
 * data-loading="Joining" on a link, form or submit button.
 */

import { showLoader, resetLoader } from '../trip/loader.js';

const DELAY_MS = 150; // fast pages never show it

let timer = null;

function start(label) {
    clearTimeout(timer);
    timer = setTimeout(() => {
        document.querySelectorAll('dialog[open]').forEach((d) => d.close());
        showLoader(label || 'Loading');
    }, DELAY_MS);
}

function isPageLink(link, event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.target && link.target !== '_self') return false;
    if (link.hasAttribute('download') || link.closest('[data-no-loader]')) return false;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return false;
    // Same page, only the #hash changes: handled in the page without the server.
    if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
    return true;
}

export function bindNavLoader() {
    if (!document.getElementById('loader')) return;

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (link && isPageLink(link, event)) {
            start(link.dataset.loading);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || form.hasAttribute('data-no-loader') || form.closest('dialog.sheet:not(.confirm-sheet)')) return;
        const submitter = event.submitter;
        start(submitter?.dataset.loading || form.dataset.loading);
    });

    // Coming back with the browser's back button can restore the page with the loader still up.
    window.addEventListener('pageshow', (event) => {
        clearTimeout(timer);
        if (event.persisted) {
            resetLoader();
        }
    });
}
