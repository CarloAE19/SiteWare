# 🏗️ GB Construction & Enterprise — Smart Inventory & Logistics System (SiteWare / CIMS)

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://php.net)
[![Database](https://img.shields.io/badge/Database-MySQL%20%7C%20MariaDB-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/UI-Bootstrap%205.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Security](https://img.shields.io/badge/Security-RSA--2048%20%7C%20SHA--256-green)](#-cryptographic-document-security--pki)
[![PWA](https://img.shields.io/badge/PWA-Installable%20%26%20Offline--Resilient-orange?logo=pwa&logoColor=white)](#-progressive-web-app-pwa--offline-resilience)

An enterprise-grade, cloud-ready **Construction Inventory Management System (CIMS)** engineered specifically for **GB Construction & Enterprise Inc.** Designed for mission-critical reliability across job sites, main warehousing, and executive management, the platform unifies real-time inventory tracking, AI-powered predictive replenishment, asymmetric cryptographic document sealing, Viber logistics messaging, cross-platform push alerts, and an offline-resilient Progressive Web App (PWA) architecture.

---

## 📑 Table of Contents

- [✨ Key Features & Innovations](#-key-features--innovations)
- [👥 Role-Based Access Control (RBAC)](#-role-based-access-control-rbac)
- [🔐 Cryptographic Document Security & PKI](#-cryptographic-document-security--pki)
- [🛡️ 5-Layer Defense-in-Depth Upload Security](#-5-layer-defense-in-depth-upload-security)
- [🤖 Role-Aware AI Assistant & Unit Advisor](#-role-aware-ai-assistant--unit-advisor)
- [🗂️ Project Structure](#️-project-structure)
- [🗃️ Database Schema & Auto-Migration Engine](#️-database-schema--auto-migration-engine)
- [🛠️ Technical Stack](#️-technical-stack)
- [🚀 Installation & Setup Guide](#-installation--setup-guide)
- [🔒 Security & Compliance Standards (ISO 9001 / ISO 25010)](#-security--compliance-standards)
- [👨‍💻 Development Team](#-development-team-the-medyas)

---

## ✨ Key Features & Innovations

### 1. 🔐 Asymmetric Cryptographic Document Sealing (RSA-2048 + SHA-256)
- **Mathematical Integrity Verification:** Every Purchase Order (PO) and Material Withdrawal (WD) generates a canonical deterministic JSON digest signed via 2048-bit RSA private keys.
- **Public Verification Portal (`verify.php`):** External auditors, suppliers, or site engineers can scan QR codes printed on physical or virtual documents to independently verify validity and detect tampering down to the single item or quantity.
- **Self-Healing Key Generation:** Automatically provisions and stores RSA key pairs (`public_key`, `private_key`) for authorized signers in the database without administrative friction.

### 2. 🤖 Role-Aware AI Analytics & Smart Unit Advisor (NVIDIA NIM API)
- **Context-Injected Role Chatbot:** Floating interactive AI widget in the system footer (`process/chatbot_chat.php`) powered by NVIDIA NIM LLaMA 3.1 (`meta/llama-3.1-8b-instruct`). Tailors responses based on active user roles (e.g., pending approvals for Management, supplier turnaround for Purchasing, discrepancy alerts for Warehouse).
- **Intelligent Unit of Measurement Advisor (`process/ai_unit_advisor.php`):** Suggests appropriate measurement units, standard abbreviations, reorder thresholds, and rationales for materials, backed by heuristic fallbacks when offline.
- **Predictive Replenishment Forecasting:** Analyzes 30-day stock velocity against supplier lead times to forecast run-out dates and suggest optimal reorder points (`analytics.php`).

### 3. 📦 Multi-Stage Purchase Order Fulfillment & Partial Deliveries
- **Incremental Receiving:** Handles multi-batch supplier deliveries with live item-level tracking (`quantity`, `received_quantity`, and status: `Pending`, `Partial`, `Complete`).
- **Responsive Virtual PO Document:** Screen-optimized and print-ready Half-A4 virtual document preview with official digital signatures, company seals, and live calculation.
- **Weather & Logistics Delay Tracking:** Structured logging for delivery delays, discrepancy memos, and supplier turnaround performance ratings.

### 4. 📲 Viber & SMS Logistics Integration
- **Supplier Viber Order Logs (`supplier_viber_logs` / `viber_actions.php`):** Direct dispatch and tracking of official purchase orders with line-item breakdowns and supplier confirmation histories.
- **SMS Gateway via httpSMS:** Fallback SMS transmission and inbound webhook reply processing (`httpsms_webhook.php`).

### 5. 📱 Progressive Web App (PWA) & Offline Resilience
- **Multi-Platform Installable:** Installable on Windows, macOS, Android, and iOS with dedicated splash screens (`components/splash_screen.php`) and service worker offline caching (`firebase-messaging-sw.js`, `offline.html`).
- **Database Disconnection Graceful Recovery:** When the MySQL server is unreachable, CIMS renders a non-fatal recovery view with active retry controls while AJAX endpoints return structured JSON status codes instead of leaking stack traces.
- **Early Theme Initializer:** Instant Dark / Light / System theme switching stored in `localStorage` executing before DOM paint to completely prevent Flash of Unstyled Content (FOUC).

### 6. 📊 Physical Count Workstation & ISO Audit Trail
- **Weekly Physical Count Station (`physical_count.php`):** Fast inventory audit workstation featuring instant discrepancy calculation, ±1 stepper controls, and discrepancy breakdown counters.
- **Permanent Audit History (`audit.php`):** Verifiable records of historical audits, conducting officers, variance breakdowns, and item-level drill-down modals.

### 7. ✍️ Digital E-Signatures & Verified Proof Capture
- **User Profile E-Signature Suite (`profile.php` / `process/update_signature.php`):** Officers draw or upload high-resolution digital signatures that automatically sync to prepared and approved documents.
- **Proof of Receipt & Photo Verification:** Capture physical delivery receipts and recipient signatures directly into secure storage.

### 8. 🛡️ Server Security Firewall & Anti-Brute Force Protection
- **Sliding-Window Rate Limiting Engine (`Connection/rate_limiter.php`):** Centralized IP-based and session-based request throttler protecting sensitive endpoints (e.g. login brute-force defense locked at 5 failed attempts per 15 minutes with dynamic countdown timers, AI chat rate guards).
- **Timing-Attack & Username Enumeration Mitigation:** Constant-time verification against realistic dummy Bcrypt hashes prevents attackers from discovering registered accounts via timing side-channel analysis.
- **Immediate Administrative Session Revocation:** Live per-request authorization checks (`layout/header.php`) that instantly terminate sessions, clear auth cookies, and redirect to `login?deactivated=1` if an administrator deactivates a user.
- **Hardened HTTP Headers & Web Firewall (`.htaccess`):** Strict directives enforcing `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`, HSTS (`Strict-Transport-Security`), and directory index suppression (`Options -Indexes`).

### 9. ⏱️ Configurable Inactivity Lockout & Auto-Logout Policy
- **System Settings Inactivity Controls (`settings.php`):** Administrators can configure idle screen lock thresholds (default: 15 minutes) and hard idle logout limits (default: 30 minutes).
- **Server-Side Activity Tracker:** Tracks user activity timestamps server-side on every request, securely terminating abandoned sessions (`login?timeout=1`) to safeguard company data on shared site terminals.

### 10. 🔔 Dynamic Notification Center & Live Supply Alerts
- **Real-Time Header Dropdown (`layout/header.php` / `assets/js/notifications.js`):** Interactive notification bell with unread badge counter, AJAX mark-as-read, clear-all, and background polling.
- **Combined Supply ETA & Alert Processor (`process/transactions/alert_actions.php`):** Aggregates imminent delivery ETAs, overdue supplier shipments, and low-inventory warnings in real-time with customizable audio alerts.

### 11. 📑 New-Item Requisition Restocking Flow
- **Uncataloged Restock Handling (`requisitions.php` / `process/transactions/rs_actions.php`):** Enables Project Engineers to request items not currently present in the master inventory database (`is_new_item`, `new_item_name`, `new_category`, `new_unit`).
- **Seamless Catalog Integration:** Purchasing and Warehouse teams can approve, purchase, and automatically onboard new items into the permanent inventory catalog during PO creation and delivery receiving.

### 12. 🏗️ Construction Project & Job-Site Registry
- **Comprehensive Project Directory (`projects.php` / `settings.php`):** Central registry tracking active and completed project sites with unique project codes.
- **Live Requisition & Withdrawal Counters:** Displays real-time counts of associated Material Requisitions (RS) and Material Withdrawal Slips (WS) bound to each construction project.

---

## 👥 Role-Based Access Control (RBAC)

CIMS enforces strict server-side role validation across all views and API controllers:

| Role | Access Scope & Key Responsibilities |
|---|---|
| **System Admin** | Full administrative rights, user provisioning, global system settings, theme branding, backup oversight, and unrestricted access to all modules. |
| **Management / Approver** | Executive dashboard, AI restocking analytics, Requisition Slip (RS) approvals/rejections, and high-level expenditure auditing. |
| **Purchasing Officer** | Supplier catalog, Purchase Order (PO) creation, supplier performance scoring, payment terms management, and Viber dispatch. |
| **Warehouse In-Charge** | Stock-in/stock-out, physical inventory audits, barcode/QR scanning, partial delivery receiving, and material withdrawal processing with signature capture. |
| **Requestor (Project Engineer)** | Material Requisition Slips (RS) creation for job sites or general restocking, live status tracking, and new-item restock requests. |

---

## 🔐 Cryptographic Document Security & PKI

```
                     ┌─────────────────────────────────────────┐
                     │ Purchase Order / Material Withdrawal    │
                     │ Form Data & Line Items                  │
                     └────────────────────┬────────────────────┘
                                          │
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ Deterministic Canonical JSON Normalizer │
                     │ (Sorted Keys, Floats, Trimmed Strings)  │
                     └────────────────────┬────────────────────┘
                                          │
                        ┌─────────────────┴─────────────────┐
                        ▼                                   ▼
             ┌─────────────────────┐             ┌─────────────────────┐
             │ SHA-256 Digest Hash │             │ RSA-2048 Signer Key │
             └──────────┬──────────┘             └──────────┬──────────┘
                        │                                   │
                        └─────────────────┬─────────────────┘
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ Cryptographic Digital Signature Sealed  │
                     │ (Base64 PKCS#1 v1.5 Encoded)            │
                     └────────────────────┬────────────────────┘
                                          │
                                          ▼
                     ┌─────────────────────────────────────────┐
                     │ Public Verification Portal (verify.php) │
                     │ Scannable QR Badge & Proof of Integrity │
                     └─────────────────────────────────────────┘
```

1. **Deterministic Canonical JSON:** Documents are normalized into sorted, consistent representations (`buildCanonicalPoPayload()`, `buildCanonicalWdPayload()`).
2. **Asymmetric Signing:** The payload is hashed with SHA-256 and signed with the officer's RSA private key (`helpers/crypto_helper.php`).
3. **Independent Verification:** The portal `verify.php?ref=PO-XXXX` extracts the signer's public key from the database and verifies the document against `openssl_verify()`. If even one character or quantity was altered, verification immediately flags **`TAMPERED / INVALID`**.

---

## 🛡️ 5-Layer Defense-in-Depth Upload Security

All document receipts, proof photos, and user signatures pass through `classes/SecureUploadHandler.php` and `secure_image.php`:

```
 [Client Upload]
       │
       ▼
 [Layer 1: Binary MIME & Structure Validation] ──► Finfo true MIME inspection & getimagesize()
       │
       ▼
 [Layer 2: Malicious Signature Scanning]       ──► Pattern scan for <?php, eval(), script tags
       │
       ▼
 [Layer 3: Cryptographic Filename Isolation]   ──► Random hex hash prefix + strictly mapped ext
       │
       ▼
 [Layer 4: Image Re-Encoding & EXIF Stripping] ──► GD library re-renders clean image buffer
       │
       ▼
 [Layer 5: Authenticated Proxy Access]         ──► secure_image.php checks session & role RBAC
```

1. **Binary MIME Verification:** Never relies on client-provided extensions; inspects binary magic bytes using PHP `finfo`.
2. **Malicious Content Heuristics:** Scans image byte streams for embedded PHP tags, hex-encoded payloads, or polyglot scripts.
3. **Cryptographic Naming & Isolation:** Replaces original file names with cryptographically secure hashes stored in private directories with `.htaccess` execution restrictions (`Deny from all`).
4. **Binary Re-Encoding:** Decodes and re-encodes images through the GD graphics library, permanently stripping EXIF metadata, GPS coordinates, and steganographic payloads.
5. **Authenticated Secure Proxy (`secure_image.php`):** Prevents direct HTTP file browsing. Requires active login sessions and verifies role clearance (e.g. receipts only accessible to authorized roles). Clean REST URLs mapped via `/media/{type}/{file}` and `/secure-image`.

---

## 🤖 Role-Aware AI Assistant & Unit Advisor

- **Powered by NVIDIA NIM API (`meta/llama-3.1-8b-instruct`):**
  - **Dynamic System Context Injection:** When a user opens the assistant, the backend queries real-time database metrics specifically scoped to their permission tier and provides contextual recommendations.
  - **Rate Limiting Guard:** Integrated with `Connection/rate_limiter.php` to prevent API exhaustion with a sliding-window algorithm (max 10 requests per 60 seconds per user).
- **AI Unit Advisor (`process/ai_unit_advisor.php`):**
  - Intelligently detects unit semantics for materials (e.g., cubic meters for gravel, bags for cement, linear meters for rebars).
  - Supplies recommended reorder levels and explanation rationales.
  - Features built-in heuristic rules providing instant, zero-latency recommendations even when offline or unconfigured.

---

## 🗂️ Project Structure

```
CIMS/
├── Connection/
│   ├── .htaccess                   # Blocks direct HTTP access to connection assets
│   ├── db.php                      # DB connection, auto-migration, auto-patching, offline handler
│   ├── fcm_helper.php              # Pure-PHP OAuth2 JWT Firebase Cloud Messaging engine
│   ├── firebase-service-account.json # Google Service Account credentials (private)
│   └── rate_limiter.php            # Centralized IP & session-based anti-abuse rate limiter
├── assets/
│   ├── css/                        # Custom modular styling (style.css, custom.css, po.css)
│   ├── js/                         # Frontend logic (notifications.js, requisitions.js, inventory.js, fcm.js)
│   ├── img/                        # Branded icons, system logo, and login backgrounds
│   └── sounds/                     # Notification audio alerts
├── classes/
│   └── SecureUploadHandler.php     # 5-Layer Defense-in-Depth file upload & image re-encoder
├── components/
│   ├── audit_modal.php             # Physical count drill-down modal
│   ├── entity_modals.php           # Centralized modal suite (PO viewer, RS details, WD viewer)
│   ├── inventory_modals.php        # Stock-in and item creation modals
│   ├── po_modal.php                # Comprehensive PO creation, status tracking, and delivery modals
│   ├── requisition_modals.php      # RS creation, item rows, and approval modals
│   ├── splash_screen.php           # PWA branded launch splash screen
│   ├── supplier_modal.php          # Supplier entry and edit modal
│   ├── unit_modal.php              # Unit management and AI advisor interface
│   ├── user_modal.php              # User management and role provisioning modal
│   └── withdrawal_modal.php        # Material withdrawal and signature capture modal
├── helpers/
│   └── crypto_helper.php           # RSA-2048 & SHA-256 PKI cryptographic sealing & verification
├── layout/
│   ├── header.php                  # Navigation, dynamic notifications dropdown, early theme init
│   └── footer.php                  # System footer, PWA router, role-based AI chatbot widget
├── process/
│   ├── ai_unit_advisor.php         # AI unit recommendation & heuristic analyzer endpoint
│   ├── chatbot_chat.php            # Role-aware AI assistant endpoint (NVIDIA NIM LLaMA 3.1)
│   ├── get_item_details.php        # AJAX inventory item reader
│   ├── get_po_details.php          # AJAX purchase order line-items and metadata reader
│   ├── get_rs_details.php          # AJAX requisition line-items and metadata reader
│   ├── get_withdrawal_details.php  # AJAX material withdrawal details reader
│   ├── httpsms_webhook.php         # Inbound SMS webhook receiver
│   ├── module_audit.php            # Audit submission and discrepancy recording
│   ├── module_inventory.php        # Stock management and CRUD controllers
│   ├── module_settings.php         # System branding and visual customization
│   ├── module_suppliers.php        # Supplier directory controller
│   ├── module_transactions.php     # High-level transaction request dispatcher
│   ├── module_users.php            # Account creation and role modifier
│   ├── process.php                 # Core centralized form action router
│   ├── process_notif.php           # AJAX notification mark-as-read and clear handlers
│   ├── update_signature.php        # Profile E-signature drawing and upload processor
│   └── transactions/
│       ├── alert_actions.php       # Low-stock alerts and ETA notification background collector
│       ├── po_actions.php          # PO creation, partial delivery, weather delay, discrepancy handlers
│       ├── rs_actions.php          # Requisition workflow, QR code generator, and approval logic
│       ├── viber_actions.php       # Viber supplier dispatch and message logging
│       └── withdrawal_actions.php  # Material withdrawal, inventory deduction, and signature recorder
├── uploads/                        # Protected storage for receipts, signatures, and proof photos
├── .env                            # Environment credentials (DB, AI Key, SMS - not committed)
├── .htaccess                       # Server security rules, rewrite engine, header protections
├── about.php                       # System & team credits page
├── analytics.php                   # AI restocking forecasting and consumption velocity interface
├── audit.php                       # Historical audit log and discrepancy ledger
├── categories.php                  # Inventory category management
├── cims_indexes.sql                # High-performance MySQL index definitions
├── construction_inventory.sql      # Reference SQL schema dump with initial seed data
├── dashboard.php                   # Role-personalized dashboard with live KPIs and activity feed
├── firebase-messaging-sw.js        # Background Web Push service worker
├── index.php                       # Primary inventory master table and stock-in interface
├── login.php                       # Authentication portal with brute-force rate limiting
├── logout.php                      # Session destroyer and cleanup
├── manifest.json                   # Web App Manifest for PWA installation
├── offline.html                    # Network-down PWA fallback screen
├── physical_count.php              # Warehouse physical count workstation with discrepancy calculator
├── po.php                          # Purchase Order management, Virtual PO preview, delivery tracking
├── profile.php                     # User profile, password management, and digital e-signature studio
├── projects.php                    # Construction project and job-site registry
├── requisitions.php                # Requisition Slips, approval workflow, and new item restock requests
├── secure_image.php                # Authenticated session and role-validated image proxy
├── settings.php                    # System customization, login styling, and visual controls
├── suppliers.php                   # Supplier directory and performance tracking
├── units.php                       # Unit measurement definitions with reorder levels
├── users.php                       # User management workstation (Admin only)
├── verify.php                      # Public Cryptographic Verification Portal for signed documents
└── withdrawals.php                 # Material Withdrawal management and signature verification
```

---

## 🗃️ Database Schema & Auto-Migration Engine

CIMS features **zero-touch database provisioning**. On initial launch, `Connection/db.php` automatically creates the database and provisions all tables, foreign keys, auto-patches, and performance indexes:

| # | Table Name | Purpose & Structure |
|---|---|---|
| 1 | `users` | User credentials, roles, status, FCM push tokens, signature paths, RSA-2048 public & private keys. |
| 2 | `inventory` | Master inventory catalog, item codes, quantity, unit, unit price, status, timestamps. |
| 3 | `categories` | Material categories (Materials, Tools, Safety Equipment, Heavy Machinery). |
| 4 | `units` | Measurement units (pcs, bags, kg, m, L) with unit-specific reorder thresholds. |
| 5 | `suppliers` | Supplier master list, contact persons, contact numbers, email, physical addresses. |
| 6 | `requisitions` | Material Requisition Slips (RS), urgency, project binding, approval state, and request type. |
| 7 | `requisition_items` | RS line items with support for catalog items and new unregistered restock requests. |
| 8 | `purchase_orders` | PO master records, payment terms, delivery ETAs, weather delays, RSA crypto signatures, proof paths. |
| 9 | `po_items` | PO line items supporting partial delivery tracking (`received_quantity`, `item_status`). |
| 10 | `withdrawals` | Material withdrawal slips with recipient name, crypto signatures, and proof photos. |
| 11 | `withdrawal_items` | Line items deducted during material releases. |
| 12 | `inventory_audits` | Weekly/monthly physical count records with auditor assignments and discrepancy totals. |
| 13 | `audit_items` | Item-by-item physical count variances (system qty vs. physical qty vs. discrepancy). |
| 14 | `notifications` | Role-based and user-targeted notifications with read receipts. |
| 15 | `supplier_viber_logs` | Communication history for Viber and SMS orders dispatched to suppliers. |
| 16 | `projects` | Active and completed construction project registry. |
| 17 | `system_settings` | System-wide configurable options (login background, blur intensity, system branding). |

### Automatic Optimization & Hostinger High-Performance Indexing
The system auto-migrates indexes from `cims_indexes.sql` into live databases, optimizing heavy queries across `inventory`, `requisitions`, `purchase_orders`, `withdrawals`, `notifications`, and audit trails for rapid response times.

---

## 🛠️ Technical Stack

| Layer | Component | Technical Detail |
|---|---|---|
| **Frontend** | Framework & Styling | HTML5, CSS3, Vanilla JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons, Font Awesome 6 |
| **Theme Engine** | FOUC-Free Theme Switcher | Early execution script binding `data-bs-theme` via `localStorage` with system preference detection |
| **Backend** | Server Engine | PHP 8.0+ using Object-Oriented Architecture and defensive PDO prepared statements |
| **Database** | RDBMS | MySQL 5.7+ / 8.0+ or MariaDB 10.4+ (InnoDB, `utf8mb4_unicode_ci`) |
| **AI Analytics** | NVIDIA NIM API | LLaMA 3.1 8B Instruct (`meta/llama-3.1-8b-instruct`) with streaming context injection |
| **Cryptography** | Digital Signatures & PKI | OpenSSL RSA-2048 key pairs with SHA-256 canonical hashing & verification |
| **Mobile & PWA** | Progressive Web App | Web App Manifest, Service Worker caching, and offline fallback |
| **Push Notifications** | Google FCM v1 | Pure-PHP JWT authentication engine (no Composer dependency required) |
| **Messaging** | Viber & SMS Gateway | Viber logistics integration and httpSMS gateway with inbound webhooks |
| **QR System** | Scan & Generate | HTML5-QRCode scanner and high-resolution dynamic QR code generation |

---

## 🚀 Installation & Setup Guide

### 1. Prerequisites
- Local Web Server: **XAMPP**, **WAMP**, **LAMP**, or **Docker** running **PHP 8.0 or higher**.
- MySQL or MariaDB database service running on default port `3306`.
- OpenSSL PHP Extension enabled (`extension=openssl` in `php.ini` - enabled by default).
- GD PHP Extension enabled (`extension=gd` in `php.ini` - enabled by default).

### 2. Clone & Configure
1. Clone the repository into your web server document root (e.g. `c:/xampp/htdocs/CIMS`):
   ```bash
   git clone https://github.com/carloae19/cims.git
   ```

2. Create a `.env` file in the root directory:
   ```env
   # Database Credentials
   DB_HOST=localhost
   DB_NAME=construction_inventory
   DB_USER=root
   DB_PASS=

   # NVIDIA NIM API (for AI Analytics & Role-Based Chatbot)
   AI_API_KEY=your_nvidia_nim_api_key_here
   AI_MODEL=meta/llama-3.1-8b-instruct
   AI_SYSTEM_PROMPT="You are SiteWare AI, the intelligent construction inventory assistant for GB Construction & Enterprise Inc."

   # SMS Gateway (httpSMS)
   SMS_API_KEY=your_httpsms_api_key
   SMS_FROM_NUMBER=+639XXXXXXXXX
   SMS_GATEWAY_URL=https://api.httpsms.com/v1/messages/send
   ```

3. **Open the application in your browser:**
   ```
   http://localhost/CIMS/
   ```
   The built-in migration engine in `Connection/db.php` will automatically create the database, all 17 tables, default categories, default units, and performance indexes.

4. **Default Admin Login (provisioned on first run):**
   - **Username:** `admin`
   - **Password:** `password123`
   - ⚠️ *Always change this password immediately in `profile.php` upon first login.*

---

## 🔒 Security & Compliance Standards

### Quality Standards & ISO 9001 / ISO/IEC 25010 Alignment
- **Traceability & Complete Audit Trails (ISO 9001 Clause 8.5.2 & 7.5):** Every material movement, requisition status change, issuance, restock, and physical audit logs permanent, immutable entries.
- **Control of Nonconformities (ISO 9001 Clause 8.7):** Dedicated workflows for delivery variance, physical count discrepancies, and mandatory rejection remarks on requisitions.
- **Defensive & Clean Architecture (ISO/IEC 25010):**
  - **100% Prepared Statements:** Eliminates SQL Injection (SQLi) vulnerabilities across all read and write queries.
  - **CSRF Token Protection with TTL:** Cryptographic 256-bit random tokens with 2-hour sliding expiry prevent cross-site request forgery, verified via `hash_equals()`.
  - **Secure Sessions & Anti-Fixation:** Mandatory `session_regenerate_id(true)` upon login, with cookie flags set to `HttpOnly`, `SameSite=Lax`, and dynamic `Secure` under HTTPS.
  - **Timing-Attack & Username Enumeration Mitigation:** Employs constant-time dummy Bcrypt verifications for non-existent users, neutralizing timing side-channel attacks during authentication.
  - **Immediate Administrative Session Revocation:** Continuous per-request validation automatically purges sessions and cookies if an administrator flags an account as inactive.
  - **Configurable Inactivity Lockout & Auto-Logout:** Automated inactivity guards tracking user idle times against enterprise policies, redirecting expired sessions to secure re-authentication.
  - **Centralized Anti-Brute Force Protection:** IP and username sliding-window rate limiters with progressive minute lockout countdowns guard login endpoints and API tools.
  - **Web Security Firewall (`.htaccess`):** Blocks access to sensitive files (`.env`, `.git`, `.ini`, `.log`), suppresses directory listings (`Options -Indexes`), and enforces strict HTTP security headers (`X-Frame-Options`, `X-XSS-Protection`, `X-Content-Type-Options`, `Referrer-Policy`, and HSTS).
  - **XSS Sanitization & Input Whitelisting:** All dynamic user inputs and outputs are strictly sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` and typecast validation.
  - **Custom Error Documents:** Dedicated error pages for HTTP 400, 403, 404, 500, and 503 suppress raw server errors and prevent information leakage.

---

## 👨‍💻 Development Team: The MedYas

Developed with pride for **GB Construction & Enterprise Inc.** by **The MedYas**:

| Member | Role & Contributions |
|---|---|
| **Jahzeel James Jakosalem** | **Project Manager** — Systems engineering, workflow coordination, quality planning, and operational alignment. |
| **Angelo Carlo Pedrosa** | **Developer / Programmer** — Architecture, full-stack development, cryptographic PKI, AI integration, and security engineering. |
| **LJ Caballero** | **System Quality Assurance** — Functional validation, security testing, mobile UX review, and compliance auditing. |

---

*SiteWare / CIMS &copy; 2026 Genetian Builders & Enterprises Inc. All rights reserved.*
