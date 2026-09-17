---
trigger: always_on
---

# Code Integrity & Professional Design Standards

Follow these strict standards across all tasks, edits, and recommendations for this codebase:

## 1. Code Integrity & Safety (Do Not Break Code)
- **Preserve Working Logic:** Never break, strip out, or alter existing working features, PHP session management, database queries, role permissions, or JavaScript handlers unless specifically instructed.
- **Context Awareness:** Always inspect surrounding code, connected API endpoints, modal scripts, and styles before modifying a file to ensure changes do not introduce regressions.
- **Defensive & Clean Code:** Ensure new or modified code handles edge cases gracefully, includes proper error checks, and adheres to clean, readable formatting.
- **Maintain Consistency:** Follow existing codebase conventions, naming patterns, and architectural structure.

## 2. Professional UI & Design Standards (No Sloppy Designs)
- **Enterprise-Grade Aesthetic:** User interfaces must look sleek, polished, modern, and professional—never basic, sloppy, or misaligned.
- **Visual Hierarchy & Spacing:** Use consistent spacing (padding, margins), structured grid/flex alignments, and clear typography sizing to create a balanced visual hierarchy.
- **Color & Contrast:** Employ curated, cohesive color palettes with appropriate contrast ratios for readability. Avoid harsh or generic browser-default colors.
- **Interactive State Polish:** Every interactive component (buttons, inputs, dropdowns, tables, modals) must have refined styling for default, `:hover`, `:focus`, `:active`, and `:disabled` states.

## 3. Mobile & Multi-Device Web App Optimization (All Phone Screen Sizes & Touch-Friendly)
- **Universal Mobile Phone Support:** The system is a fully responsive web application engineered to adapt flawlessly to all mobile phone sizes:
  - **Compact / Small Displays (320px – 360px, e.g. iPhone SE, compact Androids, folded foldables):** Layouts must never clip, overlap, or trigger horizontal scrollbars (`overflow-x: hidden`). Elements must stack vertically without squishing text or buttons.
  - **Standard Mobile Displays (375px – 430px, e.g. iPhone 13/14/15/16, Samsung Galaxy S series, Pixel):** Grid columns, cards, and modal widths must adapt dynamically with balanced padding and readable font hierarchies.
  - **Large Phones / Phablets & Foldables (430px – 600px+):** Utilize available screen real estate efficiently with adaptive multi-column form layouts and flexible table views.
- **Strict Viewport & Horizontal Overflow Prevention:** Prevent horizontal scrolling across all mobile screen widths down to 320px. Ensure tables, long strings (item codes, serial numbers, URLs), and button toolbars wrap cleanly using `text-break`, responsive wrappers, or flex-wrap.
- **Touch-Friendly Controls & Minimum Hit Targets:** Ensure all buttons, inputs, icons, dropdown triggers, and delete/action links meet a minimum touch target of 44x44px (or 48x48px where possible) with adequate spacing between adjacent buttons to eliminate misclicks on small touchscreens.
- **Mobile Form Usability & Virtual Keyboard Awareness:** 
  - Ensure form input font size is at least 16px on mobile to prevent iOS Safari auto-zooming on focus.
  - Form dialogs and inputs must remain visible and accessible when virtual keyboards are displayed.
- **Mobile Modals & Dialogs:** Use `modal-fullscreen-sm-down` or `modal-dialog-scrollable` so modal headers and sticky action footers remain easily reachable on both short and tall phone viewports.
- **Responsive Tables & Data Lists:** Data tables must be enclosed in `table-responsive` containers or dynamically transform into mobile-friendly stacked card views on narrow screens so users can read data without awkward horizontal panning.
- **Adaptive Navigation & Safe Areas:** Sidebars, navigation headers, and filter toolbars must collapse cleanly into mobile-friendly offcanvas drawers or sticky bottom/top navbars, respecting device safe areas (`env(safe-area-inset-bottom)`).

## 4. Human-Computer Interaction (HCI) & Usability Principles
- **Visibility of System Status:** Provide immediate, unambiguous feedback for user actions (loading spinners during AJAX, disabled buttons to prevent double-submits, clear toast/alert confirmations).
- **Error Prevention & Recovery:** Implement defensive input validation (e.g. quantity bounds, non-negative inputs), confirm destructive operations (deletions, cancellations), and provide helpful, human-readable error messages explaining how to fix issues.
- **Recognition Over Recall:** Minimize cognitive load by using descriptive field labels, sensible placeholder hints, contextual tooltips on icon-only buttons, and auto-suggest/autocomplete where appropriate.
- **Semantic & Visual Consistency:** Maintain strict, predictable color semantics across all screens (Green for Approved/Success, Yellow/Amber for Pending/Warning, Red for Rejected/Danger, Blue for Primary Actions).
- **User Control & Freedom:** Provide easy exits from dialogs (close button, backdrop tap, `Escape` key) and clear cancel/reset options without trapping the user in a broken state.
- **Accessibility & Inclusivity (a11y):** Ensure strong text-to-background contrast (WCAG standards), keyboard accessibility, and proper ARIA labels (`aria-label`, `aria-hidden`) on icon-only buttons.

## 5. Role-Based Access Control (RBAC) & Enterprise Security Standards
- **Strict Server-Side Authorization:** Never rely solely on client-side JS or hidden HTML elements to enforce permissions. Every backend controller, endpoint (`process/*.php`), and data query MUST verify active `$_SESSION['user_id']` and authorize `$_SESSION['user_role']` before executing actions.
- **CSRF Token Validation:** Verify anti-CSRF tokens (`$_SESSION['csrf_token']` or `X-CSRF-Token` headers) on all state-altering `POST`, `PUT`, or `DELETE` requests to prevent cross-site request forgery attacks.
- **Row-Level & Ownership Validation:** Ensure users can only view, edit, or manipulate records permitted by their role (e.g. `requestor` restricted to their own `requestor_id`, `purchasing` restricted to restock requests, and elevated roles like `admin`/`approver` verified before approvals or modifications).
- **100% Prepared Statements (SQLi Prevention):** All SQL queries with variables MUST use PDO prepared statements with parameterized placeholders (`?` or `:name`). Direct variable concatenation into SQL queries is strictly prohibited.
- **XSS Sanitization:** Escape all dynamic user output rendered in HTML using `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- **Input Validation & Whitelisting:** Strictly sanitize, type-cast (`(int)`, `(float)`), and validate incoming parameters. Whitelist allowed statuses, action types, and sorting columns.
- **Secure File Uploads:** Strictly validate uploaded files (proof of receipt, digital signatures, attachments) by checking real MIME types (`image/jpeg`, `image/png`, `application/pdf`), enforcing size limits, and storing files with randomized/hashed names in isolated directories to prevent path traversal and arbitrary script execution.
- **Atomic Transactions for Data Integrity:** Use database transactions (`beginTransaction()`, `commit()`, `rollBack()`) for multi-step mutations (e.g. stock level deductions, requisition status updates, and audit trail logging) to prevent orphaned or corrupt states.
- **Safe Error Handling:** Never output raw database errors or stack traces to end users. Log details securely on the server and return clean, friendly error messages.

## 6. Object-Oriented Programming (OOP) & Clean Code Architecture
- **Encapsulation & Modularity:** Structure backend logic, models, controllers, and services into well-defined classes with proper access modifiers (`private`, `protected`, `public`). Avoid scattered, monolithic procedural code.
- **Single Responsibility Principle (SRP):** Each class and method should have one clear responsibility (e.g. data access/models, business workflows/services, request routing/controllers).
- **Reusability & DRY (Don't Repeat Yourself):** Abstract shared logic (database access, permission checks, JSON response formatting, audit logging) into reusable classes, base classes, or traits rather than duplicating code across multiple scripts.
- **Clear Contracts & Type Hinting:** Use descriptive naming conventions (PascalCase for classes, camelCase for methods/variables), parameter type declarations (`int`, `string`, `array`, `?object`), and explicit return types where applicable.
- **Separation of Concerns:** Keep business logic and database queries decoupled from presentation/HTML views to ensure maintainability, testability, and clean code organization.

## 7. ISO 9001 (Quality Management) & ISO/IEC 25010 (Software Quality) Alignment
- **Traceability & Complete Audit Trails (ISO 9001 Clause 8.5.2 & 7.5):** Every material movement, requisition status change, issuance, restock, and inventory count must generate a permanent, verifiable audit record containing standardized columns (`user_id`, `action_type`, `entity_type`, `entity_id`, `previous_value`, `new_value`, `ip_address`, `timestamp`).
- **Control of Nonconformities (ISO 9001 Clause 8.7):** Explicitly support and document discrepancy workflows (physical audit variances like missing/surplus quantities, damaged goods logging, and mandatory rejection remarks on unapproved requisitions).
- **Process Control & Risk Mitigation (ISO 9001 Clause 6.1 & 8.5):** Implement strict backend validation guards to prevent accidental negative inventory balances, unauthorized state transitions, and duplicate submissions.
- **Software Product Quality Standards (ISO/IEC 25010):** Adhere to core software quality characteristics across all modules: Functional Suitability, Reliability (atomic transaction rollbacks on failure), Usability (HCI compliance), Security (RBAC and prepared statements), Maintainability (OOP structure), and Portability (multi-device web responsiveness).