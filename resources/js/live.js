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
export function toast(message, { tone = 'success', retry = null, action = null } = {}) {
    const item = document.createElement('div');
    item.className = `gl-toast gl-toast--${tone}`;

    const text = document.createElement('p');
    text.textContent = message;
    item.appendChild(text);

    // "Try again" after a failure, or a next step like "Add proof" after a check-in.
    const next = retry ? { label: 'Try again', run: retry } : action;

    if (next) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = next.label;
        button.className = 'gl-toast__action';
        button.addEventListener('click', () => {
            item.remove();
            next.run();
        });
        item.appendChild(button);
    }

    const line = document.createElement('span');
    line.className = 'gl-toast__line';
    line.setAttribute('aria-hidden', 'true');
    line.innerHTML = '<span class="gl-toast__fill"></span><span class="gl-toast__dot"></span>';
    item.appendChild(line);

    toastRegion.appendChild(item);
    window.setTimeout(() => item.classList.add('is-leaving'), next ? 8000 : 3200);
    window.setTimeout(() => item.remove(), next ? 8400 : 3600);
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

async function fetchDocument(url) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return new DOMParser().parseFromString(await response.text(), 'text/html');
}

/**
 * Bring this page's regions up to date (for example after checking in from the sheet), with no reload.
 */
export async function refreshPage() {
    try {
        applyRegions(await fetchDocument(window.location.href));
    } catch {
        // The page still works; it just shows the older numbers until the next visit.
    }
}

/**
 * The toast after a live action. Checking off a task offers "Add proof", which opens its Show your work panel.
 */
function announce(form, message, regions, sent) {
    const checked = form.dataset.optimistic === 'check' && sent?.get('intent') === 'done';
    const taskId = form.dataset.liveKey?.replace('task-', '');
    const proofLink = checked && taskId
        ? regions.map((region) => region.querySelector(`[data-open-proof$="-${CSS.escape(taskId)}"]`)).find(Boolean)
        : null;

    if (message) {
        toast(message, proofLink ? { action: { label: 'Add proof', run: () => proofLink.click() } } : {});
    }
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

/**
 * A progress bar under a form that's uploading a photo, filled as the bytes go up.
 */
function uploadProgress(form) {
    let bar = form.querySelector('[data-upload-progress]');

    if (!bar) {
        bar = document.createElement('div');
        bar.dataset.uploadProgress = '';
        bar.className = 'gl-upload';
        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-label', 'Uploading your photo');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', '100');
        bar.innerHTML = '<span class="gl-upload__fill"></span>';
        form.appendChild(bar);
    }

    return {
        set(percent) {
            bar.hidden = false;
            bar.setAttribute('aria-valuenow', String(percent));
            bar.firstElementChild.style.width = `${percent}%`;
        },
        done() {
            bar.hidden = true;
        },
    };
}

/**
 * Send a live form. Photo uploads go by XMLHttpRequest so the progress bar can follow them; everything else uses
 * fetch. Either way the answer looks the same: its status, where it landed, and the page.
 */
function send(form, data) {
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const hasFile = [...data.values()].some((value) => value instanceof File && value.size > 0);

    if (!hasFile) {
        return fetch(form.action, {
            method,
            body: data,
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            credentials: 'same-origin',
        }).then(async (response) => ({ ok: response.ok, status: response.status, url: response.url, text: () => response.text() }));
    }

    const progress = uploadProgress(form);
    progress.set(0);

    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open(method, form.action);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'text/html');
        request.withCredentials = true;
        request.upload.addEventListener('progress', (event) => event.lengthComputable && progress.set(Math.round((event.loaded / event.total) * 100)));
        request.addEventListener('load', () => {
            progress.done();
            resolve({ ok: request.status >= 200 && request.status < 300, status: request.status, url: request.responseURL, text: async () => request.responseText });
        });
        request.addEventListener('error', () => {
            progress.done();
            reject(new Error('network'));
        });
        request.send(data);
    });
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
        const response = await send(form, data);

        if (response.status === 419 || response.status === 401) {
            window.location.reload(); // Signed out or the session expired: a fresh page signs in or gets a fresh token.

            return;
        }

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const landed = new URL(response.url);

        // A form inside a sheet with its own source (the Check in sheet): refresh from that source, wherever the
        // server sent the answer, so the page underneath never navigates or reloads.
        const source = form.closest('[data-live-source]')?.dataset.liveSource;

        if (source) {
            const fresh = await fetchDocument(source);
            optimistic(form, false);
            const regions = applyRegions(fresh);
            restoreTyping(form.dataset.liveKey, typed);
            celebrate(form, regions);
            form.closest('[data-live-source]').dataset.changed = '1';
            announce(form, flashFrom(doc), regions, typed);

            return;
        }

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
            announce(form, message, regions, typed);
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
