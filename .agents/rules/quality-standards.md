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

## 3. Mobile & Multi-Device Web App Optimization (Responsive & Touch-Friendly)
- **Multi-Device Compatibility:** The system is a fully responsive web application. All layouts, dashboards, forms, modals, and data views must be thoroughly optimized for smartphones, tablets, laptops, and desktop screens.
- **Touch-Friendly Controls:** Ensure buttons, inputs, icons, and action links meet minimum touch-target guidelines (easy to tap on mobile without misclicks).
- **Responsive Tables & Lists:** Wrap data tables in responsive containers (`table-responsive`) or provide mobile-friendly card/stacked views to prevent horizontal overflow and broken layouts on small screens.
- **Mobile Modals & Forms:** Modal dialogs must fit mobile viewports cleanly (`modal-fullscreen-sm-down` or scrollable bodies), keeping action buttons accessible without getting cut off by virtual keyboards.
- **Adaptive Navigation:** Menus, sidebars, and filter toolbars must collapse cleanly into mobile-friendly toggles or offcanvas drawers on smaller screens.

## 4. Human-Computer Interaction (HCI) & Usability Principles
- **Visibility of System Status:** Provide immediate, unambiguous feedback for user actions (loading spinners during AJAX, disabled buttons to prevent double-submits, clear toast/alert confirmations).
- **Error Prevention & Recovery:** Implement defensive input validation (e.g. quantity bounds, non-negative inputs), confirm destructive operations (deletions, cancellations), and provide helpful, human-readable error messages explaining how to fix issues.
- **Recognition Over Recall:** Minimize cognitive load by using descriptive field labels, sensible placeholder hints, contextual tooltips on icon-only buttons, and auto-suggest/autocomplete where appropriate.
- **Semantic & Visual Consistency:** Maintain strict, predictable color semantics across all screens (Green for Approved/Success, Yellow/Amber for Pending/Warning, Red for Rejected/Danger, Blue for Primary Actions).
- **User Control & Freedom:** Provide easy exits from dialogs (close button, backdrop tap, `Escape` key) and clear cancel/reset options without trapping the user in a broken state.
- **Accessibility & Inclusivity (a11y):** Ensure strong text-to-background contrast (WCAG standards), keyboard accessibility, and proper ARIA labels (`aria-label`, `aria-hidden`) on icon-only buttons.

## 5. Role-Based Access Control (RBAC) & Enterprise Security Standards
- **Strict Server-Side Authorization:** Never rely solely on client-side JS or hidden HTML elements to enforce permissions. Every backend controller, endpoint (`process/*.php`), and data query MUST verify active `$_SESSION['user_id']` and authorize `$_SESSION['user_role']` before executing actions.
- **Row-Level & Ownership Validation:** Ensure users can only view, edit, or manipulate records permitted by their role (e.g. `requestor` restricted to their own `requestor_id`, `purchasing` restricted to restock requests, and elevated roles like `admin`/`approver` verified before approvals or modifications).
- **100% Prepared Statements (SQLi Prevention):** All SQL queries with variables MUST use PDO prepared statements with parameterized placeholders (`?` or `:name`). Direct variable concatenation into SQL queries is strictly prohibited.
- **XSS Sanitization:** Escape all dynamic user output rendered in HTML using `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- **Input Validation & Whitelisting:** Strictly sanitize, type-cast (`(int)`, `(float)`), and validate incoming parameters. Whitelist allowed statuses, action types, and sorting columns.
- **Atomic Transactions for Data Integrity:** Use database transactions (`beginTransaction()`, `commit()`, `rollBack()`) for multi-step mutations (e.g. stock level deductions, requisition status updates, and audit trail logging) to prevent orphaned or corrupt states.
- **Safe Error Handling:** Never output raw database errors or stack traces to end users. Log details securely on the server and return clean, friendly error messages.