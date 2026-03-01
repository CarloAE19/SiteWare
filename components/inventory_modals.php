<!-- MODAL: SCAN TO RECEIVE INVENTORY (STOCK IN) -->
<div class="modal fade" id="receiveScanModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2 text-success"></i>Scan to Stock In</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopReceiveScanner()"></button>
            </div>
            <div class="modal-body bg-light">
                
                <!-- CAMERA CONTAINER -->
                <div id="receive-qr-reader" class="mx-auto border border-2 border-primary rounded" style="width: 100%; max-width: 400px; overflow: hidden;"></div>
                
                <!-- FORM CONTAINER (Hidden until successfully scanned) -->
                <div id="receiveFormContainer" style="display: none;" class="mt-3 text-center">
                    <div class="alert alert-primary d-flex align-items-center mb-3 text-start shadow-sm">
                        <i class="bi bi-upc-scan fs-3 me-3"></i>
                        <div>
                            <small class="d-block text-muted fw-bold text-uppercase">Scanned Item</small>
                            <span id="scannedItemDisplayName" class="fw-bold fs-5 text-dark"></span>
                        </div>
                    </div>
                    
                    <!-- FIXED: AJAX FORM SUBMISSION -->
                    <form id="receiveAjaxForm" onsubmit="submitReceiveAjax(event)">
                        <input type="hidden" name="action" value="stock_in_scanned">
                        <input type="hidden" name="ajax" value="1"> <!-- Tells PHP to use AJAX -->
                        <input type="hidden" name="item_code" id="scannedInputCode">
                        
                        <div class="form-group text-start mb-3">
                            <label class="fw-bold mb-1">Enter Quantity Arrived:</label>
                            <input type="number" name="added_qty" class="form-control form-control-lg text-center fw-bold text-success shadow-sm border-success" required min="1" placeholder="0">
                        </div>
                        
                        <button type="submit" id="receiveSubmitBtn" class="btn btn-success w-100 fw-bold py-3 fs-5 shadow-sm">
                            <i class="bi bi-plus-circle me-2"></i>Add to Inventory
                        </button>
                        
                        <!-- AJAX SUCCESS MESSAGE BOX (Hidden by default) -->
                        <div id="ajaxSuccessMsg" class="alert alert-success d-none mt-3 mb-0 fw-bold border-2 border-success"></div>
                    </form>

                    <button type="button" class="btn btn-link text-muted mt-3" onclick="resetReceiveScanner()">Cancel & Scan different item</button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL: PRINT ITEM QR CODE -->
<div class="modal fade" id="itemQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2" style="color: var(--gb-yellow);"></i>Item QR Label</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center bg-light" id="qrPrintArea">
                <div class="bg-white p-3 border rounded shadow-sm d-inline-block">
                    <h6 class="fw-bold text-dark mb-2" id="qrItemName">Item Name</h6>
                    <img id="qrItemImg" src="" alt="Item QR" style="width: 150px; height: 150px;">
                    <div class="mt-2 text-muted fw-bold font-monospace" id="qrItemCode">ITM-0000</div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-brand" onclick="printItemLabel()"><i class="bi bi-printer me-1"></i> Print Label</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ADD/EDIT INVENTORY ITEM -->
<?php if (in_array($role, ['admin', 'warehouse'])): ?>
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title" id="modalTitle"><span style="color: var(--gb-yellow);">Add Item</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body bg-light">
                    <input type="hidden" name="action" id="formAction" value="add"><input type="hidden" name="id" id="itemId" value="">
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Item Code</label><input type="text" class="form-control" name="item_code" id="itemCode" required></div>
                        <div class="col-md-8 mb-3"><label class="form-label fw-bold">Item Name</label><input type="text" class="form-control" name="item_name" id="itemName" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label fw-bold">Category</label><select class="form-select" name="category" id="itemCategory" required><option>Materials</option><option>Tools</option><option>Equipment</option><option>Safety Gear</option></select></div>
                        <div class="col-md-6 mb-3 d-none"><input type="hidden" name="status" id="itemStatus" value="In Stock"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Qty</label><input type="number" class="form-control" name="quantity" id="itemQuantity" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Unit</label><select class="form-select" name="unit" id="itemUnit" required>
                <?php foreach ($dynamicUnits as $u): ?>
                <option value="<?= htmlspecialchars($u['unit_name']) ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
                                <?php endforeach; ?>
                            </select></div>
                        <div class="col-md-4 mb-3"><label class="form-label fw-bold">Price ($)</label><input type="number" step="0.01" class="form-control" name="unit_price" id="itemPrice" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-brand" id="submitBtn">Save Item</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>