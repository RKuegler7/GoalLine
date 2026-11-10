/**
 * The splash: on the first page of a visit, the wordmark shows while the line draws. As soon as the page is ready the
 * dot runs past the end tick and smashes the line (a small blue diamond), then the page fades in. It never holds
 * anyone up: it's gone by about 1.2 seconds after the page started loading, and a tap skips it. With reduced motion
 * it simply fades. CSS removes it at 1.2 seconds even if this script never runs.
 */
const SPLASH_LIMIT_MS = 1200;
const SMASH_MS = 380;
const FADE_MS = 220;

function finishSplash(splash) {
    if (splash.dataset.finishing) {
        return;
    }

    splash.dataset.finishing = '1';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const remaining = Math.max(0, SPLASH_LIMIT_MS - performance.now());
    const smash = reduced ? 0 : Math.min(SMASH_MS, remaining);

    splash.querySelector('.gl-loader')?.classList.add('is-done');

    window.setTimeout(() => {
        splash.classList.add('is-gone');
        window.setTimeout(() => removeSplash(splash), FADE_MS);
    }, smash);
}

function removeSplash(splash) {
    splash.remove();
    delete document.documentElement.dataset.splash;
    document.dispatchEvent(new CustomEvent('goalline:splash-done'));
}

const splash = document.querySelector('[data-splash]');

if (splash) {
    document.documentElement.dataset.splash = '1';

    // A tap skips it at once.
    splash.addEventListener('pointerdown', () => removeSplash(splash), { once: true });

    // Module scripts run once the page is parsed, so the page is ready now: smash the line and fade in.
    if (performance.now() >= SPLASH_LIMIT_MS) {
        removeSplash(splash);
    } else {
        finishSplash(splash);
    }
}
