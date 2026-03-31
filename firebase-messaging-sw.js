/* ==========================================================
 * GB INVENTORY - FIREBASE MESSAGING SERVICE WORKER
 * Handles BACKGROUND push notifications (tab closed / minimized)
 * THIS FILE MUST LIVE AT: /CIMS/firebase-messaging-sw.js
 * ========================================================== */

importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey:            "AIzaSyAGR4gLzookR7GCva3RwlfBITu5KRhvnt0",
    authDomain:        "siteware-9fb2f.firebaseapp.com",
    projectId:         "siteware-9fb2f",
    storageBucket:     "siteware-9fb2f.firebasestorage.app",
    messagingSenderId: "488556756323",
    appId:             "1:488556756323:web:53fc4a2f1a2cfa7bd02756"
});

const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Background message received:', payload);

    const title = payload.notification?.title || 'GB Inventory';
    const body  = payload.notification?.body  || 'You have a new notification.';

    self.registration.showNotification(title, {
        body:    body,
        icon:    '/CIMS/assets/LogoGB.png',
        badge:   '/CIMS/assets/favicon.ico',
        tag:     'gb-inventory-notif',
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
