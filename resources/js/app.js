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
