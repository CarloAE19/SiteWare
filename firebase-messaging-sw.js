/* ==========================================================
 * GB INVENTORY - FIREBASE MESSAGING SERVICE WORKER
 * Handles BACKGROUND push notifications (tab closed / minimized)
 * Dynamically resolves base path for both local (/CIMS/) and production (/)
 * ========================================================== */

// Dynamically determine the base path from the Service Worker's own location
// e.g. '/CIMS' when hosted in a subfolder on XAMPP, or '' when hosted at root on Hostinger
const swBasePath = self.location.pathname.replace(/\/firebase-messaging-sw\.js.*$/, '');
const swUrl = (path) => (swBasePath ? `${swBasePath}${path}` : path);

// Defensive script importation (ensures offline worker bootstrap never crashes if CDN is unreachable)
try {
    importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
    importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');
} catch (err) {
    console.warn('[SW] Offline or unable to load Firebase SDK:', err);
}

let messaging = null;

if (typeof firebase !== 'undefined' && typeof firebase.initializeApp === 'function') {
    try {
        if (!firebase.apps.length) {
            firebase.initializeApp({
                apiKey: "AIzaSyAGR4gLzookR7GCva3RwlfBITu5KRhvnt0",
                authDomain: "siteware-9fb2f.firebaseapp.com",
                projectId: "siteware-9fb2f",
                storageBucket: "siteware-9fb2f.firebasestorage.app",
                messagingSenderId: "488556756323",
                appId: "1:488556756323:web:53fc4a2f1a2cfa7bd02756"
            });
        }
        messaging = firebase.messaging();
    } catch (err) {
        console.error('[SW] Firebase messaging initialization error:', err);
    }
}

// Handle background push messages
if (messaging) {
    messaging.onBackgroundMessage((payload) => {
        console.log('[SW] Background message received:', payload);

        const title = payload.notification?.title || payload.data?.title || 'GB Inventory';
        const body = payload.notification?.body || payload.data?.body || 'You have a new inventory update.';
        const targetUrl = payload.data?.url || payload.data?.click_action || swUrl('/');
        const notificationTag = payload.data?.tag || 'gb-inventory-notif';

        self.registration.showNotification(title, {
            body: body,
            icon: swUrl('/assets/LogoGB.png'),
            badge: swUrl('/assets/favicon.ico'),
            tag: notificationTag,
            renotify: true,
            vibrate: [200, 100, 200],
            data: { url: targetUrl }
        });
    });
}

// Click on notification → focus tab and navigate to destination
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || swUrl('/');

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                const matchesScope = swBasePath ? client.url.includes(swBasePath) : true;
                if (matchesScope && 'focus' in client) {
                    if (targetUrl && 'navigate' in client && !client.url.endsWith(targetUrl)) {
                        client.navigate(targetUrl);
                    }
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
 * GB INVENTORY — OFFLINE CACHE STRATEGY (v4)
 * Supports full offline app shell, CDN asset caching,
 * and SPA route caching (Dashboard -> PO -> Requisitions)
 * ========================================================== */

const OFFLINE_CACHE = 'gb-offline-v4';

// Core local assets required for the offline application shell (dynamically mapped with swUrl)
const PRECACHE_LOCAL_ASSETS = [
    '/offline.html',
    '/assets/LogoGB.png',
    '/assets/favicon.ico',
    '/assets/css/style.css',
    '/assets/css/custom.css',
    '/assets/css/offline.css',
    '/assets/js/offline.js',
    '/assets/js/pwa.js',
    '/assets/js/router.js',
    '/assets/js/modals.js',
    '/assets/js/inventory.js',
    '/assets/js/notifications.js',
    '/assets/js/fcm.js',
    '/manifest.json'
].map(swUrl);

// Essential vendor CDN assets for styling, typography, and interactive alerts
const PRECACHE_CDN_ASSETS = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap'
];

/* ── Install: pre-cache local shell & vendor CDNs ── */
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(OFFLINE_CACHE).then(async (cache) => {
            console.log('[SW] Pre-caching v4 offline assets');
            
            // 1. Cache same-origin local assets
            try {
                await cache.addAll(PRECACHE_LOCAL_ASSETS);
            } catch (err) {
                console.warn('[SW] Some local assets failed during addAll:', err);
            }

            // 2. Pre-cache CDN assets defensively so external network blips don't fail SW installation
            await Promise.allSettled(
                PRECACHE_CDN_ASSETS.map((cdnUrl) =>
                    fetch(cdnUrl, { mode: 'cors' })
                        .then((res) => {
                            if (res.ok || res.type === 'opaque') {
                                return cache.put(cdnUrl, res);
                            }
                        })
                        .catch((err) => console.warn('[SW] CDN asset pre-cache failed:', cdnUrl, err))
                )
            );
        })
    );
    /* Immediately activate updated SW */
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
                        console.log('[SW] Deleting stale cache:', k);
                        return caches.delete(k);
                    })
            )
        )
    );
    /* Claim all active clients immediately */
    self.clients.claim();
});

/* ── Fetch: intelligent route & asset handling ── */
self.addEventListener('fetch', (event) => {
    /* Only handle GET requests; skip unsupported protocols */
    if (event.request.method !== 'GET') return;
    if (!event.request.url.startsWith('http')) return;

    const url = new URL(event.request.url);

    // Bypass backend action controllers, process scripts, and logout from page-shell caching
    const isBackendAction =
        url.pathname.includes('/process/') ||
        url.pathname.includes('/controllers/') ||
        url.pathname.includes('/api/') ||
        url.pathname.includes('logout');

    if (isBackendAction) {
        return; // Allow network to execute without service worker caching
    }

    const isSameOrigin = url.origin === self.location.origin;
    const acceptHeader = event.request.headers.get('Accept') || '';
    const isHtmlRequest =
        event.request.mode === 'navigate' ||
        acceptHeader.includes('text/html') ||
        (isSameOrigin && !url.pathname.includes('.') && !url.pathname.endsWith('/'));

    /* ── 1. HTML Pages & SPA Navigation (Dashboard, PO, Requisitions, etc.) → Network-First with Cache Fallback ── */
    if (isHtmlRequest && isSameOrigin) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    // Valid server response → save clone in offline cache
                    if (response.ok && response.status === 200) {
                        const clone = response.clone();
                        caches.open(OFFLINE_CACHE).then((cache) => {
                            cache.put(event.request, clone);
                        });
                    }
                    return response;
                })
                .catch(async () => {
                    console.log('[SW] Network unavailable. Attempting cache fallback for:', event.request.url);
                    const cache = await caches.open(OFFLINE_CACHE);

                    // 1. Direct match
                    let cached = await cache.match(event.request);

                    // 2. Extension alias match (e.g. /po vs /po.php)
                    if (!cached) {
                        if (url.pathname.endsWith('.php')) {
                            const cleanUrl = url.origin + url.pathname.replace(/\.php$/, '') + url.search;
                            cached = await cache.match(cleanUrl);
                        } else {
                            const phpUrl = url.origin + url.pathname + '.php' + url.search;
                            cached = await cache.match(phpUrl);
                        }
                    }

                    if (cached) {
                        return cached;
                    }

                    // 3. Fallback to offline.html for full browser navigations
                    if (event.request.mode === 'navigate') {
                        const fallback = await cache.match(swUrl('/offline.html'));
                        if (fallback) return fallback;
                    }

                    // 4. Fallback snippet for AJAX/SPA routing when route was never cached
                    return new Response(
                        `<div class="card shadow-sm border-0 m-4">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-wifi-off text-danger" style="font-size: 3rem;"></i>
                                <h4 class="fw-bold mt-3 text-dark">Section Not Available Offline</h4>
                                <p class="text-muted">This page has not been cached yet. Please reconnect to access it.</p>
                                <button class="btn btn-brand btn-sm mt-2" onclick="window.location.reload()">
                                    <i class="bi bi-arrow-clockwise me-1"></i> Retry Connection
                                </button>
                            </div>
                        </div>`,
                        {
                            status: 200,
                            headers: { 'Content-Type': 'text/html; charset=utf-8' }
                        }
                    );
                })
        );
        return;
    }

    /* ── 2. Static Assets (CSS, JS, Fonts, Images from local or CDNs) → Cache-First with Dynamic Cache ── */
    const isStaticAsset =
        event.request.destination === 'style' ||
        event.request.destination === 'script' ||
        event.request.destination === 'image' ||
        event.request.destination === 'font' ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.woff2') ||
        url.pathname.endsWith('.woff') ||
        url.hostname === 'cdn.jsdelivr.net' ||
        url.hostname === 'cdnjs.cloudflare.com' ||
        url.hostname === 'fonts.googleapis.com' ||
        url.hostname === 'fonts.gstatic.com';

    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                if (cached) return cached;

                return fetch(event.request)
                    .then((response) => {
                        if (response.ok || response.type === 'opaque') {
                            const clone = response.clone();
                            caches.open(OFFLINE_CACHE).then((cache) => {
                                cache.put(event.request, clone);
                            });
                        }
                        return response;
                    })
                    .catch(() => {
                        // Fallback placeholder for missing images offline
                        if (event.request.destination === 'image') {
                            return caches.match(swUrl('/assets/LogoGB.png'));
                        }
                    });
            })
        );
        return;
    }
});
