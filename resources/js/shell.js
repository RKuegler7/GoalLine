/**
 * The app shell: Turbo Drive swaps only the page between the top bar and the tab bar, so the bars never re-render or
 * move when you switch tabs. Links are fetched and swapped in place (with the URL pushed, so back and forward work);
 * forms stay with live.js. Each page's scripts run through onPage(), on the first load and after every swap.
 *
 * - The bars are data-turbo-permanent. Just before a swap, the incoming page's active tab and badges are copied onto
 *   them (data-shell-sync), so they're always current without being replaced.
 * - A thin progress bar shows after a moment, and a skeleton if the page is slow.
 * - Tabs prefetch on hover (Turbo) and on touchstart (here, for iPhone).
 * - Each tab remembers where you were scrolled and returns you there.
 * - Cross-fade between pages with the View Transitions API where supported (see app.css), none with reduced motion.
 */
import * as Turbo from '@hotwired/turbo';

Turbo.config.forms.mode = 'off';
Turbo.config.drive.progressBarDelay = 120;

const initializers = [];

/**
 * Run fn on every page: the first load and after each Turbo swap. Use once() inside for per-element setup.
 */
export function onPage(fn) {
    initializers.push(fn);
}

/**
 * Wrap a per-element setup so it runs once per element, however many times the page's initializers run.
 */
export function once(setup) {
    const done = new WeakSet();

    return (element, ...rest) => {
        if (done.has(element)) {
            return;
        }

        done.add(element);
        setup(element, ...rest);
    };
}

/**
 * Set up every element matching selector on each page (first load and after swaps), once per element.
 */
export function eachOnPage(selector, setup) {
    const run = once(setup);
    onPage((root) => root.querySelectorAll(selector).forEach(run));
}

function runPage() {
    initializers.forEach((fn) => {
        try {
            fn(document);
        } catch (error) {
            console.error(error); // One page script failing never stops the rest.
        }
    });

    // Turbo can leave aria-busy on <html> after a redirect; it isn't allowed there and the page is done.
    document.documentElement.removeAttribute('aria-busy');
    // A signal that the page is fully set up (used by the QA suite).
    document.documentElement.dataset.pageReady = '1';
}

// turbo:load fires on the first page load and after every visit.
document.addEventListener('turbo:load', runPage);
document.addEventListener('turbo:visit', () => delete document.documentElement.dataset.pageReady);

/* ---------- The permanent bars stay current ---------- */

document.addEventListener('turbo:before-render', (event) => {
    const incoming = event.detail.newBody;

    incoming.querySelectorAll('[data-shell-sync]').forEach((fresh) => {
        const current = document.querySelector(`[data-shell-sync="${CSS.escape(fresh.dataset.shellSync)}"]`);

        if (!current) {
            return;
        }

        // The active tab's look, its label, and badges: exactly as the server drew them for the new page.
        ['class', 'aria-current', 'aria-label'].forEach((name) => {
            if (fresh.hasAttribute(name)) {
                current.setAttribute(name, fresh.getAttribute(name));
            } else {
                current.removeAttribute(name);
            }
        });

        if (current.innerHTML !== fresh.innerHTML) {
            current.innerHTML = fresh.innerHTML;
        }
    });

    // Menus in the bars close on navigation.
    document.querySelectorAll('[data-turbo-permanent] details[open]').forEach((menu) => menu.removeAttribute('open'));
    hideSkeleton();
});

/* ---------- Loading: a skeleton for slow pages ---------- */

let skeletonTimer;

function hideSkeleton() {
    window.clearTimeout(skeletonTimer);
    document.querySelector('[data-nav-skeleton]')?.setAttribute('hidden', '');
}

document.addEventListener('turbo:visit', (event) => {
    if (event.detail.action === 'restore') {
        return;
    }

    window.clearTimeout(skeletonTimer);
    skeletonTimer = window.setTimeout(() => document.querySelector('[data-nav-skeleton]')?.removeAttribute('hidden'), 350);
});

document.addEventListener('turbo:load', hideSkeleton);
document.addEventListener('turbo:fetch-request-error', hideSkeleton);

/* ---------- Prefetch tabs on touch (iPhone has no hover) ---------- */

document.addEventListener('touchstart', (event) => {
    const link = event.target.closest?.('a[data-tab]');

    if (link) {
        link.dispatchEvent(new MouseEvent('mouseenter', { bubbles: false }));
    }
}, { passive: true, capture: true });

/* ---------- Each tab remembers its scroll position ---------- */

const scrollKey = (path) => `goalline:scroll:${path}`;
let returningToTab = null;

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-tab]');

    if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey) {
        return;
    }

    try {
        sessionStorage.setItem(scrollKey(window.location.pathname), String(window.scrollY));
    } catch {
        // Storage can be off; the tab just opens at the top.
    }

    returningToTab = new URL(link.href).pathname;
});

document.addEventListener('turbo:load', () => {
    if (!returningToTab || returningToTab !== window.location.pathname) {
        returningToTab = null;

        return;
    }

    returningToTab = null;

    try {
        const y = Number(sessionStorage.getItem(scrollKey(window.location.pathname)) || 0);

        if (y > 0) {
            window.scrollTo(0, y);
        }
    } catch {
        // Fine without it.
    }
});

export { Turbo };
