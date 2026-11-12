// Sheets first: their Back handler must be registered before Turbo's (shell.js starts Turbo), so a Back that
// closes a sheet never reaches Turbo as a page visit.
import { openSheet } from './sheets';
import { eachOnPage, onPage } from './shell';
import { refreshPage, toast } from './live';
import './celebrate';
import './loader';
import './cards';

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
        } catch {
            // Offline for a moment, or leaving the page mid-request: the next poll picks it up.
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
        } catch {
            // No connection: the message stays in the box so it can be sent again.
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

        const response = await fetch(deleteForm.action, { method: 'POST', headers, body: new FormData(deleteForm) }).catch(() => null);

        if (response?.ok) {
            deleteForm.closest('[data-message-id]')?.remove();
        }
    });

    scrollToEnd();

    // Pages swap in place now, so stop polling once this chat is no longer on the page.
    const whileOnPage = () => {
        if (!chat.isConnected) {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', whileOnPage);

            return;
        }

        poll();
    };
    const timer = window.setInterval(whileOnPage, POLL_INTERVAL_MS);
    document.addEventListener('visibilitychange', whileOnPage);
}

eachOnPage('[data-league-chat]', setUpLeagueChat);

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

eachOnPage('[data-download-card]', (button) => {
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

eachOnPage('[data-detect-timezone]', (field) => {
    if (!field.value) {
        field.value = browserTimezone;
    }
});

eachOnPage('[data-use-browser-timezone]', (button) => {
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

const isInstalled = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
const DISMISSED_KEY = 'goalline:install-dismissed';

const wasDismissed = () => {
    try {
        return localStorage.getItem(DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
};

const showInstallVariant = (installCard, variant) => {
    installCard.querySelectorAll('[data-install-variant]').forEach((part) => {
        part.hidden = part.dataset.installVariant !== variant;
    });
    installCard.hidden = false;
};

// The browser offers its install prompt once per full page load; keep it for whichever page shows the card.
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;

    if (!wasDismissed()) {
        document.querySelectorAll('[data-install-card]').forEach((installCard) => showInstallVariant(installCard, 'prompt'));
    }
});

eachOnPage('[data-install-card]', (installCard) => {
    if (isInstalled || wasDismissed()) {
        return;
    }

    const show = (variant) => showInstallVariant(installCard, variant);

    if (deferredPrompt) {
        show('prompt');
    }

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
});

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
            item.innerHTML = '<span class="font-semibold text-ink"></span> <span class="text-ink/70"></span>';
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

eachOnPage('[data-mention-input]', setUpMentions);

/**
 * Push notifications on this device: subscribe through the service worker and tell GoalLine where to send.
 * iPhone supports this only when GoalLine is opened from the home screen.
 */

eachOnPage('[data-push-panel]', (pushPanel) => {
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
});

const NUDGE_DISMISSED_KEY = 'goalline:push-nudge-dismissed';

eachOnPage('[data-push-nudge]', (pushNudge) => {
    if (!('PushManager' in window && 'Notification' in window && Notification.permission === 'default')) {
        return;
    }

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
});

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
            <p class="text-xs text-ink/70">Drag to move. Pinch, scroll, or use the slider to zoom.</p></div>
        <div class="bg-ink/5 ${shape.round ? 'crop-round' : ''}" style="height: min(60vh, 24rem)"><img alt="" class="block max-w-full"></div>
        <div class="flex items-center gap-3 px-5 py-3">
            <label class="flex flex-1 items-center gap-2 text-xs text-ink/70">Zoom <input type="range" min="0" max="1" step="0.01" value="0" class="flex-1 accent-club" data-zoom></label>
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

eachOnPage('input[type="file"][data-crop]', (input) => {
    input.addEventListener('change', () => openCropper(input));
});

/**
 * The account menu closes when you click anywhere else or press Escape.
 */
eachOnPage('[data-account-menu]', (menu) => {
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
// Delegated, so it keeps working on parts of the page that update in place.
document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-open-proof]');
    const panel = link && document.getElementById(link.dataset.openProof);

    if (panel) {
        event.preventDefault();
        panel.hidden = false;
        panel.querySelector('input[type="file"]')?.focus();
        link.hidden = true;
    }
});

/**
 * "Suggest a caption": ask Claude for one and drop it in the box to edit. It never posts anything.
 */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-suggest-caption]');

    if (!button) {
        return;
    }

    const status = button.parentElement.querySelector('[data-suggest-status]');
    const box = document.getElementById(button.dataset.target);

    button.disabled = true;
    status.textContent = 'Thinking…';

    try {
        const response = await fetch(button.dataset.suggestCaption, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await response.json();

        if (data.caption && box) {
            box.value = data.caption;
            box.focus();
            status.textContent = 'Edit it however you like.';
        } else {
            status.textContent = data.message || "Couldn't suggest one right now. Write your own!";
        }
    } catch {
        status.textContent = "Couldn't suggest one right now. Write your own!";
    } finally {
        button.disabled = false;
    }
});

/**
 * Copy and Share, for every invite link, sign-up link, code and calendar link (see components/share-link).
 *
 * - data-copy-from="field-id" copies that field's value (the full link); data-copy-text="…" copies the text itself.
 * - Copied: the button reads "Copied ✓" for a moment and a "Link copied" toast confirms it.
 * - Clipboard blocked (some in-app browsers, older Safari): the link is shown, selected and focused so it can be
 *   copied by hand, with a short note saying so.
 * - data-share-message opens the phone's share sheet where there is one ("Join my GoalLine league, …: https://…").
 *
 * Listeners sit on the document, so buttons inside regions that update in place keep working.
 */
const copyLabelReset = new WeakMap();

function showCopied(button) {
    button.dataset.label ??= button.textContent.trim();
    button.textContent = 'Copied ✓';
    button.setAttribute('data-copied', '');
    window.clearTimeout(copyLabelReset.get(button));
    copyLabelReset.set(button, window.setTimeout(() => {
        button.textContent = button.dataset.label;
        button.removeAttribute('data-copied');
    }, 2000));
}

function copyByHand(button, field, text) {
    const box = button.closest('[data-share-link]');

    if (field) {
        field.classList.remove('sr-only');
        field.hidden = false;
        field.focus();
        field.select();
        field.setSelectionRange?.(0, field.value.length);
        box?.querySelector('[data-copy-fallback]')?.removeAttribute('hidden');
        toast('Copying is blocked here. The link is selected: copy it by hand.', { tone: 'warning' });

        return;
    }

    window.prompt('Copy this:', text);
}

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-from], [data-copy-text]');

    if (!button) {
        return;
    }

    const field = button.dataset.copyFrom ? document.getElementById(button.dataset.copyFrom) : null;
    const text = field ? field.value : button.dataset.copyText;

    try {
        if (!navigator.clipboard?.writeText || !window.isSecureContext) {
            throw new Error('Clipboard unavailable');
        }

        await navigator.clipboard.writeText(text);
        showCopied(button);
        toast(button.dataset.copyToast || 'Link copied');
    } catch {
        copyByHand(button, field, text);
    }
});

function revealShareButtons(root = document) {
    if (!navigator.share) {
        return;
    }

    root.querySelectorAll('[data-share-message]').forEach((button) => {
        button.hidden = false;
    });
}

onPage(() => revealShareButtons());
document.addEventListener('goalline:updated', (event) => revealShareButtons(event.target));

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-share-message]');

    if (!button || !navigator.share) {
        return;
    }

    // The message carries the link itself, so every app (iMessage, WhatsApp, email) shows the same thing.
    navigator.share({ title: button.dataset.shareTitle, text: button.dataset.shareMessage }).catch(() => {});
});

/**
 * Intake questions, one at a time with a progress bar. "I'm not sure" and "Skip" mark the question and move on;
 * answering clears the mark. Without this script every question shows at once and blank answers count as skipped.
 */
eachOnPage('[data-intake]', (form) => {
    const questions = [...form.querySelectorAll('[data-intake-question]')];
    const current = form.querySelector('[data-intake-current]');
    const bar = form.querySelector('[data-intake-bar]');
    const firstWithError = questions.findIndex((question) => question.querySelector('.text-red-700'));
    let step = Math.max(0, firstWithError);

    const show = (index) => {
        step = Math.min(Math.max(index, 0), questions.length - 1);
        questions.forEach((question, i) => (question.hidden = i !== step));
        current.textContent = step + 1;
        bar.style.width = `${Math.round(((step + 1) / questions.length) * 100)}%`;
        questions[step].querySelector('input:not([type="hidden"])')?.focus({ preventScroll: true });
    };

    const refreshMarks = (question) => {
        const status = question.querySelector('[data-intake-status]').value;
        question.querySelectorAll('[data-intake-mark]').forEach((button) => button.toggleAttribute('data-active', button.dataset.intakeMark === status));
    };

    questions.forEach((question, index) => {
        const status = question.querySelector('[data-intake-status]');
        const next = question.querySelector('[data-intake-next]');
        const back = question.querySelector('[data-intake-back]');

        next.hidden = index === questions.length - 1;
        back.hidden = index === 0;
        next.addEventListener('click', () => show(index + 1));
        back.addEventListener('click', () => show(index - 1));

        question.querySelectorAll('[data-intake-mark]').forEach((button) => {
            button.addEventListener('click', () => {
                status.value = status.value === button.dataset.intakeMark ? '' : button.dataset.intakeMark;
                refreshMarks(question);

                if (status.value && index < questions.length - 1) {
                    show(index + 1);
                }
            });
        });

        question.addEventListener('input', (event) => {
            if (event.target !== status) {
                status.value = '';
                refreshMarks(question);
            }
        });

        question.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target.matches('input[type="text"], input[type="number"]') && index < questions.length - 1) {
                event.preventDefault();
                show(index + 1);
            }
        });

        refreshMarks(question);
    });

    form.querySelector('[data-intake-progress]').hidden = false;
    show(step);
});

/**
 * Matchup pager: one page per matchup. Swipe (native scroll snapping), use the arrows, dots and scoreboard rows, or
 * the left and right arrow keys. The track is as tall as the page in view, so a short page doesn't leave a gap. On
 * phones, a sticky toggle shows one player's tasks at a time. Without this script, it's a horizontal scroller.
 */
// The line: tapping a player's puck (or its button) opens that player's details; tapping again closes them.
eachOnPage('[data-matchup-line]', (line) => {
    line.addEventListener('click', (event) => {
        const control = event.target.closest('[data-line-puck], [data-line-toggle]');
        const detail = control && document.getElementById(control.getAttribute('aria-controls'));

        if (!detail) {
            return;
        }

        const open = detail.hidden;
        line.querySelectorAll('[data-line-detail]').forEach((each) => { each.hidden = true; });
        line.querySelectorAll('[aria-controls]').forEach((each) => each.setAttribute('aria-expanded', 'false'));
        detail.hidden = !open;
        line.querySelectorAll(`[aria-controls="${detail.id}"]`).forEach((each) => each.setAttribute('aria-expanded', String(open)));
    });
});

eachOnPage('[data-pager]', (pager) => {
    const track = pager.querySelector('[data-pager-track]');
    const pages = () => [...track.querySelectorAll('[data-pager-page]')];
    const dots = [...pager.querySelectorAll('[data-pager-dot]')];
    const count = pager.querySelector('[data-pager-count]');
    const prev = pager.querySelector('[data-pager-prev]');
    const next = pager.querySelector('[data-pager-next]');
    const toggle = pager.querySelector('[data-player-toggle]');
    const tabs = [...pager.querySelectorAll('[data-player-tab]')];
    const phone = window.matchMedia('(max-width: 767px)');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let player = 0;

    // While the big scoreboard is on screen, the sticky strip stays hidden (CSS reads data-hero-in-view).
    const heroWatch = 'IntersectionObserver' in window
        ? new IntersectionObserver(([entry]) => pager.toggleAttribute('data-hero-in-view', entry.isIntersecting), { rootMargin: '-56px 0px 0px 0px' })
        : null;
    const firstHero = pager.querySelector('[data-pager-hero]:not([hidden])');
    if (firstHero && heroWatch) {
        pager.setAttribute('data-hero-in-view', '');
        heroWatch.observe(firstHero);
    }

    const fitHeight = () => {
        const page = pages()[current];

        if (page) {
            track.style.height = `${Math.max(page.offsetHeight, page.scrollHeight)}px`; // All of the page, never clipped.
        }
    };

    // Switching days or opening the check-in log changes a page's height: the track follows.
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(() => fitHeight());
        pages().forEach((page) => observer.observe(page));
    }

    const showPlayer = () => {
        const page = pages()[current];
        const names = JSON.parse(page?.dataset.sideNames || '[]');
        const twoSided = names.length > 1;

        if (toggle) {
            toggle.hidden = !twoSided;
            tabs.forEach((tab, index) => {
                tab.textContent = names[index] ? `${names[index]}'s tasks` : '';
                tab.setAttribute('aria-selected', String(index === player));
            });
        }

        pages().forEach((each) => each.querySelectorAll('[data-player-panel]').forEach((panel) => {
            panel.hidden = phone.matches && twoSided && Number(panel.dataset.playerPanel) !== player;
        }));
        fitHeight();
    };

    const mark = (index) => {
        current = index;
        dots.forEach((dot, i) => dot.toggleAttribute('data-active', i === index));
        dots.forEach((dot, i) => (i === index ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
        count.textContent = `${index + 1} of ${pages().length}`;
        prev.disabled = index === 0;
        next.disabled = index === pages().length - 1;
        // The big scoreboard and the sticky strip follow the page in view.
        document.querySelectorAll('[data-pager-strip]').forEach((strip) => {
            strip.hidden = Number(strip.dataset.pagerStrip) !== index;
        });
        document.querySelectorAll('[data-pager-hero]').forEach((hero) => {
            hero.hidden = Number(hero.dataset.pagerHero) !== index;
            if (!hero.hidden) {
                heroWatch?.disconnect();
                heroWatch?.observe(hero);
            }
        });
        showPlayer();
    };

    const go = (index) => {
        const page = pages()[Math.min(Math.max(index, 0), pages().length - 1)];
        track.scrollTo({ left: page.offsetLeft - track.offsetLeft, behavior: reduced.matches ? 'auto' : 'smooth' });
        mark(pages().indexOf(page));
    };

    let settle;
    track.addEventListener('scroll', () => {
        window.clearTimeout(settle);
        settle = window.setTimeout(() => {
            const index = Math.round(track.scrollLeft / Math.max(track.clientWidth, 1));

            if (index !== current) {
                mark(Math.min(index, pages().length - 1));
            }
        }, 60);
    }, { passive: true });

    prev.addEventListener('click', () => go(current - 1));
    next.addEventListener('click', () => go(current + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', (event) => {
        event.preventDefault();
        go(index);
    }));
    document.querySelectorAll('[data-pager-jump]').forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        pager.scrollIntoView({ behavior: reduced.matches ? 'auto' : 'smooth', block: 'start' });
        go(Number(link.dataset.pagerJump));
    }));

    // Left and right arrow keys flip pages, unless someone is typing or a sheet is open.
    document.addEventListener('keydown', (event) => {
        // A pager from a page you've left does nothing.
        if (!pager.isConnected) {
            return;
        }

        if (!['ArrowLeft', 'ArrowRight'].includes(event.key) || event.altKey || event.metaKey || event.ctrlKey
            || event.target.closest('input, textarea, select, [contenteditable], dialog[open]')) {
            return;
        }

        event.preventDefault();
        go(current + (event.key === 'ArrowRight' ? 1 : -1));
    });

    tabs.forEach((tab, index) => tab.addEventListener('click', () => {
        player = index;
        showPlayer();
    }));

    phone.addEventListener('change', showPlayer);
    new ResizeObserver(fitHeight).observe(track);
    pages().forEach((page) => new ResizeObserver(fitHeight).observe(page));
    document.addEventListener('goalline:updated', () => window.requestAnimationFrame(showPlayer));

    const fromHash = pages().findIndex((page) => `#${page.id}` === window.location.hash);
    mark(Math.max(fromHash, 0));

    if (fromHash > 0) {
        track.scrollLeft = pages()[fromHash].offsetLeft - track.offsetLeft;
    }
});

/**
 * Task sheet: tapping a task on the pager opens its details in a bottom sheet (a dialog on wider screens). If the
 * sheet can't load, the link opens the task's own page instead.
 */
eachOnPage('[data-task-sheet]', (sheet) => {
    const body = sheet.querySelector('[data-task-sheet-body]');
    let opener = null;

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-task-sheet-open]');

        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || typeof sheet.showModal !== 'function') {
            return;
        }

        event.preventDefault();
        opener = link;

        try {
            const response = await fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            body.innerHTML = await response.text();
            openSheet(sheet);
            body.scrollTop = 0;
            body.dispatchEvent(new CustomEvent('goalline:updated', { bubbles: true }));
        } catch {
            window.location.assign(link.href);
        }
    });

    // A tap on the dimmed backdrop closes it.
    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) {
            sheet.close();
        }
    });

    sheet.addEventListener('close', () => opener?.focus({ preventScroll: true }));
});

/**
 * Touch screens: any link, button or menu toggle smaller than 44 × 44 gets an invisible tap area of that size
 * (.gl-tap in app.css), so small text links and chips are easy to hit without changing how they look. Controls
 * with their own positioned parts or ::after are left alone. Runs again when a menu opens or part of the page updates.
 */
const coarsePointer = window.matchMedia('(pointer: coarse)');

function growTapTargets(root = document) {
    if (!coarsePointer.matches) {
        return;
    }

    root.querySelectorAll('a[href], button, summary, [role="button"], [role="tab"]').forEach((control) => {
        if (control.classList.contains('gl-tap')) {
            return;
        }

        const box = control.getBoundingClientRect();

        if (box.width === 0 || (box.width >= 44 && box.height >= 44) || getComputedStyle(control, '::after').content !== 'none') {
            return;
        }

        if (getComputedStyle(control).position === 'static') {
            // Screen-reader-only text is positioned but takes no space, so it doesn't count.
            const hasPositionedParts = [...control.querySelectorAll('*')].some((part) => !part.classList.contains('sr-only') && getComputedStyle(part).position !== 'static');

            if (hasPositionedParts) {
                return;
            }

            control.style.position = 'relative';
        }

        control.classList.add('gl-tap');
    });
}

onPage(() => growTapTargets());
document.addEventListener('goalline:updated', (event) => growTapTargets(event.target));
document.addEventListener('toggle', (event) => growTapTargets(event.target), true);
coarsePointer.addEventListener('change', () => growTapTargets());

/**
 * Photo pickers: check the photo before it uploads and show it. iPhone converts photos to JPEG for a picker that
 * asks for JPEG, PNG or WebP; anything else (a HEIC file from a computer, say) or a file over the size limit gets a
 * friendly note instead of a failed upload. Pickers with a cropper (data-crop) show their own preview.
 */
function photoNote(input, message) {
    let note = input.parentElement.querySelector('[data-photo-note]');

    if (!note) {
        note = document.createElement('p');
        note.dataset.photoNote = '';
        note.setAttribute('role', 'alert');
        note.className = 'mt-1 text-xs text-red-700';
        input.insertAdjacentElement('afterend', note);
    }

    note.textContent = message;
    note.hidden = !message;
}

document.addEventListener('change', (event) => {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.accept.includes('image/')) {
        return;
    }

    const file = input.files?.[0];
    const allowed = input.accept.split(',').map((type) => type.trim()).filter((type) => type.includes('/'));
    const maxBytes = Number(input.dataset.maxBytes || 5 * 1024 * 1024);
    input.parentElement.querySelector('[data-photo-preview]')?.remove();

    if (!file) {
        photoNote(input, '');

        return;
    }

    if (allowed.length && !allowed.includes(file.type)) {
        input.value = '';
        photoNote(input, 'That photo is in a format GoalLine can\'t open yet (like HEIC). Pick it from your Photos on iPhone, take a screenshot, or save it as a JPG or PNG.');

        return;
    }

    if (file.size > maxBytes) {
        input.value = '';
        photoNote(input, `That photo is ${(file.size / 1048576).toFixed(1)} MB. Pick one under ${Math.round(maxBytes / 1048576)} MB, or take a screenshot of it.`);

        return;
    }

    photoNote(input, '');

    if (!input.hasAttribute('data-crop')) {
        const preview = document.createElement('img');
        preview.dataset.photoPreview = '';
        preview.alt = 'Preview of the photo you picked';
        preview.className = 'mt-2 max-h-40 rounded-xl object-cover ring-1 ring-line';
        preview.src = URL.createObjectURL(file);
        preview.addEventListener('load', () => URL.revokeObjectURL(preview.src), { once: true });
        input.insertAdjacentElement('afterend', preview);
    }
});

/**
 * Show extra fields only for one choice of a select, such as "A new goal for this league". Without this script the
 * fields always show.
 */
eachOnPage('[data-reveal]', (select) => {
    const target = document.getElementById(select.dataset.reveal);
    const sync = () => {
        const shown = select.value === select.dataset.revealValue;
        target.hidden = !shown;
        target.querySelectorAll('input').forEach((input) => (input.required = shown));
    };

    select.addEventListener('change', sync);
    sync();
});

/**
 * "Refine your plan" idea chips fill the box, so a change is one tap plus any specifics.
 */
eachOnPage('[data-refine]', (form) => {
    const box = form.querySelector('textarea');

    form.querySelectorAll('[data-refine-idea]').forEach((chip) => chip.addEventListener('click', () => {
        box.value = box.value.trim() ? `${box.value.trim()} ${chip.dataset.refineIdea}.` : `${chip.dataset.refineIdea}.`;
        box.focus();
    }));
});

/**
 * Show fields only for the matching choice, such as the day chips for "Specific days" or the unit for "A number".
 * data-show-for="name:value1,value2". Without this script every field shows.
 */
eachOnPage('[data-habit-fields]', (fields) => {
    const sync = () => fields.querySelectorAll('[data-show-for]').forEach((element) => {
        const [name, values] = element.dataset.showFor.split(':');
        const checked = fields.querySelector(`input[name="${name}"]:checked`)?.value;
        element.hidden = !values.split(',').includes(checked);
    });

    fields.addEventListener('change', sync);
    sync();
});

/**
 * Segmented controls with in-page panels (components/segmented): show the chosen [data-segment-panel], hide the rest.
 * Delegated, so controls inside regions that update in place keep working.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-segment]');
    const group = button?.closest('[data-segmented]');

    if (!button || !group) {
        return;
    }

    group.querySelectorAll('[data-segment]').forEach((option) => {
        const active = option === button;
        option.setAttribute('aria-selected', active ? 'true' : 'false');
        option.classList.toggle('bg-white', active);
        option.classList.toggle('text-club', active);
        option.classList.toggle('shadow-sm', active);
        option.classList.toggle('text-ink/70', !active);
    });
    group.querySelectorAll('[data-segment-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.segmentPanel !== button.dataset.segment;
    });
});

/* Close any open "+ Start" menu when tapping elsewhere. */
document.addEventListener('click', (event) => {
    document.querySelectorAll('[data-action-menu][open]').forEach((menu) => {
        if (!menu.contains(event.target)) {
            menu.open = false;
        }
    });
});

/**
 * "Check in" from any screen: the floating button opens a bottom sheet with today's list, loaded fresh each time.
 * Its forms are live and refresh from the sheet's own source (see live.js), so the page underneath never reloads.
 */
document.addEventListener('click', async (event) => {
    const opener = event.target.closest('[data-checkin-open]');
    const sheet = opener && document.querySelector('[data-checkin-sheet]');

    if (!sheet) {
        return;
    }

    openSheet(sheet);
    loadCheckInSheet(sheet);
});

/**
 * Load today's list into the sheet: the branded loader shows only if it takes more than a moment, and if the list
 * can't be reached (or takes over 12 seconds) the sheet says so calmly, with "Try again".
 */
async function loadCheckInSheet(sheet) {
    const body = sheet.querySelector('[data-checkin-body]');
    const fill = (selector) => {
        body.replaceChildren(sheet.querySelector(selector).content.cloneNode(true));
    };

    fill('template[data-checkin-loading]');

    try {
        const response = await fetchWithTimeout(sheet.dataset.liveSource, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        body.innerHTML = await response.text();
        body.dispatchEvent(new CustomEvent('goalline:updated', { bubbles: true }));
    } catch {
        fill('template[data-checkin-error]');
        body.querySelector('[data-load-retry]')?.addEventListener('click', () => loadCheckInSheet(sheet), { once: true });
    }
}

/**
 * fetch(), given up after a while so a dead connection turns into "Try again" instead of a loader that never ends.
 */
function fetchWithTimeout(url, options = {}, milliseconds = 12000) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), milliseconds);

    return fetch(url, { ...options, signal: controller.signal }).finally(() => window.clearTimeout(timer));
}

// Tapping the dimmed area outside the sheet closes it.
document.addEventListener('click', (event) => {
    if (event.target.matches?.('dialog[data-checkin-sheet]')) {
        event.target.close();
    }
});

// After checking in from the sheet, bring the page underneath up to date when it closes.
eachOnPage('[data-checkin-sheet]', (sheet) => {
    sheet.addEventListener('close', () => {
        if (sheet.dataset.changed) {
            delete sheet.dataset.changed;
            refreshPage();
        }
    });
});

/**
 * Matchup day chips: show one day's tasks for both players (today by default), or the whole week.
 */
document.addEventListener('click', (event) => {
    const chip = event.target.closest('[data-day-chip], [data-day-all]');
    const controls = chip?.closest('[data-day-controls]');

    if (!controls) {
        return;
    }

    // One set of chips (in the sticky strip) chooses the day on every matchup page.
    const all = chip.hasAttribute('data-day-all');
    controls.querySelectorAll('[data-day-chip]').forEach((each) => each.setAttribute('aria-selected', String(!all && each === chip)));
    controls.querySelector('[data-day-all]')?.setAttribute('aria-pressed', String(all));
    document.querySelectorAll('[data-day-group]').forEach((group) => {
        group.hidden = !all && group.dataset.dayGroup !== chip.dataset.dayChip;
    });
});

/**
 * The floating Check in button tucks away while you scroll down (so it never covers what you're reading or about to
 * tap) and comes back as soon as you scroll up, or at the top of the page.
 */
(() => {
    let last = window.scrollY;
    window.addEventListener('scroll', () => {
        // Looked up each time: each page has its own button (or none).
        const button = document.querySelector('[data-checkin-open]');

        if (!button) {
            return;
        }

        const y = window.scrollY;
        const down = y > last + 4;
        const up = y < last - 4;

        if (down && y > 80) {
            button.setAttribute('data-tucked', '');
        } else if (up || y <= 80) {
            button.removeAttribute('data-tucked');
        }

        if (down || up) {
            last = y;
        }
    }, { passive: true });
})();

/**
 * Folded GoalWeeks open on their own when a link points into them (#goalweek-4, a proof panel, an error), in every
 * browser. Also after a live update brings the page back.
 */
function openFoldedTarget() {
    const target = window.location.hash ? document.getElementById(decodeURIComponent(window.location.hash.slice(1))) : null;
    const fold = target?.closest('details[data-week-fold]');

    if (fold && !fold.open) {
        fold.open = true;
        target.scrollIntoView({ block: 'start' });
    }
}

onPage(openFoldedTarget);
window.addEventListener('hashchange', openFoldedTarget);

/**
 * No double submits on plain forms (live forms have their own guard in live.js): the second submit is ignored and
 * the button shows it's working until the next page loads. If another script handles the form itself, this steps
 * back. Coming back with the Back button re-enables everything.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;
    const method = (form.getAttribute('method') || 'get').toLowerCase();

    if (event.defaultPrevented || form.hasAttribute('data-live') || method !== 'post' || form.target === '_blank') {
        return;
    }

    if (form.dataset.submitting) {
        event.preventDefault();

        return;
    }

    form.dataset.submitting = '1';
    window.setTimeout(() => {
        if (event.defaultPrevented) {
            delete form.dataset.submitting;

            return;
        }

        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }, 0);
});

window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    });
});

/**
 * Buttons that open a bottom sheet (data-open-sheet="id"), like "Start a GoalLeague". Without this script they're
 * plain links. Tapping the dimmed area outside a sheet closes it.
 */
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-open-sheet]');
    const sheet = opener && document.getElementById(opener.dataset.openSheet);

    if (sheet && typeof sheet.showModal === 'function' && !event.metaKey && !event.ctrlKey) {
        event.preventDefault();
        openSheet(sheet);

        return;
    }

    if (event.target.matches?.('dialog[data-start-sheet]')) {
        event.target.close();
    }
});
