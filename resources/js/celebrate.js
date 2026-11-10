/**
 * The band-up celebration. The server decides when a check-in moved a GoalWeek up a band (and only once per band a
 * week); this just plays it, in under 1.5 seconds, skippable by a tap:
 * - In line: the line fills green and the dot pulses. "You're on the line."
 * - Past the line: a golden shine sweeps across. "Past the Line."
 * - Smashed: a blue diamond bursts past the end tick. "Smashed it." With "Add proof to lock it in" when it needs it.
 * A tiny vibration on phones that have one (never a sound). Reduced motion: a simple fade. Screen readers hear it.
 */
import { onPage } from './shell';

const COPY = {
    in_line: { title: "You're on the line.", detail: 'In line this GoalWeek. One more day at a time.' },
    past: { title: 'Past the Line.', detail: 'More than the plan. That\'s the process paying off.' },
    smashed: { title: 'Smashed it.', detail: 'Well past the plan this GoalWeek.' },
};

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let hideTimer;

function root() {
    return document.querySelector('[data-band-celebration]');
}

function hide() {
    const overlay = root();

    if (!overlay || overlay.hidden) {
        return;
    }

    window.clearTimeout(hideTimer);
    overlay.classList.add('is-leaving');
    window.setTimeout(() => {
        overlay.hidden = true;
        overlay.classList.remove('is-playing', 'is-leaving');
    }, 180);
}

export function celebrateBand({ band, percent, needsProof = false, goalUrl = '#' }) {
    const overlay = root();
    const copy = COPY[band];

    if (!overlay || !copy) {
        return;
    }

    overlay.dataset.band = band;
    overlay.querySelector('[data-celebration-title]').textContent = copy.title;
    overlay.querySelector('[data-celebration-detail]').textContent = needsProof
        ? `${percent ? `${percent}% of plan. ` : ''}Add proof or your opponent's OK so it counts in full.`
        : copy.detail;

    const proof = overlay.querySelector('[data-celebration-proof]');
    proof.hidden = !needsProof;
    proof.href = goalUrl || '#';

    overlay.hidden = false;
    overlay.classList.remove('is-playing', 'is-leaving');
    void overlay.offsetWidth; // Restart the animation if it's already showing.
    overlay.classList.add('is-playing');

    if (!reducedMotion() && typeof navigator.vibrate === 'function') {
        navigator.vibrate(band === 'smashed' ? [18, 40, 18] : 18);
    }

    window.clearTimeout(hideTimer);
    // The animation is under 1.5 seconds; a proof nudge stays a little longer so it can be tapped.
    hideTimer = window.setTimeout(hide, needsProof ? 4000 : 1400);
}

// Tap the card (anywhere but the proof link) or press Escape to skip.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-celebration-card]') && !event.target.closest('[data-celebration-proof]')) {
        hide();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        hide();
    }
});

// From a live check-in (live.js) …
document.addEventListener('goalline:band-up', (event) => celebrateBand(event.detail));

// … or a page that arrived with one (a full load, or a page Turbo just showed).
onPage(() => {
    const marker = document.querySelector('[data-band-up]');

    if (marker) {
        marker.remove();
        celebrateBand({ ...marker.dataset, needsProof: marker.dataset.needsProof === '1' });
    }
});
