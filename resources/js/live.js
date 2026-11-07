/**
 * Live forms: actions that update the page in place instead of reloading it and jumping to the top.
 *
 * Any <form data-live> is sent with fetch. The server answers exactly as it does without JavaScript (a redirect to
 * the updated page), so every form keeps working with JavaScript off. With it on:
 * - the button shows a busy state and the form ignores a second submit until the first finishes;
 * - forms with data-optimistic flip their look right away and roll back if the server says no;
 * - every part of the page marked data-region is swapped for its fresh version, so counts, progress, bands and
 *   badges elsewhere on the page update too, while the scroll position stays put;
 * - the page's status message becomes a toast, validation messages land next to their fields, and what the person
 *   typed is kept;
 * - check-ins pop, numbers count up, and progress bars glide. All motion is off for prefers-reduced-motion.
 * If an action leads to a different page (joining a league, say), the browser simply goes there.
 */

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Toasts ---------- */

const toastRegion = (() => {
    const region = document.createElement('div');
    region.className = 'gl-toasts';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    document.body.appendChild(region);

    return region;
})();

/**
 * A short message at the bottom of the screen with the GoalLine line filling across it. It leaves on its own.
 */
export function toast(message, { tone = 'success', retry = null } = {}) {
    const item = document.createElement('div');
    item.className = `gl-toast gl-toast--${tone}`;

    const text = document.createElement('p');
    text.textContent = message;
    item.appendChild(text);

    if (retry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Try again';
        button.className = 'gl-toast__action';
        button.addEventListener('click', () => {
            item.remove();
            retry();
        });
        item.appendChild(button);
    }

    const line = document.createElement('span');
    line.className = 'gl-toast__line';
    line.setAttribute('aria-hidden', 'true');
    line.innerHTML = '<span class="gl-toast__fill"></span><span class="gl-toast__dot"></span>';
    item.appendChild(line);

    toastRegion.appendChild(item);
    window.setTimeout(() => item.classList.add('is-leaving'), retry ? 8000 : 3200);
    window.setTimeout(() => item.remove(), retry ? 8400 : 3600);
}

// A message carried over from a live action that moved to another page.
try {
    const carried = sessionStorage.getItem('goalline:toast');

    if (carried) {
        sessionStorage.removeItem('goalline:toast');
        toast(carried);
    }
} catch {
    // Storage can be off; the page's own status message still shows.
}

// Back where you were after a live action that had to reload the page.
try {
    const saved = JSON.parse(sessionStorage.getItem('goalline:scroll') || 'null');

    if (saved) {
        sessionStorage.removeItem('goalline:scroll');

        if (saved.path === window.location.pathname) {
            window.scrollTo(0, saved.y);
        }
    }
} catch {
    // Fine without it.
}

/* ---------- Motion ---------- */

/**
 * Count a number up (or down) to its new value, keeping any text around it ("62%", "+4 pts").
 */
function countUp(element, from, to) {
    const template = element.textContent;

    if (reducedMotion() || from === to || Number.isNaN(from) || Number.isNaN(to)) {
        return;
    }

    const decimals = (String(to).split('.')[1] || '').length;
    const started = performance.now();
    const duration = 350;
    const step = (now) => {
        const progress = Math.min(1, (now - started) / duration);
        const value = from + (to - from) * (1 - (1 - progress) ** 3);
        element.textContent = template.replace(String(to), value.toFixed(decimals));

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            element.textContent = template;
        }
    };

    requestAnimationFrame(step);
}

/**
 * Remember numbers and bar widths before a swap, so they can move to their new values after it.
 */
function snapshot(root) {
    const numbers = new Map();
    const bars = new Map();

    root.querySelectorAll('[data-countup][data-key]').forEach((element) => numbers.set(element.dataset.key, parseFloat(element.dataset.countup)));
    root.querySelectorAll('[data-progress-bar][data-key]').forEach((element) => bars.set(element.dataset.key, element.style.width));

    return { numbers, bars };
}

function animateChanges(root, before) {
    root.querySelectorAll('[data-countup][data-key]').forEach((element) => {
        if (before.numbers.has(element.dataset.key)) {
            countUp(element, before.numbers.get(element.dataset.key), parseFloat(element.dataset.countup));
        }
    });

    root.querySelectorAll('[data-progress-bar][data-key]').forEach((element) => {
        const from = before.bars.get(element.dataset.key);
        const to = element.style.width;

        if (from !== undefined && from !== to && !reducedMotion()) {
            element.style.width = from;
            requestAnimationFrame(() => requestAnimationFrame(() => {
                element.style.width = to;
            }));
        }
    });
}

/* ---------- Regions ---------- */

/**
 * Swap every changed data-region for its fresh version from the server's page. Returns the regions swapped in.
 */
function applyRegions(doc) {
    const swapped = [];
    const before = snapshot(document);
    const focusKey = document.activeElement?.closest('[data-focus-key]')?.dataset.focusKey;

    document.querySelectorAll('[data-region]').forEach((current) => {
        const fresh = doc.querySelector(`[data-region="${CSS.escape(current.dataset.region)}"]`);

        if (!fresh || fresh.innerHTML === current.innerHTML) {
            return;
        }

        const incoming = document.importNode(fresh, true);
        current.replaceWith(incoming);
        swapped.push(incoming);
    });

    swapped.forEach((region) => {
        animateChanges(region, before);
        region.dispatchEvent(new CustomEvent('goalline:updated', { bubbles: true }));
    });

    if (focusKey) {
        document.querySelector(`[data-focus-key="${CSS.escape(focusKey)}"]`)?.focus({ preventScroll: true });
    }

    return swapped;
}

/**
 * The page's status message, if the action left one.
 */
function flashFrom(doc) {
    return doc.querySelector('[data-flash]')?.textContent.trim() || null;
}

/* ---------- Forms ---------- */

function setBusy(form, submitter, busy) {
    form.toggleAttribute('data-live-pending', busy);
    form.setAttribute('aria-busy', busy ? 'true' : 'false');
    form.querySelectorAll('button, input[type="submit"]').forEach((button) => {
        button.disabled = busy;
    });

    if (submitter) {
        submitter.toggleAttribute('data-busy', busy);
    }
}

/**
 * Flip a form's look right away (a check turns green, a cheer counts up); undone if the server says no.
 */
function optimistic(form, on) {
    if (!form.hasAttribute('data-optimistic')) {
        return;
    }

    form.toggleAttribute('data-optimistic-on', on);
    form.querySelectorAll('[data-optimistic-count]').forEach((count) => {
        const value = parseInt(count.dataset.optimisticCount, 10);

        if (!Number.isNaN(value)) {
            count.textContent = on ? String(value + (form.dataset.optimistic === 'remove' ? -1 : 1)) : String(value);
        }
    });
}

/**
 * After a validation error, put back anything the person typed that the fresh form doesn't have.
 */
function restoreTyping(formKey, typed) {
    const form = formKey && document.querySelector(`form[data-live-key="${CSS.escape(formKey)}"]`);

    if (!form) {
        return;
    }

    typed.forEach((value, name) => {
        const field = form.querySelector(`[name="${CSS.escape(name)}"]`);

        if (field && typeof value === 'string' && ['text', 'textarea', 'number', 'email', 'search', 'url', 'date'].includes(field.type) && !field.value) {
            field.value = value;
        }
    });
}

/**
 * The check-in pop and its toast, when a live form says it completes something.
 */
function celebrate(form, regions) {
    if (form.dataset.celebrate !== 'check' || reducedMotion()) {
        return;
    }

    const key = form.dataset.liveKey;
    const fresh = regions.map((region) => region.querySelector(`form[data-live-key="${CSS.escape(key)}"] [data-check]`)).find(Boolean);

    if (fresh?.dataset.check === 'done') {
        fresh.classList.add('gl-pop');
    }
}

async function submit(form, submitter) {
    if (form.hasAttribute('data-live-pending')) {
        return; // Double-submit protection: one at a time.
    }

    const data = new FormData(form, submitter);
    const typed = new Map([...data.entries()].filter(([, value]) => typeof value === 'string'));
    setBusy(form, submitter, true);
    optimistic(form, true);

    try {
        const response = await fetch(form.action, {
            method: (form.getAttribute('method') || 'POST').toUpperCase(),
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
        });

        if (response.status === 419 || response.status === 401) {
            window.location.reload(); // Signed out or the session expired: a fresh page signs in or gets a fresh token.

            return;
        }

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const landed = new URL(response.url);

        // The action leads somewhere else (joining a league, a new page): go there, with its message.
        if (landed.pathname !== window.location.pathname) {
            try {
                const message = flashFrom(doc);

                if (message) {
                    sessionStorage.setItem('goalline:toast', message);
                }
            } catch {
                // Fine without it.
            }

            window.location.assign(response.url);

            return;
        }

        // A form outside any refreshable region: reload the page, but keep the scroll position and show the toast.
        if (!form.closest('[data-region]')) {
            try {
                const message = flashFrom(doc);

                if (message) {
                    sessionStorage.setItem('goalline:toast', message);
                }

                sessionStorage.setItem('goalline:scroll', JSON.stringify({ path: window.location.pathname, y: window.scrollY }));
            } catch {
                // Fine without it.
            }

            window.location.reload();

            return;
        }

        optimistic(form, false);
        const regions = applyRegions(doc);
        restoreTyping(form.dataset.liveKey, typed);
        celebrate(form, regions);

        const message = flashFrom(doc);
        // A validation message shows up in (or right under) the form that was sent.
        const sentAgain = [...document.querySelectorAll('form[data-live]')].find((candidate) => candidate.action === form.action);
        const hasErrors = Boolean(sentAgain?.closest('[data-region]') && sentAgain.parentElement?.querySelector('.text-red-700'));

        if (message) {
            toast(message);
        } else if (hasErrors) {
            toast('Almost: check the note next to the field.', { tone: 'warning' });
        }
    } catch {
        optimistic(form, false);
        toast("Couldn't reach GoalLine just now.", { tone: 'warning', retry: () => submit(form, submitter) });
    } finally {
        setBusy(form, submitter, false);
    }
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-live]');

    // Forms with their own confirm() that was declined arrive here already cancelled.
    if (!form || event.defaultPrevented || !window.fetch || !window.DOMParser) {
        return;
    }

    event.preventDefault();
    submit(form, event.submitter);
});
