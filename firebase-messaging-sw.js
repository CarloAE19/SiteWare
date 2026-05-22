/* ==========================================================
 * GB INVENTORY - FIREBASE MESSAGING SERVICE WORKER
 * Handles BACKGROUND push notifications (tab closed / minimized)
 * THIS FILE MUST LIVE AT: /CIMS/firebase-messaging-sw.js
 * ========================================================== */

importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyAGR4gLzookR7GCva3RwlfBITu5KRhvnt0",
    authDomain: "siteware-9fb2f.firebaseapp.com",
    projectId: "siteware-9fb2f",
    storageBucket: "siteware-9fb2f.firebasestorage.app",
    messagingSenderId: "488556756323",
    appId: "1:488556756323:web:53fc4a2f1a2cfa7bd02756"
});

const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Background message received:', payload);

    const title = payload.notification?.title || 'GB Inventory';
    const body = payload.notification?.body || 'You have a new notification.';

    self.registration.showNotification(title, {
        body: body,
        icon: '/CIMS/assets/LogoGB.png',
        badge: '/CIMS/assets/favicon.ico',
        tag: 'gb-inventory-notif',
        renotify: true,
        data: { url: '/CIMS/' }
    });
});

// Click on notification → open / focus the app
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/CIMS/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes('/CIMS/') && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

/* ==========================================================
 * GB INVENTORY — OFFLINE CACHE STRATEGY
 * Pre-caches offline.html on install; serves it as a fallback
 * when a page navigation fails due to no connectivity.
 * ========================================================== */

const OFFLINE_CACHE = 'gb-offline-v2';

const PRECACHE_ASSETS = [
    '/CIMS/offline.html',
    '/CIMS/assets/LogoGB.png',
    '/CIMS/assets/favicon.ico',
    '/CIMS/assets/css/style.css',
    '/CIMS/assets/css/offline.css',
    '/CIMS/assets/js/offline.js',
];

/* ── Install: pre-cache the offline shell ── */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE).then((cache) => {
            console.log('[SW] Pre-caching offline assets');
            return cache.addAll(PRECACHE_ASSETS);
        })
    );
    /* Immediately take control — don't wait for old SW to expire */
    self.skipWaiting();
});

/* ── Activate: purge stale caches ── */
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k !== OFFLINE_CACHE)
                    .map((k) => {
                        console.log('[SW] Deleting old cache:', k);
                        return caches.delete(k);
                    })
            )
        )
    );
    /* Take control of all clients immediately */
    self.clients.claim();
});

/* ── Fetch: network-first, fallback to offline.html for navigations ── */
self.addEventListener('fetch', (event) => {
    /* Only handle GET requests; skip non-http */
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith('http')) return;

    /* Page navigation → network-first, fallback to offline.html */
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                console.log('[SW] Navigation failed — serving offline.html');
                return caches.match('/CIMS/offline.html');
            })
        );
        return;
    }

    /* Static assets (styles, images, fonts) → cache-first */
    if (
        event.request.destination === 'style' ||
        event.request.destination === 'image' ||
        event.request.destination === 'font'
    ) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                return (
                    cached ||
                    fetch(event.request).then((response) => {
                        /* Cache same-origin assets only */
                        if (
                            response.ok &&
                            event.request.url.startsWith(self.location.origin)
                        ) {
                            const clone = response.clone();
                            caches.open(OFFLINE_CACHE).then((cache) =>
                                cache.put(event.request, clone)
                            );
                        }
                        return response;
                    })
                );
            })
        );
    }
});
