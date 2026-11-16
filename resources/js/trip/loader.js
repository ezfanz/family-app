/*
 * "Loading" fill-text overlay, after motion.dev's loading-fill-text example.
 * A faded copy of the word sits underneath; the solid copy is revealed left to
 * right with clip-path. The fill creeps while we wait and springs to full when
 * the request finishes.
 */

import { animate } from 'motion/mini';
import { $ } from './util.js';

const HIDDEN = 'inset(0 100% 0 0)';
const MIN_VISIBLE_MS = 600;
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let active = null;

/** Hide immediately without animation (e.g. page restored from the back/forward cache). */
export function resetLoader() {
    const overlay = $('loader');
    overlay?.classList.add('hidden');
    if (overlay) overlay.style.opacity = '';
    active = null;
}

/**
 * Show the overlay. Call the returned finish() when the work is done.
 *
 * @param {string} label
 * @returns {{ finish: () => Promise<void> }}
 */
export function showLoader(label = 'Loading') {
    if (active) {
        return active;
    }

    const overlay = $('loader');
    const fill = overlay.querySelector('.fg');
    overlay.querySelectorAll('.bg, .fg').forEach((el) => { el.textContent = label; });
    overlay.setAttribute('aria-label', `${label}…`);
    fill.style.clipPath = HIDDEN;
    overlay.classList.remove('hidden');

    const startedAt = performance.now();
    const still = reducedMotion();
    let creep = null;

    if (still) {
        fill.style.clipPath = 'inset(0 0% 0 0)';
    } else {
        animate(overlay, { opacity: [0, 1] }, { duration: 0.15 });
        // Fast start, then slow down as if waiting on the network.
        creep = animate(fill, { clipPath: [HIDDEN, 'inset(0 55% 0 0)', 'inset(0 20% 0 0)'] }, {
            duration: 2.4,
            times: [0, 0.25, 1],
            ease: ['easeOut', 'easeOut'],
        });
    }

    active = {
        async finish() {
            const wait = MIN_VISIBLE_MS - (performance.now() - startedAt);
            if (wait > 0) {
                await new Promise((resolve) => setTimeout(resolve, wait));
            }
            if (!still) {
                creep?.stop();
                await animate(fill, { clipPath: 'inset(0 0% 0 0)' }, { type: 'spring', bounce: 0, duration: 0.45 });
                await animate(overlay, { opacity: 0 }, { duration: 0.2 });
            }
            overlay.classList.add('hidden');
            overlay.style.opacity = '';
            active = null;
        },
    };
    return active;
}
