/* Account page: log out confirmation and the "Save trip on this phone" switch. */

import { offlineCopyEnabled, setOfflineCopyEnabled } from '../trip/sync.js';

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
