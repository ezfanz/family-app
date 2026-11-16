/* Appearance: System / Light / Dark. Saved in localStorage (works offline) and a cookie (no flash on load). */

const CHOICES = ['system', 'light', 'dark'];
const YEAR = 60 * 60 * 24 * 365;

export function currentTheme() {
    try {
        const saved = localStorage.getItem('theme');
        if (CHOICES.includes(saved)) return saved;
    } catch {
        /* storage blocked */
    }
    return document.documentElement.dataset.theme || 'system';
}

export function applyTheme(choice, { animate = true } = {}) {
    const root = document.documentElement;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (animate && !reduce) {
        root.classList.add('theme-anim');
        setTimeout(() => root.classList.remove('theme-anim'), 350);
    }

    if (choice === 'light' || choice === 'dark') {
        root.dataset.theme = choice;
        document.cookie = `theme=${choice}; path=/; max-age=${YEAR}; SameSite=Lax`;
    } else {
        root.removeAttribute('data-theme');
        document.cookie = 'theme=; path=/; max-age=0; SameSite=Lax';
    }
    try {
        localStorage.setItem('theme', choice);
    } catch {
        /* storage blocked: the cookie still works */
    }
}

export function bindThemePicker() {
    const inputs = document.querySelectorAll('input[name="theme"]');
    if (!inputs.length) return;

    const selected = currentTheme();
    inputs.forEach((input) => {
        input.checked = input.value === selected;
        input.addEventListener('change', () => {
            if (input.checked) {
                applyTheme(input.value);
                const status = document.getElementById('themeStatus');
                if (status) status.textContent = `${input.dataset.label} theme on.`;
            }
        });
    });
}
