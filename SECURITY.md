# CIMS Security Policy & System Hardening Guidelines

**Project:** Construction / Corporate Inventory Management System (CIMS)  
**Classification:** Enterprise Web Application / Capstone Deployment  
**Standard Alignment:** OWASP Top 10, ISO 9001:2015 (Process Traceability), ISO/IEC 25010 (Software Product Quality - Security Characteristic)

---

## 1. Overview & Security Objectives
The Construction Inventory Management System (CIMS) manages mission-critical physical inventory, stock movements, purchase restocks, and multi-tier requisition approvals. The objective of this security policy is to safeguard the confidentiality, integrity, and availability (CIA Triad) of system data, eliminate single points of compromise, and provide verifiable auditability for both client stakeholders and academic review panels.

---

## 2. Role-Based Access Control (RBAC) Matrix

CIMS enforces strict server-side authorization. Client-side interface adjustments (such as hidden buttons or disabled links) are strictly non-authoritative.

| Role | Permitted Actions | Restrictions |
| :--- | :--- | :--- |
| **Admin** | Full system administration, user management, audit trail inspection, system configuration, master inventory overrides. | Subject to immutable audit logging for all actions. |
| **Approver** | Review project requisitions, approve/reject material requests with mandatory remarks, monitor stock availability. | Cannot alter user credentials or bypass audit logs. |
| **Purchasing** | Create and manage warehouse restock requisitions, monitor supplier orders, receive delivered shipments. | Restricted from approving project-level requisitions. |
| **Requestor** | Submit project material requisitions, track personal request status, cancel pending own requests. | **Strictly restricted to own requisitions** (`requestor_id` match); cannot view or alter other users' requests. |
| **Warehouse / Inventory Custodian** | Log physical counts, reconcile discrepancies, issue approved items, perform barcode/QR check-ins. | Cannot approve requisitions or edit administrative role assignments. |

---

## 3. Threat Mitigation & Defensive Architecture

CIMS incorporates defense-in-depth mitigations aligned with the OWASP Top 10 security standards:

### 3.1. SQL Injection (SQLi) Defense
- **100% Parameterized Queries:** Every database interaction is executed via PHP Data Objects (PDO) utilizing prepared statements with parameterized placeholders (`?` or `:name`).
- Direct concatenation of variables into SQL queries is prohibited.
- Table and column identifiers used in dynamic sorting or filtering are validated against strict internal whitelists.

### 3.2. Cross-Site Scripting (XSS) Prevention
- All user-supplied data rendered in server-generated HTML templates is escaped using:
  ```php
  htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
  ```
- AJAX JSON payloads are encoded with safety flags (`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`) to prevent script injection in DOM-inserted elements.

### 3.3. Cross-Site Request Forgery (CSRF) Prevention
- Anti-CSRF tokens generated per session are embedded into all forms and validated on state-changing endpoints (`POST`, `PUT`, `DELETE`).
- AJAX requests transmit the token via headers (`X-CSRF-Token`) or multipart payloads, rejecting mismatches with HTTP 403 Forbidden.

### 3.4. Insecure Direct Object References (IDOR)
- Backend endpoints enforce row-level ownership validation before reading or altering data.
- Example: When a requestor cancels a requisition, the backend enforces:
  ```sql
  UPDATE requisitions SET status = 'cancelled' WHERE id = ? AND requestor_id = ?
  ```

### 3.5. Authentication & Session Hardening
- **Password Security:** Passwords are hashed using `password_hash($password, PASSWORD_DEFAULT)` using industry-standard modern cryptographic algorithms (BCrypt / Argon2).
- **Session Regeneration:** Sessions are regenerated (`session_regenerate_id(true)`) upon successful authentication to defend against session fixation attacks.
- **Cookie Flags:** Session cookies are configured with `HttpOnly`, `SameSite=Lax`, and `Secure` (for HTTPS deployments) to mitigate session hijacking via client-side scripts.

### 3.6. Secure File Uploads
- Uploaded receipts, signatures, and documentation are verified via server-side MIME type inspection (`finfo_file`).
- Files are saved with randomized cryptographic names (`bin2hex(random_bytes(16)) . '.' . $extension`) outside direct script execution paths.
- Execution permissions for `.php`, `.phtml`, `.exe`, and `.sh` files inside upload folders are disabled.

### 3.7. Concurrency & Stock Integrity (Atomic Transactions)
- Multi-step inventory operations (stock deduction upon issuance, requisition state transitions, and audit trail insertions) are wrapped inside atomic PDO transactions (`beginTransaction()`, `commit()`, `rollBack()`).
- Database constraints ensure inventory levels cannot drop below zero (`quantity >= 0`).

---

## 4. Audit Logging & Nonconformity Traceability (ISO 9001 Alignment)

To comply with **ISO 9001:2015 Clause 7.5 (Documented Information)** and **Clause 8.5.2 (Identification and Traceability)**:
1. **Permanent Audit Trail:** Every material issuance, restock, approval, rejection, and recount writes a permanent record into the system audit log containing:
   - `user_id` (Operator)
   - `action_type` (e.g., `APPROVE_REQUISITION`, `DEDUCT_STOCK`, `AUDIT_VARIANCE`)
   - `entity_type` & `entity_id`
   - `previous_value` & `new_value`
   - `ip_address` & `timestamp`
2. **Discrepancy Documentation:** Physical count variances (surplus/missing stock) and rejection reasons cannot be erased and require explanatory remarks for accountability.

---

## 5. Client Deployment Security Checklist

When deploying CIMS to the client's production environment, the following hardening steps must be executed:

- [ ] **HTTPS Enforcement:** Configure Apache/Nginx with a valid TLS/SSL certificate; redirect all HTTP traffic to HTTPS.
- [ ] **Disable Display Errors:** Set `display_errors = Off` and `log_errors = On` in `php.ini` to prevent stack trace disclosures to end users.
- [ ] **Database Privilege Minimization:** The application database user should only have `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges. Restrict `DROP`, `ALTER`, and `GRANT` permissions in production.
- [ ] **Environment Separation:** Store database credentials in a protected configuration file located outside the web root or in server environment variables.
- [ ] **Scheduled Backups:** Configure daily automated backups of the MySQL database and uploaded media directory.

---

## 6. Vulnerability Disclosure & Support

If any security vulnerability or logic flaw is identified during capstone evaluation or client operation, please report it to the development team:
- **Project Lead / Developer Contact:** CIMS Development Team
- **Response SLA:** Critical vulnerabilities will be acknowledged within 24 hours with an expedited patch deployment.
