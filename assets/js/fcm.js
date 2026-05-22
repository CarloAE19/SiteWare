/* ==========================================================
 * GB INVENTORY - FIREBASE CLOUD MESSAGING
 * Active Service Worker Hard Reset Fix
 * ========================================================== */

if (typeof firebase === 'undefined') {
    console.error("🔥 FIREBASE ERROR: The Firebase scripts did not load.");
} else {
    const firebaseConfig = {
      apiKey: "AIzaSyAGR4gLzookR7GCva3RwlfBITu5KRhvnt0",
      authDomain: "siteware-9fb2f.firebaseapp.com",
      projectId: "siteware-9fb2f",
      storageBucket: "siteware-9fb2f.firebasestorage.app",
      messagingSenderId: "488556756323",
      appId: "1:488556756323:web:53fc4a2f1a2cfa7bd02756"
    };

    firebase.initializeApp(firebaseConfig);
    const messaging = firebase.messaging();
    
    // 🚨 REPLACE THIS WITH YOUR NEW VAPID KEY FROM FIREBASE CONSOLE 🚨
    const VAPID_KEY = "BAlVWwzuZaN7XIH7UTpW5vTEqyCAnRnHFTWoILHRo-akfvn2SqKu3MtwNdAQQv11RMt6XQdwCIuAbxV_G1TfAcA"; 

    messaging.onMessage((payload) => {
        console.log('Message received in foreground: ', payload);
        new Audio('assets/sounds/success.mp3').play().catch(e => {}); 
        
        navigator.serviceWorker.getRegistration('/CIMS/').then(reg => {
            if (reg) {
                reg.showNotification(payload.notification.title, {
                    body: payload.notification.body,
                    icon: '/CIMS/assets/LogoGB.png'
                });
            } else {
                alert(`📢 ${payload.notification.title}\n\n${payload.notification.body}`);
            }
        });
    });

    async function requestNotificationPermission() {
        try {
            console.log("1. Requesting user permission...");
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log("2. Permission granted! Registering Service Worker...");
                
                // Force an update with a random version number to kill the ghost worker
                const swPath = `/CIMS/firebase-messaging-sw.js?v=${new Date().getTime()}`;
                const swRegistration = await navigator.serviceWorker.register(swPath, { scope: '/CIMS/' });
                
                // THE FIX: Aggressively wait for the worker to become 'active'
                console.log("3. Waiting for Service Worker to become active...");
                await navigator.serviceWorker.ready;
                console.log("4. Service Worker is ACTIVE! Fetching FCM Token...");
                
                // Get the token using the verified active worker
                const currentToken = await messaging.getToken({ 
                    vapidKey: VAPID_KEY, 
                    serviceWorkerRegistration: swRegistration 
                });
                
                if (currentToken) {
                    console.log("✅ 5. Token Generated Successfully!", currentToken);
                    saveTokenToDatabase(currentToken);
                }
            } else {
                console.warn("❌ User denied notification permissions.");
            }
        } catch (error) {
            console.error("❌ Token Error:", error);
        }
    }

    async function saveTokenToDatabase(token) {
        let formData = new FormData();
        formData.append('action', 'save_fcm_token');
        formData.append('fcm_token', token);
        try {
            let response = await fetch('process/process_notif.php', { method: 'POST', body: formData });
            let data = await response.json();
            if (data.status === 'success') {
                console.log("✅ 6. DATABASE SUCCESS: Token Saved!");
            }
        } catch(e) { }
    }

    document.addEventListener("DOMContentLoaded", () => {
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); });
        
        if (Notification.permission === 'granted') {
            requestNotificationPermission();
        } else if (Notification.permission === 'default') {
            const enableBtn = document.createElement('button');
            enableBtn.innerHTML = '<i class="bi bi-bell-fill me-2"></i> Enable Push Notifications';
            enableBtn.className = 'btn btn-primary position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg';
            enableBtn.style.zIndex = '9999';
            enableBtn.onclick = async () => { 
                await requestNotificationPermission(); 
                enableBtn.remove(); 
            };
            document.body.appendChild(enableBtn);
        }
    });
}