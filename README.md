# 🏗️ GB Construction & Enterprise — Smart Inventory & Logistics System (CIMS)

An enterprise-grade, cloud-ready **Construction Inventory Management System (CIMS)** tailored for **GB Construction & Enterprise Inc.** This system bridges the gap between project sites, warehousing, and management through real-time data syncing, AI-powered analytics, cross-platform push notifications, and a fully offline-resilient architecture.

---

## ✨ Key Features

- **📱 Progressive Web App (PWA):** Fully installable on Windows, macOS, Android, and iOS. Features a custom install hijacker for a seamless user onboarding experience, complete with a branded splash screen.
- **🔔 Native Push Notifications (FCM):** Utilizes Firebase Cloud Messaging (FCM API v1) with a custom-built, pure-PHP Google OAuth2 JWT authentication engine — no Composer plugins required. Supports background and foreground cross-platform push alerts triggered by key system events.
- **🧠 AI-Powered Analytics:** Integrates the **NVIDIA NIM API** (LLaMA 3.1 model) to analyze 30-day inventory consumption data, predict stock depletion timelines, account for supplier lead times, and recommend optimal restock dates.
- **📷 QR Code Integration:** Built-in HTML5 QR/Barcode scanning for rapid material receiving and Withdrawal Slip (WS) / Requisition Slip (RS) verification in the warehouse.
- **📊 Physical Count Workstation:** A dedicated weekly physical count module (`physical_count.php`) with a real-time discrepancy calculator, live summary statistics (matching items vs. discrepancies), ±1 stepper controls, and auditor trail logging. Restricted to Warehouse & Admin roles.
- **📜 Audit History Log:** A separate audit history view (`audit.php`) displaying past weekly counts, auditor names, discrepancy totals, and item-level drill-down via modal.
- **📈 Supplier Performance Tracking:** View detailed supplier delivery performance, distinguishing correctly delivered items from discrepancies to evaluate vendor reliability.
- **📜 Historical Supplier Insights:** Requisition items automatically surface previous suppliers, helping Purchasing Officers make informed reordering decisions.
- **✉️ Dynamic SMS Blaster Integration:** Automated SMS dispatching via **httpSMS** for Purchase Order tracking, dynamically generating exact item lists within the SMS payload. Includes inbound SMS reply handling via a webhook.
- **✍️ Digital Signature & Photo Proof on Withdrawals:** Warehouse withdrawals capture the recipient's name, a canvas-drawn digital signature (saved as PNG), and an optional photo proof of receipt, all stored in `uploads/`.
- **⚙️ Automated Schema Provisioning:** Zero-touch database setup. The centralized `Connection/db.php` automatically creates the database and provisions all 17 required tables on first load. Includes auto-patch logic to add missing columns to existing databases without breaking live deployments.
- **🔴 Offline DB Resilience:** When the database server is unreachable, the system gracefully renders an offline banner with a "Retry Connection" button instead of throwing a fatal error. AJAX endpoints return structured JSON error responses.
- **🏠 Role-Specific Dashboards:** Each user role receives a personalized dashboard with tailored KPIs, quick-action shortcuts, a live clock/greeting, and a recent activity feed.

---

## 👥 Role-Based Access Control (RBAC)

The system features **5 distinct user workspaces**, each with specific authorizations:

| Role | Key Responsibilities |
|---|---|
| **System Admin** | Full system configuration, user management, global oversight, and access to all modules. |
| **Management / Approver** | Access to AI analytics, high-level reporting, and Requisition Slip (RS) approvals. |
| **Purchasing Officer** | Manages the Supplier database, generates Purchase Orders (POs), handles SMS logistics tracking. |
| **Warehouse In-Charge** | Manages stock-in/stock-out, executes physical inventory audits, operates the QR scanner, and processes withdrawal slips with digital signature capture. |
| **Requestor (Project Engineer)** | Generates Material Requisition Slips (RS) for specific construction projects. |

---

## 🗂️ Project Structure

```
CIMS/
├── Connection/
│   ├── db.php                  # Core DB connection, auto-migration, and offline handler
│   └── fcm_helper.php          # Firebase FCM push notification helper (pure PHP, no Composer)
├── assets/
│   ├── css/                    # Per-module stylesheets (dashboard, audit, inventory, etc.)
│   ├── js/                     # Per-module JavaScript (audit.js, inventory.js, withdrawals.js, etc.)
│   ├── img/                    # Static images and login background
│   └── sounds/                 # Notification sound assets
├── components/                 # Reusable PHP modal partials (audit_modal, po_modal, withdrawal_modal, etc.)
├── layout/
│   ├── header.php              # Global header: navigation, sidebar, FCM init, session checks
│   └── footer.php              # Global footer: Bootstrap JS, PWA router, notification poller
├── process/
│   ├── process.php             # Central action router (delegates to module handlers)
│   ├── module_audit.php        # Weekly physical count submission logic
│   ├── module_inventory.php    # Inventory CRUD and stock-in logic
│   ├── module_transactions.php # Entry point for transaction actions (delegates to /transactions/)
│   ├── module_suppliers.php    # Supplier CRUD logic
│   ├── module_users.php        # User management logic
│   ├── module_settings.php     # System settings logic
│   ├── chatbot_chat.php        # AI analytics chat endpoint (NVIDIA NIM API)
│   ├── httpsms_webhook.php     # Inbound SMS reply webhook handler
│   ├── process_notif.php       # Notification read/clear endpoint
│   └── transactions/
│       ├── rs_actions.php      # Requisition Slip actions (create, approve, reject, QR fetch)
│       ├── po_actions.php      # Purchase Order actions (create, mark delivered, ETA tracking)
│       ├── withdrawal_actions.php # Withdrawal Slip actions (create with signature/photo proof)
│       ├── sms_actions.php     # SMS dispatch and PO tracking actions
│       └── alert_actions.php   # Low-stock notification and alert actions
├── uploads/
│   └── signatures/             # Stored digital signatures from withdrawal slips (PNG)
├── index.php                   # Inventory management (main stock view)
├── dashboard.php               # Role-specific dashboard with KPIs and shortcuts
├── requisitions.php            # Requisition Slip management
├── po.php                      # Purchase Order management
├── withdrawals.php             # Withdrawal Slip management
├── suppliers.php               # Supplier management
├── audit.php                   # Weekly audit history log
├── physical_count.php          # Physical count workstation (Warehouse / Admin only)
├── analytics.php               # AI-powered inventory analytics interface
├── categories.php              # Inventory category management
├── units.php                   # Unit of measurement management (with reorder levels)
├── projects.php                # Construction project registry
├── users.php                   # User account management (Admin only)
├── profile.php                 # User profile settings
├── about.php                   # System information page
├── login.php                   # Authentication page
├── logout.php                  # Session termination
├── manifest.json               # PWA manifest
├── firebase-messaging-sw.js    # Firebase Service Worker for background push notifications
├── offline.html                # PWA offline fallback page
├── construction_inventory.sql  # Full database dump (for import with sample data)
├── cims_indexes.sql            # Database index definitions for query optimization
└── .env                        # Environment variables (credentials, API keys — not committed)
```

---

## 🗃️ Database Schema (17 Tables — Auto-Provisioned)

| Table | Description |
|---|---|
| `users` | User accounts, roles, and FCM tokens |
| `inventory` | Stock items with item code, quantity, unit price, and status |
| `categories` | Inventory categories |
| `units` | Units of measurement with per-unit reorder thresholds |
| `suppliers` | Supplier master list |
| `requisitions` | Requisition Slips (RS) — project and warehouse restock types |
| `requisition_items` | Line items for each RS |
| `purchase_orders` | Purchase Orders (PO) with ETA and delay tracking |
| `po_items` | Line items for each PO |
| `withdrawals` | Withdrawal Slips with recipient name, signature path, and photo proof path |
| `withdrawal_items` | Line items for each withdrawal |
| `inventory_audits` | Weekly physical count records with auditor and discrepancy count |
| `audit_items` | Item-level discrepancy data for each audit |
| `notifications` | In-app notifications (role-based and user-specific) |
| `supplier_sms_replies` | Inbound and outbound SMS log tied to suppliers and POs |
| `projects` | Active and inactive construction project registry |
| `system_settings` | Key-value store for configurable system options (e.g., login background) |

---

## 🛠️ Technical Stack

| Layer | Technology |
|---|---|
| **Frontend** | HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons |
| **Backend** | PHP 8.0+ (PDO with prepared statements) |
| **Database** | MySQL / MariaDB |
| **AI Analytics** | NVIDIA NIM API (`meta/llama-3.1-8b-instruct`) |
| **Push Notifications** | Firebase Cloud Messaging (FCM API v1), pure-PHP JWT engine |
| **SMS Gateway** | httpSMS API (with inbound webhook support) |
| **QR Scanning** | HTML5-QRCode Library |
| **QR Generation** | QRServer API |
| **PWA** | Web App Manifest + Firebase Service Worker |

---

## 🚀 Installation & Setup

### 1. Local Environment Requirements

- XAMPP / WAMP / MAMP with PHP **8.0 or higher**
- MySQL or MariaDB database server

### 2. Repository Setup

1. Clone this repository into your local web server directory (e.g., `htdocs` or `www`):
   ```bash
   git clone https://github.com/carloae19/cims.git
   ```

2. Create a `.env` file in the root directory with the following keys:
   ```env
   # Database Credentials
   DB_HOST=localhost
   DB_NAME=construction_inventory
   DB_USER=root
   DB_PASS=your_db_password

   # NVIDIA NIM API (for AI Analytics)
   AI_API_KEY=your_nvidia_nim_api_key
   AI_MODEL=meta/llama-3.1-8b-instruct

   # AI System Prompt (customize as needed)
   AI_SYSTEM_PROMPT="You are the GB Construction AI Restock Assistant..."

   # SMS Gateway (httpSMS)
   SMS_API_KEY=your_httpsms_api_key
   SMS_FROM_NUMBER=+639XXXXXXXXX
   SMS_GATEWAY_URL=https://api.httpsms.com/v1/messages/send
   ```

3. **Navigate to your app in a browser.** The system auto-migrates on first load — it creates the `construction_inventory` database and all 17 tables automatically. No manual SQL import is needed to get started.

   > Optionally, import `construction_inventory.sql` for sample/demo data, or `cims_indexes.sql` to apply performance indexes.

4. **Default admin credentials (created on first run if no users exist):**
   - **Username:** `admin`
   - **Password:** `password123`
   - ⚠️ Change this immediately after first login.

### 3. Firebase Push Notifications Configuration

1. Create a project in the [Firebase Console](https://console.firebase.google.com/).
2. Add a **Web App** to the project. Copy the `firebaseConfig` and update `assets/js/fcm.js` and `firebase-messaging-sw.js`.
3. Go to **Project Settings › Cloud Messaging** and generate a **VAPID Key**. Add it to `assets/js/fcm.js`.
4. Go to **Project Settings › Service Accounts** and click **Generate new private key**.
5. Rename the downloaded JSON file to `firebase-service-account.json` and place it inside the `Connection/` directory.

### 4. Testing Web Push on Mobile (Localhost Bypass)

Browsers require HTTPS for Service Workers and Push Notifications. To test on a mobile device locally:

- **Option A:** Use [Ngrok](https://ngrok.com/) to tunnel your local port 80 to a secure HTTPS URL.
- **Option B (Android only):** Open `chrome://flags`, search for **"Insecure origins treated as secure"**, and add your local IPv4 address (e.g., `http://192.168.x.x`).

---

## 🛡️ Security Implementations

- **Password Hashing & Robust Complexity:** PHP's native `password_hash()` (Bcrypt algorithm with auto-generated salts). Password policies enforce at least 8 characters, uppercase (`A-Z`), lowercase (`a-z`), and numeric (`0-9`) criteria paired with real-time UI strength meters.
- **SQL Injection Prevention:** 100% PDO prepared statements — no raw string interpolation in queries.
- **Directory Protection:** `.htaccess` rules prevent direct folder browsing and mask `.php` extensions from public URLs. The `Connection/` directory has its own `.htaccess` blocking direct HTTP access.
- **Role-Based Route Guards:** Every module checks `$_SESSION['user_role']` server-side before rendering or processing any action.
- **Offline DB Handling:** PDO connection failures are caught gracefully — no raw PHP errors are exposed to the browser.

---

## 👨‍💻 Development Team: The MedYas

| Role | Name |
|---|---|
| Project Manager | Jahzeel James Jakosalem |
| Developer / Programmer | Angelo Carlo Pedrosa |
| System Quality Assurance | LJ Caballero |
