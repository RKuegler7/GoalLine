/**
 * Help, from the ? in the top bar (or any [data-open-help]): a sheet that never leaves the page. It loads the Help
 * center once, opens the answer that fits this screen, filters questions as you type, and attaches the page to
 * feedback. Without scripts the ? is a plain link to /help.
 */
import { openSheet } from './sheets';

let loadedFor = null;

function sheet() {
    return document.querySelector('[data-help-sheet]');
}

function pageContext() {
    return document.querySelector('[data-help-context]')?.dataset.helpContext || '';
}

async function load(context) {
    const body = sheet().querySelector('[data-help-body]');
    const key = `${window.location.pathname}|${context}`;

    if (loadedFor !== key || !body.querySelector('[data-help-panel]')) {
        const url = new URL('/help', window.location.origin);
        url.searchParams.set('context', context);
        url.searchParams.set('page', window.location.pathname + window.location.search);

        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            body.innerHTML = await response.text();
            loadedFor = key;
        } catch {
            body.innerHTML = '<p class="py-4 text-ink/75">Help didn\'t load. Check your connection and try again, or open <a class="font-semibold text-club underline" href="/help">the Help page</a>.</p>';

            return;
        }

        // The scoring sheet, if this page doesn't have one yet.
        const template = body.querySelector('template[data-scoring-sheet-template]');
        if (template && !document.getElementById('scoring-sheet')) {
            document.body.append(template.content.cloneNode(true));
        }
        template?.remove();
    }

    const panel = body.querySelector('[data-help-panel]');
    const focus = panel.querySelector('[data-help-focus]') || (context && panel.querySelector(`#help-${CSS.escape(context)}`));

    if (focus) {
        focus.open = true;
        focus.scrollIntoView({ block: 'start' });
        focus.querySelector('summary')?.focus({ preventScroll: true });
    } else {
        panel.querySelector('[data-help-search]')?.focus({ preventScroll: true });
    }
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-help]');

    if (!opener || !sheet() || event.metaKey || event.ctrlKey || event.shiftKey) {
        return;
    }

    event.preventDefault();
    const context = opener.dataset.openHelp || pageContext();

    // From inside another sheet (like scoring), that one steps aside first.
    document.querySelectorAll('dialog.gl-sheet[open]').forEach((open) => {
        if (open !== sheet()) {
            open.close();
        }
    });

    openSheet(sheet());
    load(context);
});

// Search: filter the questions as you type; groups with nothing left hide.
document.addEventListener('input', (event) => {
    const search = event.target.closest('[data-help-search]');

    if (!search) {
        return;
    }

    const panel = search.closest('[data-help-panel]');
    const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
    let shown = 0;

    panel.querySelectorAll('[data-help-item]').forEach((item) => {
        const match = words.every((word) => item.dataset.helpText.includes(word));
        item.hidden = !match;
        shown += match ? 1 : 0;
    });
    panel.querySelectorAll('[data-help-group]').forEach((group) => {
        group.hidden = !group.querySelector('[data-help-item]:not([hidden])');
    });
    panel.querySelector('[data-help-empty]').hidden = shown > 0;
});
