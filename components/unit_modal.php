<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="unitModalTitle"><span style="color: var(--gb-yellow);">Add Unit</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="unitFormAction" value="add_unit">
                    <input type="hidden" name="unit_id" id="unitId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Unit Name</label>
                        <input type="text" class="form-control" name="unit_name" id="unitName" placeholder="e.g. Cubic Meters" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Abbreviation</label>
                        <input type="text" class="form-control" name="abbreviation" id="unitAbbrev" placeholder="e.g. m3" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">Save Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddUnitModal() {
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add New Unit';
    document.getElementById('unitFormAction').value = 'add_unit';
    document.getElementById('unitId').value = '';
    document.getElementById('unitName').value = '';
    document.getElementById('unitAbbrev').value = '';
}

function openEditUnitModal(id, name, abbrev) {
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Unit';
    document.getElementById('unitFormAction').value = 'edit_unit';
    document.getElementById('unitId').value = id;
    document.getElementById('unitName').value = name;
    document.getElementById('unitAbbrev').value = abbrev;
    new bootstrap.Modal(document.getElementById('unitModal')).show();
}
</script>