<!-- Premium Mobile Card Table CSS for Modal -->
<style>
    @media (max-width: 767.98px) {
        #auditDetailsTable { display: block; width: 100%; background: transparent !important; }
        #auditDetailsTable thead { display: none; }
        #auditDetailsTable tbody { display: block; width: 100%; }
        
        #auditDetailsTable tbody tr { 
            display: flex; flex-direction: column; border: 1px solid #e0e4e8; border-radius: 12px; 
            margin-bottom: 1rem; background: #fff; padding: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        }
        
        #auditDetailsTable tbody td { 
            display: flex; justify-content: space-between; align-items: center; text-align: right; 
            padding: 10px 4px; border: none; border-bottom: 1px dashed #e9ecef; white-space: normal !important; word-break: break-word; 
        }
        
        #auditDetailsTable tbody td:last-child { border-bottom: none; }
        
        #auditDetailsTable tbody td::before { 
            content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #6c757d; 
            text-transform: uppercase; text-align: left; padding-right: 15px; flex-shrink: 0; 
        }
    }
</style>

<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-ruled me-2" style="color: var(--gb-yellow);"></i>Audit Trail Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                
                <div class="mb-4 border-bottom pb-3">
                    <h4 class="fw-bold text-primary mb-0" id="modalAuditMonth">Month</h4>
                    <small class="text-muted fw-bold text-uppercase">Monthly Recount Report</small>
                </div>
                
                <h6 class="fw-bold text-uppercase small text-muted mb-2">Itemized Count Results:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm" style="border: none !important; box-shadow: none !important; background: transparent !important;">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap" id="auditDetailsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-3">Item Code</th>
                                <th class="py-3">Item Name</th>
                                <th class="text-center py-3">System Qty</th>
                                <th class="text-center py-3">Physical Qty</th>
                                <th class="text-center py-3" style="width: 140px;">Discrepancy</th>
                            </tr>
                        </thead>
                        <tbody id="auditModalBody"></tbody>
                    </table>
                </div>
                
                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Notes:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="modalAuditRemarks" style="min-height: 60px;">No remarks.</p>
                </div>
                
            </div>
            <div class="modal-footer bg-white border-top-0">
                <button type="button" class="btn btn-secondary fw-bold px-4 w-100 w-md-auto shadow-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================================
// AUDIT MODAL LOGIC (Directly injected to bypass mobile cache!)
// ==========================================================

window.viewAuditDetails = function(month, remarks, itemsJson) {
    document.getElementById('modalAuditMonth').innerText = "Audit: " + month;
    
    const remarksEl = document.getElementById('modalAuditRemarks');
    remarksEl.innerText = remarks ? remarks : 'No notes provided.';
    remarksEl.style.whiteSpace = 'pre-wrap';
    
    let tbody = document.getElementById('auditModalBody');
    tbody.innerHTML = '';
    
    try {
        let items = JSON.parse(itemsJson);
        if (items.length > 0) {
            items.forEach(item => {
                let diff = parseInt(item.discrepancy);
                let diffDisplay = '';
                
                // Removed w-100 so badges fit perfectly next to labels on mobile
                if (diff < 0) {
                    diffDisplay = `<span class="badge bg-danger shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-arrow-down-circle-fill me-1"></i>${diff} Short</span>`;
                } else if (diff > 0) {
                    diffDisplay = `<span class="badge bg-warning text-dark shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-arrow-up-circle-fill me-1"></i>+${diff} Over</span>`;
                } else {
                    diffDisplay = `<span class="badge bg-success shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-check-circle-fill me-1"></i>Match</span>`;
                }
                
                // Injected data-label into every td so it works on mobile
                tbody.innerHTML += `
                    <tr>
                        <td data-label="Item Code" class="text-muted small align-middle px-3 fw-bold">${item.item_code}</td>
                        <td data-label="Item Name" class="fw-bold align-middle text-dark">${item.item_name}</td>
                        <td data-label="System Qty" class="text-end text-md-center align-middle text-secondary fw-bold fs-6">${item.system_qty}</td>
                        <td data-label="Physical Qty" class="text-end text-md-center align-middle text-primary fw-bold fs-6">${item.physical_qty}</td>
                        <td data-label="Discrepancy" class="text-end text-md-center align-middle">${diffDisplay}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No items recorded in this audit.</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error loading audit details.</td></tr>`;
    }
    
    // SPA-Safe Modal Instantiation
    var myModalEl = document.getElementById('auditModal');
    var auditModal = bootstrap.Modal.getInstance(myModalEl);
    if (!auditModal) {
        auditModal = new bootstrap.Modal(myModalEl);
    }
    auditModal.show();
}
</script>