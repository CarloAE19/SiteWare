

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
                    <div class="position-relative d-inline-block mx-auto">
                        <img id="qrItemImg" src="" alt="Item QR" style="width: 150px; height: 150px;">
                        <img id="qrLogoImg" src="assets/clearLogo.png" alt="Logo" class="position-absolute top-50 start-50 translate-middle d-none" style="width: 40px; height: 40px; background-color: white; padding: 4px; border-radius: 6px;">
                    </div>
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
    <div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                    <h5 class="modal-title" id="modalTitle"><span style="color: var(--gb-yellow);">Add Item</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="process/process.php" id="itemModalForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-body bg-light p-3 p-md-4">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="id" id="itemId" value="">

                        <div class="row mb-3">
                            <div class="col-md-5 mb-3 mb-md-0">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemCode">Item Code</label>
                                <input type="text" class="form-control fw-bold bg-white" name="item_code" id="itemCode" readonly required>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemName">Item Name / Desc.</label>
                                <input type="text" class="form-control fw-bold" name="item_name" id="itemName" placeholder="e.g. Concrete Nails 2in" required autocomplete="off">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemCategory">Category Classification</label>
                                <select class="form-select fw-bold" name="category" id="itemCategory" required>
                                    <!-- Dynamic Categories Loop -->
                                    <?php if (!empty($dynamicCategories)): ?>
                                        <?php foreach ($dynamicCategories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Materials">Materials</option>
                                        <option value="Tools">Tools</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="d-none"><input type="hidden" name="status" id="itemStatus" value="In Stock"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemQuantity">Initial Qty</label>
                                <input type="number" class="form-control fw-bold text-center text-primary" name="quantity" id="itemQuantity" required min="0" value="0">
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemUnit">Unit</label>
                                <select class="form-select fw-bold text-center" name="unit" id="itemUnit" required>
                                    <!-- Dynamic Units Loop -->
                                    <?php if (!empty($dynamicUnits)): ?>
                                        <?php foreach ($dynamicUnits as $u): ?>
                                            <option value="<?= htmlspecialchars($u['unit_name']) ?>"><?= htmlspecialchars($u['unit_name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Pieces">Pieces</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase" for="itemPrice">Price (₱)</label>
                                <input type="number" step="0.01" min="0" class="form-control fw-bold text-end" name="unit_price" id="itemPrice" required placeholder="0.00" value="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white">
                        <button type="button" class="btn btn-light text-muted fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand px-4 fw-bold shadow-sm" id="submitBtn"><i class="bi bi-save me-1.5"></i>Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>