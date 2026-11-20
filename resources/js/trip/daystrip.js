/* Day strip scrolling: arrow buttons, vertical wheel and mouse drag (from the prototype). */

import { $ } from './util.js';

export function bindDayStrip() {
    const strip = $('days');
    const prev = $('daysPrev');
    const next = $('daysNext');

    const update = () => {
        const max = strip.scrollWidth - strip.clientWidth;
        prev.disabled = strip.scrollLeft <= 4;
        next.disabled = strip.scrollLeft >= max - 4;
    };
    strip.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    window.addEventListener('days:scrolled', () => setTimeout(update, 50));

    const step = () => Math.max(160, strip.clientWidth * 0.7);
    const smooth = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    prev.addEventListener('click', () => strip.scrollBy({ left: -step(), behavior: smooth }));
    next.addEventListener('click', () => strip.scrollBy({ left: step(), behavior: smooth }));

    strip.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX) && strip.scrollWidth > strip.clientWidth) {
            e.preventDefault();
            strip.scrollLeft += e.deltaY;
        }
    }, { passive: false });

    let down = false;
    let moved = false;
    let startX = 0;
    let startLeft = 0;
    strip.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        down = true;
        moved = false;
        startX = e.clientX;
        startLeft = strip.scrollLeft;
    });
    window.addEventListener('pointermove', (e) => {
        // A lifted day tile (drag to swap) moves instead; the strip stays put.
        if (!down || document.body.classList.contains('dragging-stop')) return;
        const dx = e.clientX - startX;
        if (Math.abs(dx) > 5) {
            moved = true;
            strip.classList.add('dragging');
        }
        if (moved) strip.scrollLeft = startLeft - dx;
    });
    window.addEventListener('pointerup', () => {
        if (!down) return;
        down = false;
        strip.classList.remove('dragging');
    });
    strip.addEventListener('click', (e) => {
        if (moved) {
            e.stopPropagation();
            e.preventDefault();
            moved = false;
        }
    }, true);

    setTimeout(update, 50);
}
