/* Group page: copy and share the join code, save role changes on select. */

export function bindGroupPage() {
    const card = document.querySelector('[data-join-code]');
    if (card) {
        const code = card.dataset.joinCode;
        const status = card.querySelector('[data-copy-status]');
        const say = (text) => {
            if (status) status.textContent = text;
        };

        card.querySelector('[data-copy]')?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(code);
                say('Code copied.');
            } catch {
                say(`Copy failed. The code is ${code}.`);
            }
        });

        const shareButton = card.querySelector('[data-share]');
        shareButton?.addEventListener('click', async () => {
            const text = `Join our trip "${card.dataset.tripName}" with code ${code} at ${card.dataset.joinUrl}`;
            if (navigator.share) {
                try {
                    await navigator.share({ title: card.dataset.tripName, text });
                } catch {
                    /* cancelled */
                }
            } else {
                try {
                    await navigator.clipboard.writeText(text);
                    say('Invite message copied.');
                } catch {
                    say(text);
                }
            }
        });
    }

    document.querySelectorAll('[data-autosubmit]').forEach((select) => {
        select.addEventListener('change', () => select.form?.requestSubmit());
    });
    document.querySelectorAll('[data-autosubmit-button]').forEach((button) => button.classList.add('sr-only'));
}
