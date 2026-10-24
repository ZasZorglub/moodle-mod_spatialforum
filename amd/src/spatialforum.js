// This file is part of Moodle - http://moodle.org/

/**
 * Controlled spatial movement for the forum stage.
 *
 * @module mod_spatialforum/spatialforum
 */
export const init = () => {
    const root = document.querySelector('[data-region="spatialforum"]');
    if (!root) {
        return;
    }

    const stage = root.querySelector('[data-region="stage"]');
    const plane = root.querySelector('[data-region="plane"]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (stage && plane && !reducedMotion && window.matchMedia('(pointer: fine)').matches) {
        stage.addEventListener('pointermove', (event) => {
            const box = stage.getBoundingClientRect();
            const x = ((event.clientX - box.left) / box.width) - 0.5;
            // Only decorative layers move; links remain in normal layout even in long threads.
            stage.style.setProperty('--tilt-y', `${x * 3.2}deg`);
        });
        stage.addEventListener('pointerleave', () => {
            stage.style.removeProperty('--tilt-y');
        });
    }

    if (plane && !reducedMotion) {
        root.querySelectorAll('[data-track-link]').forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
                    return;
                }
                event.preventDefault();
                plane.classList.add('is-leaving');
                window.setTimeout(() => {
                    window.location.assign(link.href);
                }, 150);
            });
        });
    }

    root.querySelectorAll('[data-confirm-delete]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (!window.confirm(link.dataset.confirmMessage)) {
                event.preventDefault();
            }
        });
    });
};
