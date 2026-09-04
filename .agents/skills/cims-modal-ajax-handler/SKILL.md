---
name: cims-modal-ajax-handler
description: Best practices, standards, and workflow patterns for building and modifying Bootstrap modals and AJAX form interactions in the CIMS project.
---

# CIMS Modal & AJAX Handler Guide

This skill provides guidelines and standardized code patterns for creating, editing, and managing modal dialogs and asynchronous (AJAX/Fetch) interactions across the CIMS platform.

---

## 1. Core Principles

1. **Never Reload Unnecessarily:** Use AJAX/Fetch to submit modal forms and dynamically update table rows or status badges.
2. **Prevent Double Submissions:** Always disable the submit/action button and display a loading spinner during ongoing network requests.
3. **Always Clean Up on Modal Close:** Reset form inputs, validation error states, and toggle states when the modal is closed using Bootstrap's `hidden.bs.modal` event.
4. **Graceful Error Handling:** Parse backend responses and present user-friendly error messages (e.g., using SweetAlert2 or contextual alert banners) rather than failing silently.
5. **Mobile & Touch Optimization:** Ensure modal bodies scroll smoothly (`modal-dialog-scrollable`), buttons have adequate tap sizes, and dynamic rows stack gracefully on smaller screens.

---

## 2. Standard AJAX Modal Submission Pattern

When implementing a form submission inside a Bootstrap modal, follow this template:

```javascript
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('exampleForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Save';

        // 1. Client-side validation check
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // 2. Prevent duplicate submits & show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...';
        }

        try {
            const formData = new FormData(form);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const response = await fetch('controllers/example_action.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            // Compatible with both 'success: true' and 'status: "success"'
            const isSuccess = result.success === true || result.status === 'success';

            if (isSuccess) {
                // Hide modal
                const modalEl = document.getElementById('exampleModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // Success alert
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Action completed successfully.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }

                // Dynamically refresh table or target element
                if (typeof refreshDataTable === 'function') {
                    refreshDataTable();
                } else if (result.data && result.data.id) {
                    // Optional In-Place Row Update without full reload
                    const rowStatusBadge = document.getElementById(`status_${result.data.id}`);
                    if (rowStatusBadge && result.data.new_status) {
                        rowStatusBadge.textContent = result.data.new_status;
                    }
                }
            } else {
                throw new Error(result.message || 'Something went wrong.');
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Failed to process request.'
                });
            } else {
                alert(error.message || 'Failed to process request.');
            }
        } finally {
            // Restore submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });
});
```

---

## 3. Modal Lifecycle & Accessibility Patterns

Always ensure modals auto-focus the primary input on open and reset cleanly when dismissed:

```javascript
const modalEl = document.getElementById('exampleModal');
if (modalEl) {
    // 1. Accessibility: Auto-focus the first editable input when opened
    modalEl.addEventListener('shown.bs.modal', () => {
        const firstInput = modalEl.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) firstInput.focus();
    });

    // 2. Clean up: Reset form and preview states when closed
    modalEl.addEventListener('hidden.bs.modal', () => {
        const form = modalEl.querySelector('form');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        // Clear any custom dynamic preview containers or error labels
        const dynamicPreviews = modalEl.querySelectorAll('.preview-container');
        dynamicPreviews.forEach(el => el.innerHTML = '');
    });
}
```

---

## 4. Mobile & Multi-Device Modal Standards

- **Scrollable Modals:** Use `modal-dialog-scrollable` so long forms (like multi-item requisition tables) remain easily navigable when the virtual keyboard is open.
- **Dynamic Row Stacking:** On mobile screens (`<768px`), form rows with item selectors, quantities, notes, and remove buttons must stack vertically or use responsive grids (`col-12 col-md-6 col-lg-3`) so elements do not squish or overflow.
- **Touch-Friendly Hit Targets:** Buttons and delete icons must have sufficient padding (minimum 40x40px touch area) to prevent misclicks on touch devices.

---

## 5. Backend PHP Response Standard

All backend endpoints responding to AJAX requests should return a standardized JSON response:

```php
<?php
header('Content-Type: application/json; charset=utf-8');

// Ensure only authenticated users can access
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

try {
    // Process request ...
    
    echo json_encode([
        'success' => true,
        'status' => 'success',
        'message' => 'Operation completed successfully.',
        'data' => [
            // Optional returned payload
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
```
