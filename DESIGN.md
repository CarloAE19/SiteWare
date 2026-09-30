# CIMS Design System & UI/UX Architecture Specification

**Project:** Construction / Corporate Inventory Management System (CIMS)  
**Classification:** Enterprise Design System & UI/UX Architecture Document  
**Standard Alignment:** Human-Computer Interaction (HCI) Principles, WCAG 2.1 Contrast Standards, ISO/IEC 25010 (Usability & Portability Characteristics)

---

## 1. Executive Summary & Design Vision
The Construction Inventory Management System (CIMS) design system provides a cohesive, enterprise-grade user interface tailored for field personnel, warehouse custodians, purchasing agents, and executive approvers. The interface balances high-density data management (stock tables, multi-item requisitions, audit discrepancies) with seamless usability across all device form factors—from compact mobile phones on construction job sites to multi-monitor desktop workstations.

---

## 2. Visual Identity & Brand Design Tokens

### 2.1. Color Palette

The CIMS visual identity is anchored around the brand colors of GB Construction, extended with semantic functional status colors:

| Token Name | Hex Code | Visual Sample / Usage | Semantic Role |
| :--- | :--- | :--- | :--- |
| `--gb-blue` | `#0033CC` | Deep Royal Blue | Primary brand tone, primary buttons, active navigation states. |
| `--gb-red` | `#D80000` | Crimson Red | Danger actions, rejection badges, missing stock, required asterisks. |
| `--gb-yellow` | `#FFD700` | Golden Amber | Warning alerts, pending approvals, active tab accents. |
| `--gb-dark` | `#0A111F` | Deep Navy | High-contrast sidebar, primary text hierarchy, dark mode elements. |
| `--gb-bg` | `#F4F6F9` | Cloud Gray | Primary app canvas and page background. |
| `--cims-success` | `#198754` | Emerald Green | Approved requisitions, matched inventory counts, completed status. |
| `--cims-info` | `#0DCAF0` | Cyan / Sky Blue | Surplus counts, informational banners, restock highlights. |
| `--cims-viber` | `#7360F2` | Viber Purple | Instant messaging integration, notification triggers. |

### 2.2. Typography Hierarchy
- **Primary Font Family:** `'Segoe UI', -apple-system, BlinkMacSystemFont, Tahoma, Geneva, Verdana, sans-serif`
- **Readability Rules:**
  - Page Titles (`h1` / `.page-title`): `1.5rem` – `1.75rem`, Semi-bold (`600`), color: `--gb-dark`.
  - Section Headers (`h2` / `h5`): `1.15rem` – `1.25rem`, Medium (`500`).
  - Body & Table Text: `0.9375rem` (`15px`), regular (`400`), line-height: `1.5`.
  - Captions & Meta Timestamps: `0.8125rem` (`13px`), color: `#6c757d`.
  - Mobile Form Inputs: Minimum `16px` on phone viewports to prevent iOS Safari auto-zooming.

---

## 3. Human-Computer Interaction (HCI) Principles in Practice

CIMS adheres to established HCI heuristics (Nielsen Norman Group) to eliminate cognitive strain in fast-paced warehouse and project environments:

### 3.1. Visibility of System Status
- **Loading & Processing States:** When AJAX actions are dispatched (e.g., approving a requisition, submitting stock adjustments), the submit trigger is instantly disabled, displaying an animated spinner and descriptive label (`Saving...`, `Processing...`).
- **Real-Time Feedback:** Mutations trigger non-blocking feedback (SweetAlert2 toasts or dismissible bootstrap banners) confirming successful execution or actionable errors.

### 3.2. Error Prevention & Defensive Interaction
- **Input Boundaries:** Form inputs for physical stock and requested quantities enforce non-negative numbers (`min="1"`).
- **Destructive Action Confirmations:** Irreversible actions (canceling requisitions, deleting items, clearing audit logs) require an explicit confirmation prompt with distinct danger styling.
- **Form Memory & Clean Exits:** Modals support effortless dismissal via `Escape` key, backdrop tapping, or explicit `Cancel` buttons, with form fields cleanly sanitized upon closing.

### 3.3. Recognition Over Recall
- **Status Badging:** Users instantly recognize requisition stages through standardized color-coded pill badges:
  - `badge bg-success`: Approved / Issued / 0 Match
  - `badge bg-warning text-dark`: Pending Approval / In Review
  - `badge bg-danger`: Rejected / Missing Items / Out of Stock
  - `badge bg-info text-dark`: Surplus Count
- **Auto-Suggestions & Dynamic Filtering:** Item selection uses predictive search filtering so users do not need to memorize alphanumeric stock keeping units (SKUs) or item codes.

---

## 4. Universal Mobile & Multi-Device Optimization Guide

CIMS is engineered as a responsive web application delivering first-class UX across all mobile device form factors:

```
+---------------------------------------------------------------------------------+
| Compact Phones      | Standard Phones       | Phablets / Foldables | Desktops   |
| (320px - 360px)     | (375px - 430px)       | (430px - 768px)      | (992px+)   |
| iPhone SE, Mini     | iPhone 14/15, Galaxy  | Pro Max, Folded/Tabs | Laptops/PC |
+---------------------+-----------------------+----------------------+------------+
| Single-column stack | Fluid grid layout     | Adaptive 2-col grids | Full Table |
| Fullscreen modal    | Scrollable dialog     | Responsive modal     | Offcanvas  |
| Sticky bottom CTAs  | Bottom touch toolbar  | Multi-action bars    | Top/Side   |
+---------------------------------------------------------------------------------+
```

### 4.1. Screen Size Adaptations
1. **Compact & Small Displays (`320px – 360px`):**
   - Zero horizontal overflow (`overflow-x: hidden`).
   - Dynamic form rows (material select + quantity + notes + remove button) stack vertically with 100% width.
   - Long alphanumeric codes wrap safely using `.text-break`.
2. **Standard & Phablet Displays (`375px – 600px`):**
   - Tables utilize touch-scroll containers (`.table-responsive`) with sticky first columns or transform into readable stacked card lists.
   - Floating Action Buttons (FABs) and action buttons expand to full width at the bottom of the viewport for comfortable one-thumb ergonomics.
3. **Touch Target Dimensions:**
   - All interactive touch targets (buttons, links, select menus, modal close icons) maintain a minimum footprint of **44x44px** (WCAG 2.5.5 Level AAA recommendation is 44px+).

### 4.2. Virtual Keyboard Awareness
- Form modals utilize `.modal-dialog-scrollable` or `.modal-fullscreen-sm-down` to ensure modal headers and footer submission buttons remain accessible without being obscured when the on-screen software keyboard opens.

---

## 5. Reusable Component Anatomy

### 5.1. Buttons & Action Triggers
```html
<!-- Primary Brand Button -->
<button class="btn btn-primary px-4 py-2 fw-semibold">
    <i class="bi bi-check2-circle me-1"></i> Submit Request
</button>

<!-- Danger / Rejection Button -->
<button class="btn btn-outline-danger px-3 py-2">
    <i class="bi bi-x-circle me-1"></i> Reject
</button>

<!-- Viber / Notification Trigger -->
<button class="btn btn-viber px-3 py-2">
    <i class="bi bi-chat-dots me-1"></i> Notify via Viber
</button>
```

### 5.2. Data Tables & Card Containers
- **Card Containers:** Wrapped in clean `.card` wrappers with subtle elevation (`box-shadow: 0 4px 12px rgba(0,0,0,0.05)`), rounded corners (`border-radius: 8px`), and a clean 1px border.
- **Table Headers:** Distinct dark or muted headers (`.table-light` or `bg-dark text-white`) with uppercase font labels for quick scannability.

### 5.3. Modal Dialog Architecture
```html
<div class="modal fade" id="cimsModal" tabindex="-1" aria-labelledby="cimsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="cimsModalTitle">Requisition Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Dynamic form fields -->
            </div>
            <div class="modal-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
```

---

## 6. Maintenance & Quality Checklist
When developing new features or refactoring existing modules:
- [ ] Does the screen adhere to the official brand color tokens?
- [ ] Is the interface responsive down to a 320px viewport without horizontal page scrolling?
- [ ] Do all interactive elements meet the 44x44px touch target standard?
- [ ] Are async operations supported by instant loading states and feedback toasts?
- [ ] Is input font size at least 16px to prevent mobile browser zoom?
- [ ] Are all icon-only buttons equipped with descriptive `aria-label` tags?
