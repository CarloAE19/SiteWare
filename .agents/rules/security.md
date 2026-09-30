---
trigger: always_on
---

# CIMS Enterprise Security Standards & Enforcement

Follow these mandatory security rules across all backend scripts, API endpoints, database operations, and frontend interactions in the CIMS codebase:

---

## 1. 100% Prepared Statements (Zero SQL Injection)
- **Mandatory Parameterization:** All SQL queries containing user input, session values, or external variables MUST use PDO prepared statements with parameterized placeholders (`?` or `:name`).
- **Strict Prohibition:** Direct string concatenation, interpolation (`"SELECT ... WHERE id = $id"`), or inline variables inside SQL queries are strictly prohibited.
- **Dynamic Identifiers:** When dynamic table or column names are unavoidable (e.g. sorting), they must be strictly validated against a hardcoded whitelist before query execution.

## 2. Server-Side Authorization & Anti-IDOR Enforcement
- **Never Trust Client-Side:** Hiding UI elements or disabling buttons in JavaScript/HTML is purely cosmetic. Every backend endpoint (`process/*.php`, controllers, handlers) MUST independently verify:
  1. Active authentication: `isset($_SESSION['user_id'])`.
  2. Role authorization: Check `$_SESSION['user_role']` against an explicit whitelist allowed for that action.
- **Insecure Direct Object Reference (IDOR) Prevention:** Users may only view, edit, approve, or delete records they own or have explicit administrative/approver clearance for.
  - *Example:* Requestors can only query or cancel requisitions matching `requestor_id = $_SESSION['user_id']`.

## 3. Cross-Site Request Forgery (CSRF) Protection
- All state-altering requests (`POST`, `PUT`, `DELETE`, modal form submissions, and AJAX mutation actions) must validate a secure anti-CSRF token (`$_SESSION['csrf_token']` or `X-CSRF-Token` header).
- Reject any request lacking a valid token with HTTP 403 Forbidden.

## 4. Cross-Site Scripting (XSS) Sanitization
- All dynamic data rendered into HTML, table cells, modal bodies, or value attributes must be sanitized with:
  `htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8')`.
- Ensure JSON responses encode user data securely using `json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)`.

## 5. Session Hardening & Credential Protection
- **Session Regeneration:** Always call `session_regenerate_id(true)` immediately upon successful user authentication to eliminate session fixation vulnerabilities.
- **Cookie Security:** Ensure session cookies are set with `HttpOnly`, `SameSite=Lax` (or `Strict`), and `Secure` (when HTTPS is enabled).
- **Password Storage:** All passwords must be hashed using PHP's native `password_hash($password, PASSWORD_DEFAULT)` and verified using `password_verify($password, $hash)`. Plaintext or MD5/SHA1 hashing is strictly prohibited.

## 6. Secure File Upload Handling
- **MIME Type Validation:** Verify uploaded files (signatures, receipts, attachments) using server-side inspection (`finfo_file` / `mime_content_type`), never trusting the client-supplied `$_FILES['file']['type']` or extension.
- **Whitelist Allowed Extensions:** Allow only explicitly whitelisted formats (e.g., `['jpg', 'jpeg', 'png', 'pdf']`).
- **Filename Randomization:** Store uploaded files using randomized, non-guessable identifiers (e.g., `bin2hex(random_bytes(16)) . '.' . $extension`) in protected upload directories. Prevent direct PHP script execution in upload folders.
- **Size Limits:** Enforce strict file size limits (e.g., maximum 5MB) on both client and server before processing.

## 7. Atomic Database Transactions & Concurrency Safety
- Every multi-step database mutation (e.g., approving requisitions, deducting inventory quantities, inserting transaction logs, and creating audit entries) MUST be wrapped in a PDO transaction:
  ```php
  $pdo->beginTransaction();
  try {
      // Step 1: Check stock balance with row locking if necessary
      // Step 2: Deduct inventory & update requisition status
      // Step 3: Write audit trail entry
      $pdo->commit();
  } catch (Exception $e) {
      $pdo->rollBack();
      throw $e;
  }
  ```
- Prevent race conditions and negative inventory balances by enforcing `quantity >= requested_qty` checks at the database query level.

## 8. Safe Error Handling & Information Disclosure Prevention
- Never expose raw database exceptions (`$e->getMessage()` containing SQL syntax or table schemas) or PHP stack traces to client browsers or API responses.
- Log error details securely on the server via `error_log()`.
- Return clean, human-readable error messages in standardized JSON responses:
  `{"success": false, "status": "error", "message": "Unable to complete request. Please try again or contact support."}`.
