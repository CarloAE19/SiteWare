importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.8.1/firebase-messaging-compat.js');

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

// The browser handles background notifications natively via fcm_options link!

// messaging.onBackgroundMessage((payload) => {
//   const notificationTitle = payload.notification.title;
//   const notificationOptions = {
//     body: payload.notification.body,
//     icon: 'assets/favicon.ico' // Optional: Add an icon for the notification
//   };
//   self.registration.showNotification(notificationTitle, notificationOptions);
// });