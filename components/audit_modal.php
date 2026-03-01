<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-ruled me-2" style="color: var(--gb-yellow);"></i>Audit Trail Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <h5 class="fw-bold text-primary mb-3" id="modalAuditMonth">Month</h5>
                
                <div class="table-responsive bg-white border rounded">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>Item Code</th><th>Item Name</th><th class="text-center">System Qty</th><th class="text-center">Physical Qty</th><th class="text-center">Discrepancy</th></tr></thead>
                        <tbody id="auditModalBody"></tbody>
                    </table>
                </div>
                
                <div class="mt-3"><h6 class="fw-bold mb-1">Remarks:</h6><p class="text-muted small border p-2 bg-white rounded" id="modalAuditRemarks">No remarks.</p></div>
            </div>
        </div>
    </div>
</div>