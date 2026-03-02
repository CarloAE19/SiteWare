# **🏗️ GB Construction & Enterprise \- Smart Inventory & Logistics System**

An enterprise-grade, cloud-ready Construction Inventory Management System (CIMS) tailored for **GB Construction & Enterprise Inc.** This system bridges the gap between project sites, warehousing, and management through real-time data syncing, AI analytics, and cross-platform native push notifications.

## **✨ Key Features**

* **📱 Progressive Web App (PWA):** Fully installable on Windows, macOS, Android, and iOS. Features a custom install hijacker for a seamless user onboarding experience.  
* **🔔 Native Push Notifications (FCM):** Utilizes Firebase Cloud Messaging with a custom-built, pure PHP Google OAuth2 JWT authentication engine (No Composer plugins required). Supports background and foreground cross-platform alerts.  
* **🧠 AI-Powered Analytics:** Integrates Google Gemini AI to analyze 30-day consumption data, predict inventory depletion, and recommend optimal restock dates.  
* **📷 QR Code Integration:** Built-in HTML5 QR/Barcode scanning for rapid material receiving and withdrawal slip verification.  
* **✉️ SMS Blaster Integration:** Automated SMS dispatching for Purchase Order tracking and logistics notifications to suppliers.  
* **☔ Smart Logistics Tracking:** Logs weather/typhoon delays and instantly blasts push notifications to the Supply Chain Management team.  
* **📊 Discrepancy Auditing:** Automated monthly physical count module that recalculates inventory and alerts administration of stock discrepancies.

## **👥 Role-Based Access Control (RBAC)**

The system features 5 distinct user workspaces, each with specific authorizations:

1. **System Admin:** Full system configuration, user management, and global oversight.  
2. **Management / Approver:** Access to AI analytics, high-level reporting, and Requisition Slip (RS) approvals.  
3. **Purchasing Officer:** Manages the Supplier database, generates Purchase Orders (POs), and handles logistics tracking/SMS.  
4. **Warehouse In-Charge:** Manages stock-in/stock-out, executes physical inventory audits, and operates the QR scanner.  
5. **Requestor (Project Engineer):** Generates Material Requisitions (RS) for specific construction projects.

## **🛠️ Technical Stack**

* **Frontend:** HTML5, CSS3, JavaScript (ES6), Bootstrap 5, Bootstrap Icons.  
* **Backend:** PHP 8+ (PDO for secure database interactions).  
* **Database:** MySQL / MariaDB.  
* **APIs & Integrations:** \* Firebase Cloud Messaging (FCM API v1)  
  * Google Gemini AI API  
  * HTML5-QRCode Library  
  * QRServer API

## **🚀 Installation & Setup**

### **1\. Local Environment Requirements**

* XAMPP / WAMP / MAMP installed on your machine.  
* PHP 8.0 or higher.  
* MySQL Database.

### **2\. Repository Setup**

1. Clone this repository into your local web server directory (e.g., htdocs or www).  
   git clone \[https://github.com/carloae19/cims.git\](https://github.com/carloae19/cims.git)

2. Import the database layout (ensure you have the gb\_inventory database created in phpMyAdmin).  
3. Update Connection/db.php with your local database credentials.

### **3\. Firebase Push Notifications Configuration**

Because this system uses enterprise-grade FCM v1 security, you must provide your own Firebase credentials:

1. Create a project in the [Firebase Console](https://console.firebase.google.com/).  
2. Add a Web App to the project to get your firebaseConfig. Update assets/js/fcm.js and firebase-messaging-sw.js with these details.  
3. Go to **Project Settings \> Cloud Messaging** and generate a **VAPID Key**. Add this to assets/js/fcm.js.  
4. Go to **Project Settings \> Service Accounts** and generate a new private key.  
5. Rename the downloaded file to firebase-key.json and place it securely inside the process/ directory.

### **4\. Testing Web Push on Mobile (Localhost Bypass)**

Browsers require HTTPS for Service Workers and Push Notifications. To test on a mobile device locally:

* **Option A:** Use [Ngrok](https://ngrok.com/) to tunnel your local port 80 to a secure HTTPS URL.  
* **Option B (Android):** Open chrome://flags on your Android phone, search for "Insecure origins treated as secure", and add your IPv4 address (e.g., http://192.168.x.x).

## **🛡️ Security Implementations**

* **Password Hashing:** Utilizing PHP's native password\_hash() (Bcrypt).  
* **SQL Injection Prevention:** 100% adherence to PDO Prepared Statements.  
* **Directory Protection:** .htaccess routing prevents direct folder browsing and hides .php extensions from the URL.

## **👨‍💻 Development Team: The MedYas**

Project Manager: Jahzeel James Jakosalem

Developer: Angelo Carlo Pedrosa

Quality Assurance: LJ Caballero