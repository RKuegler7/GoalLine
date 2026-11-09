import './live';

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
 * Copy buttons (invite links and codes), and Share, which opens the phone's share sheet where there is one.
 */
document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            const label = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => (button.textContent = label), 1500);
        } catch {
            window.prompt('Copy this:', button.dataset.copy);
        }
    });
});

document.querySelectorAll('[data-share-url]').forEach((button) => {
    if (!navigator.share) {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', () => {
        navigator.share({ title: button.dataset.shareTitle, text: button.dataset.shareText, url: button.dataset.shareUrl }).catch(() => {});
    });
});

/**
 * Intake questions, one at a time with a progress bar. "I'm not sure" and "Skip" mark the question and move on;
 * answering clears the mark. Without this script every question shows at once and blank answers count as skipped.
 */
document.querySelectorAll('[data-intake]').forEach((form) => {
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
document.querySelectorAll('[data-pager]').forEach((pager) => {
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

    const fitHeight = () => {
        const page = pages()[current];

        if (page) {
            track.style.height = `${page.offsetHeight}px`;
        }
    };

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
document.querySelectorAll('[data-task-sheet]').forEach((sheet) => {
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
            sheet.showModal();
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

growTapTargets();
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
document.querySelectorAll('[data-reveal]').forEach((select) => {
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
document.querySelectorAll('[data-refine]').forEach((form) => {
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
document.querySelectorAll('[data-habit-fields]').forEach((fields) => {
    const sync = () => fields.querySelectorAll('[data-show-for]').forEach((element) => {
        const [name, values] = element.dataset.showFor.split(':');
        const checked = fields.querySelector(`input[name="${name}"]:checked`)?.value;
        element.hidden = !values.split(',').includes(checked);
    });

    fields.addEventListener('change', sync);
    sync();
});
