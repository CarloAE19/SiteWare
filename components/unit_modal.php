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

                    <div class="mb-3">
                        <label class="form-label fw-bold">Low Stock Alert Level</label>
                        <div class="input-group">
                            <span class="input-group-text bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i></span>
                            <input type="number" class="form-control fw-bold text-center" name="reorder_level" id="unitReorderLevel" placeholder="10" required min="1" value="10">
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Items using this unit will be flagged as <span class="badge bg-warning text-dark">Low Stock</span> when quantity falls to or below this number.</small>
                        <small class="text-success mt-1 d-block fw-bold" id="reorderAutoHint" style="display:none;"></small>
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
// ==========================================
// SMART REORDER LEVEL AUTO-SUGGESTION
// Automatically suggests a reorder level based on unit name keywords
// ==========================================
const reorderSuggestions = [
    { keywords: ['cubic meter', 'cubic yard', 'cubic feet', 'cubic foot', 'cu.m', 'cu.ft'], level: 5,  label: 'Bulk Volume' },
    { keywords: ['kilogram', 'kilo', 'ton', 'tonne', 'quintal'],                           level: 20, label: 'Weight' },
    { keywords: ['liter', 'litre', 'gallon', 'barrel'],                                     level: 5,  label: 'Liquid' },
    { keywords: ['meter', 'metre', 'feet', 'foot', 'yard', 'centimeter', 'inch'],           level: 15, label: 'Length' },
    { keywords: ['bag', 'sack'],                                                             level: 10, label: 'Packaged' },
    { keywords: ['box', 'bundle', 'pack', 'carton', 'case'],                                level: 10, label: 'Packaged' },
    { keywords: ['piece', 'unit', 'roll', 'sheet', 'pair', 'set', 'item'],                  level: 10, label: 'Countable' },
    { keywords: ['board foot', 'board feet', 'bdft'],                                        level: 15, label: 'Lumber' },
    { keywords: ['square meter', 'square feet', 'square foot', 'sq.m', 'sq.ft'],            level: 10, label: 'Area' },
];

function suggestReorderLevel(unitName) {
    const name = unitName.toLowerCase().trim();
    if (!name) return { level: 10, label: 'Default' };
    
    for (const rule of reorderSuggestions) {
        for (const keyword of rule.keywords) {
            if (name.includes(keyword)) {
                return { level: rule.level, label: rule.label };
            }
        }
    }
    return { level: 10, label: 'Default' };
}

// Track whether we are in "add" mode (auto-suggest) or "edit" mode (don't override)
let isAddMode = true;
let userManuallyChanged = false;

function openAddUnitModal() {
    isAddMode = true;
    userManuallyChanged = false;
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add New Unit';
    document.getElementById('unitFormAction').value = 'add_unit';
    document.getElementById('unitId').value = '';
    document.getElementById('unitName').value = '';
    document.getElementById('unitAbbrev').value = '';
    document.getElementById('unitReorderLevel').value = '10';
    updateReorderHint('Default', 10);
}

function openEditUnitModal(id, name, abbrev, reorderLevel) {
    isAddMode = false;
    userManuallyChanged = false;
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Unit';
    document.getElementById('unitFormAction').value = 'edit_unit';
    document.getElementById('unitId').value = id;
    document.getElementById('unitName').value = name;
    document.getElementById('unitAbbrev').value = abbrev;
    document.getElementById('unitReorderLevel').value = reorderLevel;
    updateReorderHint(null, reorderLevel); // Don't show auto-detected label for edit mode
    new bootstrap.Modal(document.getElementById('unitModal')).show();
}

function updateReorderHint(label, level) {
    const hintEl = document.getElementById('reorderAutoHint');
    if (!hintEl) return;
    
    if (label) {
        hintEl.innerHTML = `<i class="bi bi-magic me-1"></i>Auto-detected: <span class="badge bg-primary">${label}</span> → suggested threshold: <strong>${level}</strong>`;
        hintEl.style.display = 'block';
    } else {
        hintEl.style.display = 'none';
    }
}

// Listen for typing in the unit name field — auto-suggest reorder level
document.addEventListener('DOMContentLoaded', function() {
    const unitNameInput = document.getElementById('unitName');
    const reorderInput = document.getElementById('unitReorderLevel');
    
    if (unitNameInput) {
        unitNameInput.addEventListener('input', function() {
            if (!isAddMode || userManuallyChanged) return;
            
            const suggestion = suggestReorderLevel(this.value);
            reorderInput.value = suggestion.level;
            updateReorderHint(suggestion.label, suggestion.level);
        });
    }
    
    // Track if user manually changes the reorder level
    if (reorderInput) {
        reorderInput.addEventListener('input', function() {
            userManuallyChanged = true;
            updateReorderHint(null, this.value); // Hide auto-hint when user manually changes
        });
    }
});
</script>