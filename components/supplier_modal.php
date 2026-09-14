<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="supplierModalTitle"><span style="color: var(--gb-yellow);">Add Supplier</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="supplierForm" method="POST" action="process/process.php" novalidate>
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" id="supplierFormAction" value="add_supplier">
                    <input type="hidden" name="id" id="supplierId" value="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary"><i class="bi bi-building me-2"></i>Company Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Supplier Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold text-success bg-white shadow-sm" name="supplier_code" id="supplierCode" required readonly>
                            <small class="text-muted" style="font-size: 0.7rem;">Auto-generated</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted text-uppercase">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold shadow-sm" name="company_name" id="supplierCompany" placeholder="e.g. Holcim Philippines" required>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 border-bottom pb-2 text-primary mt-4"><i class="bi bi-person-lines-fill me-2"></i>Contact Information & Viber Logistics</h6>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Contact Person <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-bold shadow-sm" name="contact_person" id="supplierContactPerson" placeholder="e.g. Maria Clara" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center mb-1">
                                <span>Contact Number <span class="text-danger">*</span></span>
                                <span id="supplierViberBadge" class="badge bg-purple d-none shadow-sm" style="font-size: 0.68rem;">
                                    <i class="fa-brands fa-viber me-1"></i>Viber Ready
                                </span>
                            </label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-telephone-fill"></i></span>
                                <input type="text" class="form-control fw-bold shadow-sm" name="contact_number" id="supplierContactNumber" placeholder="e.g. 0917-123-4567 or +63 917 123 4567" required>
                            </div>
                            <div id="supplierContactNumberFeedback" class="small mt-1 text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-info-circle me-1"></i>Accepts 11-digit mobile (09XX) or international (+639XX) for direct Viber integration.
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold small text-muted text-uppercase">Email Address</label>
                            <input type="email" class="form-control fw-bold shadow-sm" name="email" id="supplierEmail" placeholder="e.g. sales@holcim.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Status</label>
                            <select class="form-select fw-bold shadow-sm" name="status" id="supplierStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive" class="text-danger">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2 mt-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Business Address</label>
                        <textarea class="form-control fw-bold shadow-sm" name="address" id="supplierAddress" rows="2" placeholder="Full business address..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm" id="supplierSubmitBtn"><i class="bi bi-save me-1"></i> Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ==========================================================
// SUPPLIER MODAL LOGIC & VIBER PHONE NORMALIZER
// Follows CIMS Modal & AJAX Handler Skill Standards
// ==========================================================

window.normalizeViberPhoneClient = function(phone) {
    if (!phone) return null;
    const digits = phone.replace(/[^0-9]/g, '');
    if (digits.startsWith('09') && digits.length === 11) {
        return '+63' + digits.substring(1);
    }
    if (digits.startsWith('639') && digits.length === 12) {
        return '+' + digits;
    }
    if (digits.startsWith('9') && digits.length === 10) {
        return '+63' + digits;
    }
    return null;
};

window.updateSupplierViberPreview = function() {
    const input = document.getElementById('supplierContactNumber');
    const badge = document.getElementById('supplierViberBadge');
    const feedback = document.getElementById('supplierContactNumberFeedback');
    if (!input || !badge || !feedback) return;

    const val = input.value.trim();
    if (!val) {
        badge.classList.add('d-none');
        feedback.className = 'small mt-1 text-muted';
        feedback.innerHTML = '<i class="bi bi-info-circle me-1"></i>Accepts 11-digit mobile (09XX) or international (+639XX) for direct Viber integration.';
        return;
    }

    const norm = window.normalizeViberPhoneClient(val);
    if (norm) {
        badge.classList.remove('d-none');
        feedback.className = 'small mt-1 text-success fw-semibold';
        feedback.innerHTML = '<i class="fa-brands fa-viber me-1" style="color: #7360f2;"></i>Viber Direct Link Ready: ' + norm;
    } else {
        badge.classList.add('d-none');
        feedback.className = 'small mt-1 text-warning fw-semibold';
        feedback.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Please enter a valid mobile number (e.g. 0917-123-4567 or +63 917 123 4567).';
    }
};

window.openAddSupplierModal = function() {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-building-add me-2" style="color: var(--gb-yellow);"></i>Add New Supplier';
    document.getElementById('supplierFormAction').value = 'add_supplier';
    document.getElementById('supplierId').value = '';
    
    // Auto-generate the code
    const randomNum = Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('supplierCode').value = 'SUP-' + randomNum;
    
    // Clear form
    document.getElementById('supplierCompany').value = '';
    document.getElementById('supplierContactPerson').value = '';
    document.getElementById('supplierContactNumber').value = '';
    document.getElementById('supplierEmail').value = '';
    document.getElementById('supplierAddress').value = '';
    document.getElementById('supplierStatus').value = 'Active';

    window.updateSupplierViberPreview();
};

window.openEditSupplierModal = function(id, code, company, person, number, email, address, status) {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2" style="color: var(--gb-yellow);"></i>Edit Supplier';
    document.getElementById('supplierFormAction').value = 'edit_supplier';
    document.getElementById('supplierId').value = id;
    
    // Fill the form
    document.getElementById('supplierCode').value = code || '';
    document.getElementById('supplierCompany').value = company || '';
    document.getElementById('supplierContactPerson').value = person || '';
    document.getElementById('supplierContactNumber').value = number || '';
    document.getElementById('supplierEmail').value = email || '';
    document.getElementById('supplierAddress').value = address || '';
    document.getElementById('supplierStatus').value = status || 'Active';

    window.updateSupplierViberPreview();
};

// Lifecycle Events & AJAX Submission (CIMS Standard)
window.initSupplierModalEvents = function() {
    const modalEl = document.getElementById('supplierModal');
    const form = document.getElementById('supplierForm');
    const phoneInput = document.getElementById('supplierContactNumber');

    if (phoneInput) {
        phoneInput.addEventListener('input', window.updateSupplierViberPreview);
        phoneInput.addEventListener('blur', window.updateSupplierViberPreview);
    }

    if (modalEl) {
        // Auto-focus on first editable field
        modalEl.addEventListener('shown.bs.modal', () => {
            const companyInput = document.getElementById('supplierCompany');
            if (companyInput) companyInput.focus();
        });

        // Reset state cleanly when dismissed
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (form) {
                form.classList.remove('was-validated');
            }
            window.updateSupplierViberPreview();
        });
    }

    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('supplierSubmitBtn');
            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-save me-1"></i> Save Supplier';

            const phoneVal = phoneInput ? phoneInput.value.trim() : '';
            if (!phoneVal) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Contact Number Required',
                        text: 'Please enter a contact number for supplier logistics.',
                        confirmButtonColor: '#7360f2'
                    });
                } else {
                    alert('Please enter a contact number for supplier logistics.');
                }
                phoneInput.focus();
                return;
            }

            // Client-side phone verification
            const normPhone = window.normalizeViberPhoneClient(phoneVal);
            if (!normPhone) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Contact Number',
                        text: 'Please provide a valid Philippine mobile number (e.g., 0917-123-4567 or +63 917 123 4567) so Viber direct messaging can function reliably.',
                        confirmButtonColor: '#7360f2'
                    });
                } else {
                    alert('Please provide a valid Philippine mobile number (e.g., 0917-123-4567 or +63 917 123 4567) for Viber.');
                }
                phoneInput.focus();
                return;
            }

            // Prevent double submit and show loading spinner
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';
            }

            try {
                const formData = new FormData(form);
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                                  form.querySelector('input[name="csrf_token"]')?.value || '';

                const response = await fetch('process/process.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    }
                });

                const result = await response.json();
                const isSuccess = result.success === true || result.status === 'success';

                if (isSuccess) {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: result.message || 'Supplier saved successfully.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }

                    // Reload page to reflect updated supplier list with valid Viber deeplinks
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to save supplier.');
                }
            } catch (err) {
                console.error('Supplier save error:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: err.message || 'An error occurred while saving the supplier.',
                        confirmButtonColor: '#7360f2'
                    });
                } else {
                    alert(err.message || 'An error occurred while saving the supplier.');
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            }
        });
    }
};

// Immediate execution for SPA compatibility
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initSupplierModalEvents);
} else {
    window.initSupplierModalEvents();
}
</script>