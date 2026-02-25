<?php if (in_array($role, ['warehouse', 'admin'])): ?>
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-box-arrow-up-right me-2" style="color: var(--gb-yellow);"></i>Release Materials</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" value="create_withdrawal">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Slip Number</label>
                            <input type="text" class="form-control text-danger fw-bold" name="withdrawal_no" value="WD-<?= date('ymd') ?>-<?= rand(100,999) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Target Project <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="project_name" required placeholder="e.g. City Hall Phase 1">
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white fw-bold text-dark d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-box-seam me-2"></i>Items to Release</span>
                            <div>
                                <!-- CAMERA TRIGGER BUTTON -->
                                <button type="button" class="btn btn-sm btn-outline-dark me-1" id="startScannerBtn">
                                    <i class="bi bi-upc-scan"></i> Scan Item
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addWdItemBtn">
                                    <i class="bi bi-plus-circle"></i> Add Row
                                </button>
                            </div>
                        </div>
                        
                        <!-- CAMERA PREVIEW CONTAINER (Hidden by default) -->
                        <div id="qr-reader" class="bg-dark mx-auto" style="width: 100%; max-width: 400px; display: none;"></div>

                        <div class="card-body p-2" id="wdItemsContainer">
                            <div class="row g-2 wd-material-row mb-2 align-items-center">
                                <div class="col-md-7">
                                    <select class="form-select wd-item-select" name="items[]" required>
                                        <option value="">Select Material...</option>
                                        <?php foreach ($inventoryItems as $item): ?>
                                            <option value="<?= $item['item_code'] ?>">
                                                <?= htmlspecialchars($item['item_name']) ?> (Stock: <?= $item['quantity'] ?> <?= $item['unit'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" class="form-control wd-qty-input" name="quantities[]" placeholder="Qty" required min="1">
                                </div>
                                <div class="col-md-2 text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-wd-row" disabled><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Remarks / Worker Name</label>
                        <textarea class="form-control" name="remarks" rows="2" placeholder="Who picked this up?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// --- QR SCANNER LOGIC ---
let html5QrcodeScanner;

document.getElementById('startScannerBtn').addEventListener('click', function() {
    const readerDiv = document.getElementById('qr-reader');
    
    // Toggle camera visibility
    if (readerDiv.style.display === 'block') {
        readerDiv.style.display = 'none';
        if (html5QrcodeScanner) html5QrcodeScanner.clear();
        this.innerHTML = '<i class="bi bi-upc-scan"></i> Scan Item';
    } else {
        readerDiv.style.display = 'block';
        this.innerHTML = '<i class="bi bi-x-circle"></i> Close Camera';
        
        // Initialize Scanner
        html5QrcodeScanner = new Html5QrcodeScanner("qr-reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        html5QrcodeScanner.render(onScanSuccess);
    }
});

function onScanSuccess(decodedText, decodedResult) {
    // 1. Play Beep Sound (UX)
    const beep = new Audio('https://www.soundjay.com/buttons/sounds/button-09a.mp3');
    beep.play();

    // 2. Check if the scanned Item Code exists in our options
    const selectOptions = document.querySelector('.wd-item-select').options;
    let itemExists = false;
    for(let i = 0; i < selectOptions.length; i++) {
        if(selectOptions[i].value === decodedText) { itemExists = true; break; }
    }

    if(itemExists) {
        // Find the last row. If it's empty, use it. If not, click "Add Row" to make a new one!
        let rows = document.querySelectorAll('.wd-material-row');
        let lastRow = rows[rows.length - 1];
        let lastSelect = lastRow.querySelector('.wd-item-select');

        if (lastSelect.value === '') {
            lastSelect.value = decodedText;
            lastRow.querySelector('.wd-qty-input').focus();
        } else {
            document.getElementById('addWdItemBtn').click();
            let newRows = document.querySelectorAll('.wd-material-row');
            let newLastRow = newRows[newRows.length - 1];
            newLastRow.querySelector('.wd-item-select').value = decodedText;
            newLastRow.querySelector('.wd-qty-input').focus();
        }
        
        // Close scanner automatically after successful scan
        document.getElementById('qr-reader').style.display = 'none';
        document.getElementById('startScannerBtn').innerHTML = '<i class="bi bi-upc-scan"></i> Scan Item';
        html5QrcodeScanner.clear();
        
    } else {
        alert("Scanned item (" + decodedText + ") not found or out of stock!");
    }
}

// --- DYNAMIC ROWS LOGIC ---
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('wdItemsContainer');
    const addBtn = document.getElementById('addWdItemBtn');
    
    if(addBtn && container) {
        addBtn.addEventListener('click', function() {
            const firstRow = container.querySelector('.wd-material-row');
            const newRow = firstRow.cloneNode(true);
            newRow.querySelector('select').value = '';
            newRow.querySelector('input[type="number"]').value = '';
            newRow.querySelector('.remove-wd-row').disabled = false;
            container.appendChild(newRow);
            updateBtns();
        });
        
        container.addEventListener('click', function(e) {
            if (e.target.closest('.remove-wd-row')) {
                if (container.querySelectorAll('.wd-material-row').length > 1) {
                    e.target.closest('.wd-material-row').remove();
                    updateBtns();
                }
            }
        });
        
        function updateBtns() {
            const rows = container.querySelectorAll('.wd-material-row');
            if (rows.length === 1) rows[0].querySelector('.remove-wd-row').disabled = true;
            else rows.forEach(r => r.querySelector('.remove-wd-row').disabled = false);
        }
    }
});
</script>
<?php endif; ?>