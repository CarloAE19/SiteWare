/* ==========================================================
 * GB INVENTORY - SUPPLIERS LOGIC
 * Handles Supplier Modal behaviors and Code Auto-Generation
 * ========================================================== */

// Attached to window so the SPA Router doesn't lose it
window.openAddSupplierModal = function() {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-building-add me-2" style="color: var(--gb-yellow);"></i>Add New Supplier';
    document.getElementById('supplierFormAction').value = 'add_supplier';
    document.getElementById('supplierId').value = '';
    
    // --- AUTO-GENERATE THE SUPPLIER CODE ---
    const randomNum = Math.floor(Math.random() * 9000 + 1000);
    document.getElementById('supplierCode').value = 'SUP-' + randomNum;
    
    // Clear the rest of the form
    document.getElementById('supplierCompany').value = '';
    document.getElementById('supplierContactPerson').value = '';
    document.getElementById('supplierContactNumber').value = '';
    document.getElementById('supplierEmail').value = '';
    document.getElementById('supplierAddress').value = '';
    document.getElementById('supplierStatus').value = 'Active';
}

window.openEditSupplierModal = function(id, code, company, person, number, email, address, status) {
    document.getElementById('supplierModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2" style="color: var(--gb-yellow);"></i>Edit Supplier';
    document.getElementById('supplierFormAction').value = 'edit_supplier';
    document.getElementById('supplierId').value = id;
    
    // Fill the form with existing data
    document.getElementById('supplierCode').value = code;
    document.getElementById('supplierCompany').value = company;
    document.getElementById('supplierContactPerson').value = person;
    document.getElementById('supplierContactNumber').value = number;
    document.getElementById('supplierEmail').value = email;
    document.getElementById('supplierAddress').value = address;
    document.getElementById('supplierStatus').value = status;
}