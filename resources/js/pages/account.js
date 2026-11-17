/* Account page: log out confirmation, "Save trip on this phone" and each trip's "Today's exchange rate" switch. */

import { offlineCopyEnabled, setOfflineCopyEnabled } from '../trip/sync.js';
import { api } from '../trip/api.js';

export function bindAccountPage() {
    const dialog = document.getElementById('logoutDlg');
    const trigger = document.querySelector('[data-confirm-logout]');
    if (dialog && trigger && typeof dialog.showModal === 'function') {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            dialog.showModal();
        });
        dialog.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    }

    bindTripAutoRate();

    // Edit name: focus the box when it opens; Cancel closes and restores the saved name.
    const nameEdit = document.getElementById('nameEdit');
    if (nameEdit) {
        const input = document.getElementById('accName');
        const saved = input.defaultValue;
        nameEdit.addEventListener('toggle', () => {
            if (nameEdit.open) {
                input.focus();
                input.select();
            }
        });
        nameEdit.querySelector('[data-close-edit]').addEventListener('click', () => {
            input.value = saved;
            nameEdit.open = false;
        });
    }

    const offline = document.getElementById('offlineCopy');
    if (offline) {
        offline.checked = offlineCopyEnabled();
        offline.addEventListener('change', () => {
            setOfflineCopyEnabled(offline.checked);
            if (!offline.checked) {
                navigator.serviceWorker?.controller?.postMessage('clear-pages');
            }
        });
    }
}

/**
 * Trip settings: "Today's exchange rate" per trip (owners and editors).
 * Turning it off keeps today's rate as the trip's fixed rate, so prices don't jump.
 */
function bindTripAutoRate() {
    const status = document.getElementById('tripSettingsStatus');
    document.querySelectorAll('[data-trip-auto-rate]').forEach((toggle) => {
        const note = toggle.closest('label')?.querySelector('[data-auto-rate-note]');
        const show = (on) => {
            if (note) note.textContent = on ? 'Bank Negara Malaysia, updated daily' : 'Off: everyone uses the rate typed on Shopping';
        };
        toggle.addEventListener('change', async () => {
            const on = toggle.checked;
            const body = { exchange_rate_auto: on };
            toggle.disabled = true;
            try {
                if (!on) {
                    const today = await api('GET', '/exchange-rates/jpy-myr').catch(() => null);
                    if (today?.data?.rm_per_100_yen > 0) body.exchange_rate_rm_per_100_yen = Number(today.data.rm_per_100_yen);
                }
                await api('PATCH', `/trips/${toggle.dataset.tripAutoRate}`, body);
                show(on);
                if (status) status.textContent = on ? 'Using today’s rate.' : 'Using the trip’s own rate.';
            } catch {
                toggle.checked = !on;
                show(!on);
                if (status) status.textContent = 'Couldn’t save. Check your connection and try again.';
                window.alert('Couldn’t save the exchange rate setting. Check your connection and try again.');
            } finally {
                toggle.disabled = false;
            }
        });
    });
}
