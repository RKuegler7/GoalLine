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

/**
 * @mentions: while typing "@na", suggest matching people and fill in their username.
 */
function setUpMentions(input) {
    let people = [];

    try {
        people = JSON.parse(input.dataset.mentions || '[]');
    } catch {
        return;
    }

    if (people.length === 0) {
        return;
    }

    const list = document.createElement('ul');
    list.className = 'absolute bottom-full left-0 z-20 mb-1 max-h-56 w-64 overflow-y-auto rounded-xl border border-line bg-white py-1 text-sm shadow-lg';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    input.parentElement.append(list);
    let matches = [];
    let active = 0;

    const query = () => {
        const before = input.value.slice(0, input.selectionStart);
        const match = before.match(/(?:^|[^\w.@])@([a-z0-9._]{0,30})$/i);

        return match ? match[1].toLowerCase() : null;
    };

    const close = () => {
        list.hidden = true;
        matches = [];
    };

    const render = () => {
        list.replaceChildren(...matches.map((person, index) => {
            const item = document.createElement('li');
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', index === active ? 'true' : 'false');
            item.className = `cursor-pointer px-3 py-1.5 ${index === active ? 'bg-mint' : 'hover:bg-ivory'}`;
            item.innerHTML = '<span class="font-semibold text-ink"></span> <span class="text-ink/50"></span>';
            item.firstChild.textContent = person.n;
            item.lastChild.textContent = `@${person.u}`;
            item.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(person);
            });

            return item;
        }));
        list.hidden = matches.length === 0;
    };

    const choose = (person) => {
        const caret = input.selectionStart;
        const before = input.value.slice(0, caret).replace(/@([a-z0-9._]{0,30})$/i, `@${person.u} `);
        input.value = before + input.value.slice(caret);
        input.setSelectionRange(before.length, before.length);
        input.focus();
        close();
    };

    input.addEventListener('input', () => {
        const typed = query();

        if (typed === null) {
            close();

            return;
        }

        matches = people.filter((person) => person.u.toLowerCase().startsWith(typed) || person.n.toLowerCase().split(' ').some((part) => part.startsWith(typed))).slice(0, 6);
        active = 0;
        render();
    });

    input.addEventListener('keydown', (event) => {
        if (list.hidden) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            active = (active + (event.key === 'ArrowDown' ? 1 : matches.length - 1)) % matches.length;
            render();
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            event.stopImmediatePropagation();
            choose(matches[active]);
        } else if (event.key === 'Escape') {
            close();
        }
    }, true); // Capture, so picking a name with Enter runs before the chat's Enter-to-send.

    input.addEventListener('blur', () => setTimeout(close, 100));
}

document.querySelectorAll('[data-mention-input]').forEach(setUpMentions);

/**
 * Copy buttons: copy the named field's value.
 */
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const field = document.getElementById(button.dataset.copy);

        try {
            await navigator.clipboard.writeText(field.value);
            button.textContent = 'Copied';
        } catch {
            field.select();
        }
    });
});

/**
 * Push notifications on this device: subscribe through the service worker and tell GoalLine where to send.
 * iPhone supports this only when GoalLine is opened from the home screen.
 */
const pushPanel = document.querySelector('[data-push-panel]');

if (pushPanel) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const vapidKey = pushPanel.dataset.vapidKey;
    const enableButton = pushPanel.querySelector('[data-push-enable]');
    const disableButton = pushPanel.querySelector('[data-push-disable]');
    const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && window.isSecureContext && vapidKey;

    const showState = (state) => {
        pushPanel.querySelectorAll('[data-push-state]').forEach((line) => {
            line.hidden = line.dataset.pushState !== state;
        });
        enableButton.hidden = state !== 'off';
        disableButton.hidden = state !== 'on';
    };

    const toKey = (base64) => {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');

        return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
    };

    const send = (method, body) => fetch('/push-subscriptions', {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body),
    });

    const refresh = async () => {
        if (!supported) {
            showState('unsupported');

            return;
        }

        if (Notification.permission === 'denied') {
            showState('blocked');

            return;
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            // Keep the server's copy current, for example after logging in as someone else on this device.
            await send('POST', subscription.toJSON());
        }

        showState(subscription ? 'on' : 'off');
    };

    enableButton.addEventListener('click', async () => {
        enableButton.disabled = true;

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                showState(permission === 'denied' ? 'blocked' : 'off');

                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: toKey(vapidKey) });
            await send('POST', subscription.toJSON());
            showState('on');
        } catch {
            showState('off');
        } finally {
            enableButton.disabled = false;
        }
    });

    disableButton.addEventListener('click', async () => {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await send('DELETE', { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
        }

        showState('off');
    });

    refresh();
}

const pushNudge = document.querySelector('[data-push-nudge]');
const NUDGE_DISMISSED_KEY = 'goalline:push-nudge-dismissed';

if (pushNudge && 'PushManager' in window && 'Notification' in window && Notification.permission === 'default') {
    let dismissed = false;

    try {
        dismissed = localStorage.getItem(NUDGE_DISMISSED_KEY) === '1';
    } catch {
        // Storage unavailable: show it.
    }

    pushNudge.hidden = dismissed;
    pushNudge.querySelector('[data-push-nudge-dismiss]').addEventListener('click', () => {
        pushNudge.hidden = true;

        try {
            localStorage.setItem(NUDGE_DISMISSED_KEY, '1');
        } catch {
            // Fine: it'll show again next time.
        }
    });
}

/**
 * Opened from the home screen: tell GoalLine once, so the Kickoff checklist can check off "add to home screen".
 */
const INSTALL_REPORTED_KEY = 'goalline:install-reported';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

if (isInstalled && csrfToken && document.querySelector('[data-signed-in]')) {
    let reported = false;

    try {
        reported = localStorage.getItem(INSTALL_REPORTED_KEY) === '1';
    } catch {
        // Storage unavailable: reporting again is harmless.
    }

    if (!reported) {
        fetch('/usage/installed', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => {
                if (response.ok) {
                    try {
                        localStorage.setItem(INSTALL_REPORTED_KEY, '1');
                    } catch {
                        // Fine.
                    }
                }
            })
            .catch(() => {});
    }
}

/**
 * Photo cropping: after picking a profile or cover photo, drag and zoom it into shape before it uploads.
 * The cropped image replaces the chosen file, so the form submits as usual.
 */
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const CROP_SHAPES = {
    avatar: { aspectRatio: 1, width: 600, height: 600, title: 'Crop your profile photo', round: true },
    cover: { aspectRatio: 3, width: 1800, height: 600, title: 'Crop your cover photo', round: false },
};

function openCropper(input) {
    const shape = CROP_SHAPES[input.dataset.crop];
    const file = input.files?.[0];

    if (!shape || !file || !file.type.startsWith('image/')) {
        return;
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'w-[min(36rem,calc(100vw-2rem))] rounded-2xl p-0 backdrop:bg-ink/60';
    dialog.innerHTML = `
        <div class="border-b border-line px-5 py-3"><h2 class="font-serif text-lg font-medium text-club"></h2>
            <p class="text-xs text-ink/55">Drag to move. Pinch, scroll, or use the slider to zoom.</p></div>
        <div class="bg-ink/5 ${shape.round ? 'crop-round' : ''}" style="height: min(60vh, 24rem)"><img alt="" class="block max-w-full"></div>
        <div class="flex items-center gap-3 px-5 py-3">
            <label class="flex flex-1 items-center gap-2 text-xs text-ink/60">Zoom <input type="range" min="0" max="1" step="0.01" value="0" class="flex-1 accent-club" data-zoom></label>
        </div>
        <div class="flex justify-end gap-2 border-t border-line px-5 py-3">
            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-ink/70 hover:text-ink" data-cancel>Cancel</button>
            <button type="button" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark" data-use>Use photo</button>
        </div>`;
    dialog.querySelector('h2').textContent = shape.title;
    document.body.append(dialog);

    const image = dialog.querySelector('img');
    const url = URL.createObjectURL(file);
    image.src = url;
    let cropper = null;
    let baseRatio = 1;

    const close = () => {
        cropper?.destroy();
        URL.revokeObjectURL(url);
        dialog.close();
        dialog.remove();
    };

    image.addEventListener('load', () => {
        cropper = new Cropper(image, {
            aspectRatio: shape.aspectRatio,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            background: false,
            guides: false,
            ready() {
                baseRatio = cropper.getImageData().width / cropper.getImageData().naturalWidth;
            },
        });
    }, { once: true });

    dialog.querySelector('[data-zoom]').addEventListener('input', (event) => {
        cropper?.zoomTo(baseRatio * (1 + Number(event.target.value) * 3));
    });

    dialog.querySelector('[data-cancel]').addEventListener('click', () => {
        input.value = '';
        close();
    });

    dialog.addEventListener('cancel', () => {
        input.value = '';
        close();
    });

    dialog.querySelector('[data-use]').addEventListener('click', () => {
        const canvas = cropper?.getCroppedCanvas({ width: shape.width, height: shape.height, imageSmoothingQuality: 'high', fillColor: '#ffffff' });

        if (!canvas) {
            close();

            return;
        }

        canvas.toBlob((blob) => {
            if (blob) {
                const cropped = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(cropped);
                input.files = transfer.files;

                // Show the result right away in the preview next to the field, if there is one.
                const preview = input.closest('section, form')?.querySelector(shape.round ? 'img.rounded-full' : '[data-cover-preview] img');
                if (preview) {
                    preview.src = URL.createObjectURL(blob);
                }
            }
            close();
        }, 'image/jpeg', 0.9);
    });

    dialog.showModal();
}

document.querySelectorAll('input[type="file"][data-crop]').forEach((input) => {
    input.addEventListener('change', () => openCropper(input));
});

/**
 * The account menu closes when you click anywhere else or press Escape.
 */
document.querySelectorAll('[data-account-menu]').forEach((menu) => {
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary')?.focus();
        }
    });
});

/**
 * "Show your work (optional)": open the proof panel under a checked-off task.
 */
document.querySelectorAll('[data-open-proof]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const panel = document.getElementById(link.dataset.openProof);

        if (panel) {
            event.preventDefault();
            panel.hidden = false;
            panel.querySelector('input[type="file"]')?.focus();
            link.hidden = true;
        }
    });
});
