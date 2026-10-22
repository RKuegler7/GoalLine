// GoalLine service worker: fast repeat visits, a friendly offline screen, and (soon) push reminders.
// Pages themselves are never cached, so nothing personal is left on a shared device after logging out.

const VERSION = 'goalline-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/app-icon.svg', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Pages: always from the network; the offline screen when there is none.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));

        return;
    }

    // Built assets, icons, and fonts never change under the same URL: serve from cache, refresh in the background.
    const isStatic = url.origin === self.location.origin && /^\/(build|icons|fonts)\//.test(url.pathname);
    const isFont = /^https:\/\/fonts\.(googleapis|gstatic)\.com$/.test(url.origin);

    if (isStatic || isFont) {
        event.respondWith(
            caches.open(VERSION).then(async (cache) => {
                const cached = await cache.match(request);
                const network = fetch(request)
                    .then((response) => {
                        if (response.ok) {
                            cache.put(request, response.clone());
                        }

                        return response;
                    })
                    .catch(() => cached);

                return cached || network;
            }),
        );
    }
});

// Push reminders: show the notification, and open the right screen when it's tapped.
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'GoalLine', {
            body: data.body || '',
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag,
            data: { url: data.url || '/dashboard' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((client) => client.url.startsWith(self.location.origin));

            if (open) {
                return open.navigate(target).then((client) => client?.focus());
            }

            return self.clients.openWindow(target);
        }),
    );
});
