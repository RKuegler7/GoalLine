/**
 * League chat: send without a page reload and pick up new messages every few seconds.
 * The chat still works as plain forms if this script doesn't run.
 */
const POLL_INTERVAL_MS = 5000;

function setUpLeagueChat(chat) {
    const list = chat.querySelector('[data-chat-list]');
    const form = chat.querySelector('[data-chat-form]');
    const input = form.querySelector('textarea');
    const token = form.querySelector('input[name="_token"]').value;
    const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
    let lastId = Number(chat.dataset.lastId || 0);
    let polling = false;

    const isNearBottom = () => list.scrollHeight - list.scrollTop - list.clientHeight < 80;
    const scrollToEnd = () => {
        list.scrollTop = list.scrollHeight;
    };

    const append = async (response) => {
        const html = (await response.text()).trim();

        if (html) {
            list.querySelector('[data-chat-empty]')?.remove();
            list.insertAdjacentHTML('beforeend', html);
        }

        lastId = Math.max(lastId, Number(response.headers.get('X-Last-Message-Id') || lastId));
    };

    const poll = async () => {
        if (polling || document.hidden) {
            return;
        }

        polling = true;

        try {
            const response = await fetch(`${chat.dataset.pollUrl}?after=${lastId}`, { headers });

            if (response.ok) {
                const stickToBottom = isNearBottom();
                await append(response);

                if (stickToBottom) {
                    scrollToEnd();
                }
            }
        } finally {
            polling = false;
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!input.value.trim()) {
            return;
        }

        input.disabled = true;

        try {
            const response = await fetch(`${form.action}?after=${lastId}`, { method: 'POST', headers, body: new FormData(form) });

            if (response.ok) {
                await append(response);
                input.value = '';
                scrollToEnd();
            } else if (response.status === 429) {
                window.alert('Easy, champ. Give it a moment before sending more.');
            }
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    // Enter sends; Shift+Enter adds a new line.
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    list.addEventListener('submit', async (event) => {
        const deleteForm = event.target.closest('[data-chat-delete]');

        if (!deleteForm) {
            return;
        }

        event.preventDefault();

        if (!window.confirm('Delete this message?')) {
            return;
        }

        const response = await fetch(deleteForm.action, { method: 'POST', headers, body: new FormData(deleteForm) });

        if (response.ok) {
            deleteForm.closest('[data-message-id]')?.remove();
        }
    });

    scrollToEnd();
    window.setInterval(poll, POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', poll);
}

document.querySelectorAll('[data-league-chat]').forEach(setUpLeagueChat);

/**
 * Share cards: turn the on-page SVG into a 1080 × 1350 PNG. The page's web fonts are embedded
 * into the SVG first, because an SVG drawn as an image can't load fonts on its own.
 */
async function embeddedFontCss() {
    const rules = [];

    for (const sheet of document.styleSheets) {
        let cssRules;

        try {
            cssRules = sheet.cssRules;
        } catch {
            continue;
        }

        for (const rule of cssRules) {
            if (!(rule instanceof CSSFontFaceRule)) {
                continue;
            }

            const url = rule.style.getPropertyValue('src').match(/url\("?([^")]+\.woff2)"?\)/)?.[1];

            if (url) {
                rules.push({ rule, url });
            }
        }
    }

    const css = await Promise.all(rules.map(async ({ rule, url }) => {
        const bytes = new Uint8Array(await (await fetch(url)).arrayBuffer());
        let binary = '';
        bytes.forEach((byte) => {
            binary += String.fromCharCode(byte);
        });

        return `@font-face { font-family: ${rule.style.getPropertyValue('font-family')}; font-weight: ${rule.style.getPropertyValue('font-weight')}; src: url(data:font/woff2;base64,${btoa(binary)}) format("woff2"); }`;
    }));

    return css.join('\n');
}

document.querySelectorAll('[data-download-card]').forEach((button) => {
    button.addEventListener('click', async () => {
        const original = document.querySelector('[data-share-card]');
        const label = button.textContent;
        button.disabled = true;
        button.textContent = 'Preparing image...';

        try {
            const svg = original.cloneNode(true);
            svg.setAttribute('width', '1080');
            svg.setAttribute('height', '1350');
            const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
            style.textContent = await embeddedFontCss();
            svg.prepend(style);

            const image = new Image();
            image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(new XMLSerializer().serializeToString(svg))}`;
            await image.decode();

            const canvas = document.createElement('canvas');
            canvas.width = 1080;
            canvas.height = 1350;
            canvas.getContext('2d').drawImage(image, 0, 0, 1080, 1350);

            const link = document.createElement('a');
            link.download = button.dataset.filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    });
});

/**
 * Time zones: fill hidden fields with the browser's zone, and let "Use my time zone" set the picker.
 */
const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

document.querySelectorAll('[data-detect-timezone]').forEach((field) => {
    if (!field.value) {
        field.value = browserTimezone;
    }
});

document.querySelectorAll('[data-use-browser-timezone]').forEach((button) => {
    const select = document.getElementById(button.dataset.useBrowserTimezone);

    button.hidden = !browserTimezone || select.value === browserTimezone;
    button.textContent = `Use ${browserTimezone.replaceAll('_', ' ')}`;
    button.addEventListener('click', () => {
        select.value = browserTimezone;
        button.hidden = true;
    });
});

/**
 * The app: register the service worker, and offer "Add to home screen" where the browser supports it.
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

const installCard = document.querySelector('[data-install-card]');
const isInstalled = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const DISMISSED_KEY = 'goalline:install-dismissed';

const wasDismissed = () => {
    try {
        return localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
};

if (installCard && !isInstalled && !wasDismissed()) {
    const show = (variant) => {
        installCard.querySelectorAll('[data-install-variant]').forEach((part) => {
            part.hidden = part.dataset.installVariant !== variant;
        });
        installCard.hidden = false;
    };
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        show('prompt');
    });

    // iPhone and iPad Safari have no install prompt: show how to add it from the Share menu instead.
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    if (isIos) {
        show('ios');
    }

    installCard.querySelector('[data-install-button]')?.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        deferredPrompt = null;

        if (outcome === 'accepted') {
            installCard.hidden = true;
        }
    });

    installCard.querySelector('[data-install-dismiss]')?.addEventListener('click', () => {
        installCard.hidden = true;

        try {
            localStorage.setItem(DISMISSED_KEY, '1');
        } catch {
            // Private browsing: it'll show again next visit, which is fine.
        }
    });

    window.addEventListener('appinstalled', () => {
        installCard.hidden = true;
    });
}
