import { bootTrip } from './trip/main.js';
import { clearAllTabiStorage } from './trip/sync.js';
import { bindGroupPage } from './pages/group.js';

const tripRoot = document.getElementById('app');
if (tripRoot?.dataset.tripId) {
    bootTrip(tripRoot);
}

bindGroupPage();

// Installable app: the service worker lets a trip you have opened before open with no signal.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            /* the app still works online without it */
        });
    });
}

// Log out removes cached trips and pages from this device (they include private notes).
document.querySelectorAll('form[action$="/logout"]').forEach((form) => {
    form.addEventListener('submit', () => {
        clearAllTabiStorage();
        navigator.serviceWorker?.controller?.postMessage('clear-pages');
    });
});
