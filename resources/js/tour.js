/**
 * The first-run tour: five short coach marks (Clubhouse, Matchups, standings, checking in, Help), each pointing at
 * the real control. Offered once to new and demo accounts, replayable from Help. Never blocking: the page stays usable,
 * focus isn't trapped, Escape or "Skip tour" ends it, and with reduced motion nothing moves.
 */
import { onPage } from './shell';

const STEPS = [
    { find: ['#app-tabbar a[data-tab][href$="/dashboard"]', '#app-topbar a[data-tab][href$="/dashboard"]'], title: 'Your Clubhouse', text: 'Today\'s plan, your goals and your GoalLeagues. Start here each day; History keeps every season and week.' },
    { find: ['#app-tabbar a[data-tab][href$="/matchups"]', '#app-topbar a[data-tab][href$="/matchups"]'], title: 'Your matchup', text: 'Each week you face one friend, both scored against your own plans. The line shows where you both stand.' },
    { find: ['#app-tabbar a[data-tab][href$="/leagues"]', '#app-topbar a[data-tab][href$="/leagues"]'], title: 'Standings', text: 'Open a GoalLeague for the table, stats and every player\'s season.' },
    { find: ['[data-checkin-open]'], title: 'Check in', text: 'Tick off today\'s tasks from any screen. Today and yesterday count.' },
    { find: ['#app-topbar [data-open-help]'], title: 'Help, any time', text: 'Answers, how scoring works, and this tour again. Tap the ? whenever you need it.' },
];

let index = 0;
let returnFocus = null;
let target = null;

const box = () => document.querySelector('[data-tour]');

function visible(element) {
    if (!element) {
        return false;
    }
    const rect = element.getBoundingClientRect();

    return rect.width > 0 && rect.height > 0 && getComputedStyle(element).visibility !== 'hidden';
}

function stepsHere() {
    return STEPS.map((step) => ({ ...step, element: step.find.map((selector) => document.querySelector(selector)).find(visible) })).filter((step) => step.element);
}

function place(card, element) {
    const rect = element.getBoundingClientRect();
    const margin = 12;
    const width = card.offsetWidth;
    const left = Math.min(Math.max(margin, rect.left + rect.width / 2 - width / 2), window.innerWidth - width - margin);
    const below = rect.top < window.innerHeight / 2;

    card.style.left = `${left}px`;
    card.style.top = below ? `${Math.min(rect.bottom + margin, window.innerHeight - card.offsetHeight - margin)}px` : '';
    card.style.bottom = below ? '' : `${Math.max(margin, window.innerHeight - rect.top + margin)}px`;
}

function show() {
    const steps = stepsHere();
    const card = box();

    if (!card || steps.length === 0) {
        return end(false);
    }

    index = Math.min(index, steps.length - 1);
    const step = steps[index];

    target?.classList.remove('gl-tour-target');
    target = step.element;
    target.classList.add('gl-tour-target');

    card.querySelector('[data-tour-count]').textContent = `${index + 1} of ${steps.length}`;
    card.querySelector('[data-tour-title]').textContent = step.title;
    card.querySelector('[data-tour-text]').textContent = step.text;
    card.querySelector('[data-tour-back]').hidden = index === 0;
    card.querySelector('[data-tour-next]').textContent = index === steps.length - 1 ? 'Done' : 'Next';
    card.hidden = false;
    place(card, target);
    card.querySelector('[data-tour-title]').focus({ preventScroll: true });
}

function end(remember = true) {
    const card = box();
    target?.classList.remove('gl-tour-target');
    target = null;

    if (card) {
        card.hidden = true;
    }

    document.querySelector('[data-tour-pending]')?.removeAttribute('data-tour-pending');
    returnFocus?.focus?.({ preventScroll: true });
    returnFocus = null;

    if (remember) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        fetch('/tour', { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => {});
    }
}

export function startTour() {
    returnFocus = document.activeElement;
    index = 0;
    show();
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-tour-start]')) {
        document.querySelectorAll('dialog.gl-sheet[open]').forEach((open) => open.close());
        window.setTimeout(startTour, 50);

        return;
    }

    const card = box();
    if (!card || card.hidden) {
        return;
    }

    if (event.target.closest('[data-tour-skip]')) {
        end();
    } else if (event.target.closest('[data-tour-back]')) {
        index = Math.max(0, index - 1);
        show();
    } else if (event.target.closest('[data-tour-next]')) {
        if (index >= stepsHere().length - 1) {
            end();
        } else {
            index += 1;
            show();
        }
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && box() && !box().hidden) {
        end();
    }
});

// Going somewhere else mid-tour (like tapping the tab it points at) counts as done, so it doesn't start over.
document.addEventListener('turbo:before-visit', () => {
    if (box() && !box().hidden) {
        end();
    }
});

window.addEventListener('resize', () => {
    if (box() && !box().hidden && target) {
        place(box(), target);
    }
});

// Offered once: when the page says this account hasn't seen it, after the page settles.
onPage(() => {
    if (document.querySelector('[data-tour-pending]') && box()?.hidden) {
        window.setTimeout(startTour, 600);
    }
});
