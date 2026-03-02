/* ==========================================================
 * GB INVENTORY - FIREBASE CLOUD MESSAGING (FRONTEND)
 * Apple iOS Compliant - Requires User Gesture for Permission
 * ========================================================== */

if (typeof firebase === 'undefined') {
    console.error("🔥 FIREBASE ERROR: The Firebase scripts did not load.");
} else {
    // 1. Initialize Firebase with your exact config
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

    // Your actual VAPID KEY
    const VAPID_KEY = "BBRBVfnmWYg0_panaR-Un_rjJTS32gI4HWwwrcgN8x-OJoGtUbUCnTfYPGxWZ6robct9svwqOcArRz0B-LzCVFE"; 

    async function requestNotificationPermission() {
        try {
            console.log("Asking browser for permission (Triggered by User)...");
            const permission = await Notification.requestPermission();
            
            if (permission === 'granted') {
                console.log("Permission granted! Registering Service Worker...");

                const swRegistration = await navigator.serviceWorker.register('./firebase-messaging-sw.js', { scope: './' });
                
                // Get the unique Device Token
                const currentToken = await messaging.getToken({ 
                    vapidKey: VAPID_KEY,
                    serviceWorkerRegistration: swRegistration
                });
                
                if (currentToken) {
                    console.log("✅ SUCCESS! Your FCM Token is:", currentToken);
                    saveTokenToDatabase(currentToken);
                } else {
                    console.error("❌ Failed to generate token.");
                }
            } else {
                console.warn("User or Browser blocked the notification popup.");
            }
        } catch (error) {
            console.error("CRITICAL ERROR during token generation:", error);
        }
    }

    // Handle incoming messages when the app is OPEN on the screen
    messaging.onMessage((payload) => {
        console.log('Message received in foreground: ', payload);
        new Audio('assets/sounds/success.mp3').play().catch(e => {});
        alert(`📢 ${payload.notification.title}\n\n${payload.notification.body}`);
    });

    // Function to send token to your PHP backend
    async function saveTokenToDatabase(token) {
        let formData = new FormData();
        formData.append('action', 'save_fcm_token');
        formData.append('fcm_token', token);
        
        try {
            await fetch('process/process_notif.php', { method: 'POST', body: formData });
            console.log("Token successfully saved to database!");
        } catch(e) {
            console.error("Failed to reach process_notif.php", e);
        }
    }

    // Trigger on load
    document.addEventListener("DOMContentLoaded", () => {
        // Silence default install prompt on Dashboard
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); });

        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

        if (isIOS && !isStandalone) {
            setTimeout(() => {
                alert("🍎 iPhone Detected!\n\nTo receive real-time push notifications, you must add this app to your Home Screen.\n\n1. Tap the 'Share' icon at the bottom of Safari.\n2. Tap 'Add to Home Screen'.\n3. Open the new app!");
            }, 1500);
            return; 
        }

        // =========================================================================
        // THE APPLE FIX: Require a physical button tap if permission is 'default'
        // =========================================================================
        if (Notification.permission === 'granted') {
            // Already approved previously, silently fetch the token in the background
            requestNotificationPermission();
        } 
        else if (Notification.permission === 'default') {
            // Create a sleek floating button asking the user to subscribe
            const enableBtn = document.createElement('button');
            enableBtn.innerHTML = '<i class="bi bi-bell-fill me-2"></i> Enable Push Notifications';
            enableBtn.className = 'btn btn-primary position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg';
            enableBtn.style.zIndex = '9999';
            enableBtn.style.borderRadius = '50px';
            enableBtn.style.fontWeight = 'bold';
            
            // When tapped, iOS sees the "User Gesture" and allows the prompt!
            enableBtn.onclick = async () => {
                await requestNotificationPermission();
                enableBtn.remove(); // Hide button after clicking
            };
            
            document.body.appendChild(enableBtn);
        }
    });
}