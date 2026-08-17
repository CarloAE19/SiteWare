<div class="modal fade" id="unitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="unitModalTitle"><span style="color: var(--gb-yellow);">Add Unit</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="unitFormAction" value="add_unit">
                    <input type="hidden" name="unit_id" id="unitId" value="">
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-bold mb-0">Unit / Material Name</label>
                            <button type="button" id="btnAiUnitSuggest" class="btn btn-sm btn-outline-primary border-0 py-0 px-2 fw-semibold" style="font-size: 0.8rem;" onclick="fetchAiSiteWareUnitSuggestion()">
                                <i class="bi bi-robot me-1"></i>AI SiteWare Assistant
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" name="unit_name" id="unitName" placeholder="e.g. Cubic Meters, Portland Cement, 16mm Rebar" required>
                            <button type="button" class="btn btn-outline-primary" id="btnAiUnitIcon" onclick="fetchAiSiteWareUnitSuggestion()" title="Analyze with AI SiteWare Assistant">
                                <i class="bi bi-stars"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Abbreviation</label>
                        <input type="text" class="form-control" name="abbreviation" id="unitAbbrev" placeholder="e.g. cu.m, bg, pcs, kg" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Low Stock Alert Level</label>
                        <div class="input-group">
                            <span class="input-group-text bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i></span>
                            <input type="number" class="form-control fw-bold text-center" name="reorder_level" id="unitReorderLevel" placeholder="10" required min="1" value="10">
                        </div>
                        <small class="text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Items using this unit will trigger <span class="badge bg-warning text-dark">Low Stock</span> alerts when quantity drops to or below this threshold.</small>
                        
                        <!-- AI INSIGHT / AUTO HINT BOX -->
                        <div id="reorderAutoHint" class="alert alert-info py-2 px-3 mt-2 mb-0 border-0 shadow-sm" style="display:none; font-size: 0.85rem;">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-bold text-primary" id="aiHintSource"><i class="bi bi-robot me-1"></i>AI SiteWare Assistant</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" id="aiHintBadge">Optimal ROP</span>
                            </div>
                            <div id="aiHintText" class="text-muted"></div>
                        </div>
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
// AI SITEWARE ASSISTANT - UNIT & REORDER POINT ADVISOR
// ==========================================
let isAddMode = true;
let userManuallyChanged = false;
let aiDebounceTimer = null;

function openAddUnitModal() {
    isAddMode = true;
    userManuallyChanged = false;
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add New Unit';
    document.getElementById('unitFormAction').value = 'add_unit';
    document.getElementById('unitId').value = '';
    document.getElementById('unitName').value = '';
    document.getElementById('unitAbbrev').value = '';
    document.getElementById('unitReorderLevel').value = '10';
    hideAiHint();
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
    hideAiHint();
    new bootstrap.Modal(document.getElementById('unitModal')).show();
}

function hideAiHint() {
    const hintEl = document.getElementById('reorderAutoHint');
    if (hintEl) hintEl.style.display = 'none';
}

function showAiHint(text, source = 'AI SiteWare Assistant', badge = 'Optimal ROP') {
    const hintEl = document.getElementById('reorderAutoHint');
    const textEl = document.getElementById('aiHintText');
    const sourceEl = document.getElementById('aiHintSource');
    const badgeEl = document.getElementById('aiHintBadge');
    if (!hintEl || !textEl) return;

    sourceEl.innerHTML = `<i class="bi bi-robot text-primary me-1"></i>${source}`;
    badgeEl.textContent = badge;
    textEl.innerHTML = text;
    hintEl.style.display = 'block';
}

async function fetchAiSiteWareUnitSuggestion() {
    const unitNameInput = document.getElementById('unitName');
    const abbrevInput = document.getElementById('unitAbbrev');
    const reorderInput = document.getElementById('unitReorderLevel');
    const aiBtn = document.getElementById('btnAiUnitSuggest');
    const aiIcon = document.getElementById('btnAiUnitIcon');

    const query = unitNameInput ? unitNameInput.value.trim() : '';
    if (!query) {
        unitNameInput.focus();
        showAiHint('Please enter a unit name or material type (e.g. <em>Cubic Meters</em>, <em>Bags</em>, or <em>Portland Cement</em>) first.', 'AI SiteWare Assistant', 'Input Required');
        return;
    }

    // Show loading indicator
    if (aiBtn) aiBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Thinking...';
    if (aiIcon) aiIcon.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    try {
        const response = await fetch('process/ai_unit_advisor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ input_text: query })
        });
        const data = await response.json();

        if (data && !data.error) {
            if (data.unit_name && (!unitNameInput.value || isAddMode)) {
                unitNameInput.value = data.unit_name;
            }
            if (data.abbreviation) {
                abbrevInput.value = data.abbreviation;
            }
            if (data.reorder_level) {
                reorderInput.value = data.reorder_level;
            }
            
            const sourceLabel = 'AI SiteWare Assistant';
            showAiHint(data.rationale || 'Optimal threshold calculated based on typical construction consumption velocity.', sourceLabel, `Alert: ≤ ${data.reorder_level} ${data.abbreviation}`);
        } else if (data.error) {
            showAiHint(data.error, 'Notice', 'Warning');
        }
    } catch (err) {
        console.warn('AI Advisor Error:', err);
    } finally {
        if (aiBtn) aiBtn.innerHTML = '<i class="bi bi-robot me-1"></i>AI SiteWare Assistant';
        if (aiIcon) aiIcon.innerHTML = '<i class="bi bi-stars"></i>';
    }
}

// Auto-trigger on debounced input
document.addEventListener('DOMContentLoaded', function() {
    const unitNameInput = document.getElementById('unitName');
    const reorderInput = document.getElementById('unitReorderLevel');

    if (unitNameInput) {
        unitNameInput.addEventListener('input', function() {
            if (!isAddMode || userManuallyChanged) return;
            clearTimeout(aiDebounceTimer);
            const val = this.value.trim();
            if (val.length >= 3) {
                aiDebounceTimer = setTimeout(() => {
                    fetchAiSiteWareUnitSuggestion();
                }, 800);
            }
        });
    }

    if (reorderInput) {
        reorderInput.addEventListener('input', function() {
            userManuallyChanged = true;
        });
    }
});
</script>