import { bootTrip } from './trip/main.js';
import { clearAllTabiStorage } from './trip/sync.js';
import { bindGroupPage } from './pages/group.js';

const tripRoot = document.getElementById('app');
if (tripRoot?.dataset.tripId) {
    bootTrip(tripRoot);
}

bindGroupPage();

// Log out removes cached trips from this device (they include private notes).
document.querySelectorAll('form[action$="/logout"]').forEach((form) => {
    form.addEventListener('submit', clearAllTabiStorage);
});
