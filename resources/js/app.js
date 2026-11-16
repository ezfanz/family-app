import { bootTrip } from './trip/main.js';
import { bindGroupPage } from './pages/group.js';

const tripRoot = document.getElementById('app');
if (tripRoot?.dataset.tripId) {
    bootTrip(tripRoot);
}

bindGroupPage();
