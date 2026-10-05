<?php
// Fetch Data needed for Create PO Modal
// Include performance score for each supplier so the Purchasing Officer can make informed decisions
$suppliers = $pdo->query("
    SELECT
        s.id,
        s.company_name,
        s.contact_number,
        COUNT(p.id)                                                               AS total_po,
        SUM(CASE WHEN p.status LIKE '%Delayed%' THEN 1 ELSE 0 END)               AS delayed_count,
        SUM(CASE WHEN p.status LIKE '%Discrepancy%' THEN 1 ELSE 0 END)           AS discrepancy_count
    FROM suppliers s
    LEFT JOIN purchase_orders p ON p.supplier_id = s.id
    WHERE s.status = 'Active'
    GROUP BY s.id
    ORDER BY s.company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
// Fetch Approved AND Partially Approved Requisitions for PO creation
// Supports both Warehouse Restock and Project Requisitions (Direct-to-Jobsite or Central Warehouse Delivery)
$approvedRS = $pdo->query("
    SELECT r.id, r.rs_no, r.project_name, r.status, r.type, p.address AS project_address 
    FROM requisitions r 
    LEFT JOIN projects p ON r.project_name = p.project_name 
    WHERE r.status IN ('Approved', 'Partially Approved', 'Partially Ordered') 
    ORDER BY 
        FIELD(r.status, 'Partially Ordered', 'Approved', 'Partially Approved'),
        r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ==========================================
  1. MODAL: CREATE NEW PURCHASE ORDER
=========================================== -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"
                        style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php" id="createPoForm" enctype="multipart/form-data">
                <!-- Added p-4 for premium spacing -->
                <div class="modal-body bg-light p-4">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" value="create_po">
                    <input type="hidden" name="has_item_selection" value="1">

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Auto-Generated PO
                            Number</label>
                        <input type="text" class="form-control fw-bold text-primary bg-white shadow-sm" name="po_no"
                            value="PO-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Approved Requisition
                            (RS) <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="rs_id" id="poRsSelect" required onchange="if(typeof window.updatePoDestinationOnRsChange === 'function') window.updatePoDestinationOnRsChange();">
                            <option value="" disabled selected>-- Select an Approved RS --</option>
                            <?php foreach ($approvedRS as $rs):
                                $isPartialApp = $rs['status'] === 'Partially Approved';
                                $isPartiallyOrdered = $rs['status'] === 'Partially Ordered';
                                $statusLabel = $isPartiallyOrdered ? ' ⏳ [Partially Ordered - Split PO]' : ($isPartialApp ? ' ⚠️ [Partially Approved]' : ' ✅ [Approved]');
                                $isRestock = ($rs['type'] === 'restock' || $rs['project_name'] === 'Warehouse Restock');
                                $typePrefix = $isRestock ? '📦 [Restock]' : '🏗️ [Project: ' . htmlspecialchars($rs['project_name']) . ']';
                                ?>
                                <option value="<?= $rs['id'] ?>"
                                    data-type="<?= htmlspecialchars($rs['type'] ?? 'project') ?>"
                                    data-project="<?= htmlspecialchars($rs['project_name']) ?>"
                                    data-address="<?= htmlspecialchars($rs['project_address'] ?? '') ?>"
                                    data-status="<?= htmlspecialchars($rs['status']) ?>">
                                    <?= $typePrefix ?> <?= $rs['rs_no'] ?>     <?= $statusLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle me-1"></i>Approved and Partially Ordered RSes (Warehouse Restock &amp; Project requests) appear here.</small>
                    </div>

                    <!-- Interactive Split-PO Item Selection & Allocation -->
                    <div class="mb-4 d-none" id="rsItemsPreviewContainer">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <label class="form-label fw-bold small text-muted text-uppercase mb-0">
                                    <i class="bi bi-check2-square text-primary me-1"></i>Select Items For This Supplier <span class="text-danger">*</span>
                                </label>
                                <small class="text-muted d-block" style="font-size: 0.73rem;">
                                    Check items this vendor fulfills. Unselected items stay on the RS for another PO.
                                </small>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2" onclick="toggleAllPoItems(true)" style="font-size: 0.72rem;">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0.5 px-2" onclick="toggleAllPoItems(false)" style="font-size: 0.72rem;">Deselect All</button>
                            </div>
                        </div>

                        <div class="table-responsive border rounded shadow-sm bg-white" style="max-height: 280px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted sticky-top">
                                    <tr>
                                        <th style="width: 36px;" class="text-center ps-2">
                                            <input type="checkbox" class="form-check-input" id="checkAllPoItems" onchange="toggleAllPoItems(this.checked)" title="Select/Deselect All">
                                        </th>
                                        <th>Item Description</th>
                                        <th class="text-center" style="width: 120px;">Order Qty</th>
                                        <th class="text-end pe-3" style="width: 110px;">Est. Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="rsItemsPreviewBody">
                                    <!-- Populated via AJAX -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Allocation Summary Footer -->
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 p-2 mt-1 bg-white border rounded small">
                            <span class="text-muted fw-semibold" id="poSelectedCountBadge">
                                <i class="bi bi-box-seam me-1 text-primary"></i>0 items selected
                            </span>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-xs fw-bold px-2 py-1 text-white shadow-sm" onclick="openPrePoInquiryModal()" style="background-color: #7360f2; font-size: 0.73rem; border-radius: 6px;" title="Send quick stock & price inquiry to supplier via Viber">
                                    <i class="fa-brands fa-viber me-1"></i> Inquire Checked Items
                                </button>
                                <span class="fw-bold text-dark">
                                    Est. Total: <span class="text-success font-monospace fs-6" id="poSelectedTotalDisplay">₱0.00</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Destination & Routing Card (ISO 9001 / Direct-to-Jobsite Architecture) -->
                    <div class="mb-4 p-3 bg-white border rounded shadow-sm" id="poDeliveryRoutingCard">
                        <label class="form-label fw-bold small text-muted text-uppercase d-flex align-items-center justify-content-between mb-2">
                            <span><i class="bi bi-geo-alt-fill text-danger me-1"></i> Delivery Destination & Routing</span>
                            <span class="badge bg-light text-secondary border font-monospace" id="rsTypeBadge" style="font-size: 0.70rem;">Central Storage</span>
                        </label>

                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <div class="form-check p-2.5 border rounded-3 bg-light h-100 destination-radio-wrap" id="destRadioWarehouseWrap">
                                    <input class="form-check-input ms-1" type="radio" name="delivery_destination_type" id="destTypeWarehouse" value="warehouse" checked onchange="togglePoDestinationFields()">
                                    <label class="form-check-label fw-bold small text-dark ms-2" for="destTypeWarehouse">
                                        <i class="bi bi-building-down text-primary me-1"></i> Central Warehouse
                                        <small class="d-block text-muted fw-normal" style="font-size: 0.72rem;">Stocked into warehouse inventory</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="form-check p-2.5 border rounded-3 bg-light h-100 destination-radio-wrap" id="destRadioJobsiteWrap">
                                    <input class="form-check-input ms-1" type="radio" name="delivery_destination_type" id="destTypeJobsite" value="jobsite" onchange="togglePoDestinationFields()">
                                    <label class="form-check-label fw-bold small text-dark ms-2" for="destTypeJobsite">
                                        <i class="bi bi-truck text-success me-1"></i> Direct to Jobsite
                                        <small class="d-block text-muted fw-normal" style="font-size: 0.72rem;">Delivered directly to project location</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden input holding actual destination string sent to backend -->
                        <input type="hidden" name="delivery_destination" id="poDeliveryDestination" value="Warehouse (Central Storage)">

                        <!-- Address / Drop Location (Shown when Direct to Jobsite is selected) -->
                        <div id="jobsiteAddressGroup" class="d-none mt-2">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                                Jobsite Address / Drop Instructions
                            </label>
                            <textarea class="form-control form-control-sm bg-light shadow-sm" name="delivery_address" id="poDeliveryAddress" rows="2" placeholder="e.g. Lot 4 Block 2, MacArthur Highway Site Gate 1 (Contact: Engr. Santos)"></textarea>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                <i class="bi bi-info-circle me-1"></i>Printed on the Purchase Order for supplier trucking and site receiving.
                            </small>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-0">Select Supplier <span
                                    class="text-danger">*</span></label>
                            <button type="button" class="btn btn-sm fw-bold px-2.5 py-0.5 shadow-sm text-white"
                                id="btnQuickViberInquiry" onclick="openPrePoInquiryModal()"
                                title="Send quick stock availability & price inquiry to this supplier via Viber or Copy message"
                                style="background-color: #7360f2; border-color: #7360f2; font-size: 0.75rem; border-radius: 6px;">
                                <i class="fa-brands fa-viber me-1"></i> Quick Viber Inquiry
                            </button>
                        </div>
                        <select class="form-select fw-bold shadow-sm" name="supplier_id" id="poSupplierSelect" required onchange="if(typeof window.onPoSupplierChange === 'function') window.onPoSupplierChange();">
                            <option value="" disabled selected>-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup):
                                $total = (int) $sup['total_po'];
                                if ($total === 0) {
                                    $tier = '🔘 New';
                                    $score = '';
                                } else {
                                    $onTime = ($total - (int) $sup['delayed_count']) / $total * 100;
                                    $accuracy = ($total - (int) $sup['discrepancy_count']) / $total * 100;
                                    $sc = round(($onTime + $accuracy) / 2, 1);
                                    if ($sc >= 90) {
                                        $tier = '🟢 Excellent';
                                    } elseif ($sc >= 70) {
                                        $tier = '🟡 Average';
                                    } else {
                                        $tier = '🔴 Poor';
                                    }
                                    $score = ' — ' . $sc . '%';
                                }
                                ?>
                                <option value="<?= $sup['id'] ?>"
                                    data-phone="<?= htmlspecialchars($sup['contact_number'] ?? '') ?>"
                                    data-company="<?= htmlspecialchars($sup['company_name']) ?>"
                                    data-contact="<?= htmlspecialchars($sup['contact_person'] ?? '') ?>">
                                    <?= htmlspecialchars($sup['company_name']) ?> [<?= $tier ?><?= $score ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i
                                class="bi bi-bar-chart-line me-1"></i>Performance score based on delivery history. 🟢
                            Excellent ≥90% &nbsp; 🟡 Average ≥70% &nbsp; 🔴 Poor &lt;70%</small>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Payment Terms <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="payment_terms" id="poPaymentTerms" required>
                            <option value="Credit (30 Days Net)" selected>💳 Credit (30 Days Net Terms)</option>
                            <option value="Credit (15 Days Net)">💳 Credit (15 Days Net Terms)</option>
                            <option value="Credit (60 Days Net)">💳 Credit (60 Days Net Terms)</option>
                            <option value="Charge / On Account">💳 Charge / On Account</option>
                            <option value="Cash on Delivery (COD)">💵 Cash on Delivery (COD)</option>
                            <option value="Cash in Advance / Prepaid">💵 Cash in Advance / Prepaid</option>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle me-1"></i>Official payment terms printed on the PO delivered to the supplier.</small>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Expected Time of Arrival
                            (Warehouse ETA)</label>
                        <input type="date" class="form-control fw-bold shadow-sm" name="expected_delivery_date"
                            min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i
                                class="bi bi-calendar-event me-1"></i>Target date supplies are expected to arrive at the
                            warehouse.</small>
                    </div>
                </div>
                <!-- Clean white footer -->
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm"><i
                            class="bi bi-check-circle me-1"></i> Generate & Save PO</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ==========================================
  2. MODAL: LOG WEATHER/LOGISTICS DELAY
=========================================== -->
<div class="modal fade" id="delayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 border-danger border-top border-4 shadow-lg">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-cloud-lightning-rain-fill me-2"></i>Log
                    Supply Delay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php" id="delayForm">
                <div class="modal-body p-4 bg-light">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" value="log_po_delay">
                    <input type="hidden" name="po_id" id="delayPoId">
                    <input type="hidden" name="po_no" id="delayPoNo">

                    <div class="alert alert-danger px-3 py-2 mb-4 shadow-sm"
                        style="font-size: 0.8rem; border-left: 3px solid #dc3545;">
                        <i class="bi bi-info-circle-fill me-1"></i> Flagging this PO will instantly update the status
                        and notify Management & Warehouse.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Delay <span
                                class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm mb-2 text-danger" name="delay_type" required>
                            <option value="Weather / Typhoon">Weather / Typhoon</option>
                            <option value="Road / Traffic Conditions">Road / Traffic Conditions</option>
                            <option value="Supplier Out of Stock">Supplier Out of Stock</option>
                            <option value="Port / Customs Hold">Port / Customs Hold</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase"><i
                                class="bi bi-calendar2-week text-primary me-1"></i> Revised Warehouse ETA <span
                                class="text-muted">(New Arrival Date)</span></label>
                        <input type="date" class="form-control fw-bold shadow-sm" name="new_eta" id="delayNewEta">
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle me-1"></i>Updating the ETA reschedules the warehouse delivery
                            tracking.</small>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-bold small text-muted text-uppercase">Additional Remarks</label>
                        <textarea class="form-control fw-bold shadow-sm" name="remarks" rows="2"
                            placeholder="e.g. Typhoon Basyang blocking port, rescheduled arrival..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white p-3 border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold btn-sm px-3"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm px-3"><i
                            class="bi bi-exclamation-triangle-fill me-1"></i> Submit Delay Alert</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
  3. MODAL: RECEIVE PO / VERIFY DISCREPANCY
=========================================== -->
<div class="modal fade" id="receiveModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg border-top border-success border-4">
            <div class="modal-header bg-white">
                <div>
                    <h5 class="modal-title text-success fw-bold mb-0">
                        <i class="bi bi-box-seam me-2"></i>Verify Stock In & Delivery Manifest
                    </h5>
                    <small class="text-muted">Multi-Stage & Partial Delivery Supported</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    onclick="if(typeof stopReceiptCamera==='function') stopReceiptCamera();"></button>
            </div>
            <form method="POST" action="process/process.php" id="receiveForm" enctype="multipart/form-data">
                <div class="modal-body p-3 p-md-4 bg-light">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" value="mark_po_delivered">
                    <input type="hidden" name="po_id" id="receivePoId">
                    <input type="hidden" name="po_no" id="receivePoNo">
                                    <!-- 3-Way Match Reference: Supplier Delivery Receipt & Quality Inspection -->
                    <div class="card border border-success-subtle shadow-xs mb-3 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                <span class="text-uppercase fw-bold text-success small" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                    <i class="bi bi-shield-check me-1"></i> 3-Way Match & Quality Inspection
                                </span>
                                <span class="badge bg-light text-dark border font-monospace" id="receivePoNoBadge" style="font-size: 0.75rem;">PO-0000</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                                        Supplier DR / Sales Invoice No. <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white text-muted"><i class="bi bi-receipt"></i></span>
                                        <input type="text" class="form-control fw-bold text-dark" name="supplier_dr_no" id="receiveSupplierDrNo"
                                            placeholder="e.g. DR-2026-9041 or SI-88219" required>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-info-circle me-1"></i>Official vendor Delivery Receipt or Invoice reference for 3-way matching.
                                    </small>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold text-dark small text-uppercase mb-1">
                                        Quality Inspection Remarks <span class="text-muted fw-normal">(Optional)</span>
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white text-muted"><i class="bi bi-chat-left-text"></i></span>
                                        <input type="text" class="form-control" name="inspection_notes" id="receiveInspectionNotes"
                                            placeholder="e.g. Vehicle plate ABC-1234, packaging intact, moisture-free">
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-card-checklist me-1"></i>Physical condition, batch LOT numbers, seals, or delivery driver notes.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Alert Explaining Partial Deliveries -->
                    <div class="alert alert-info border-0 shadow-sm mb-3 d-flex align-items-center py-2 px-3 rounded-3"
                        style="font-size: 0.85rem; background-color: #e8f4fd; color: #0d47a1;">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0 text-primary"></i>
                        <div>
                            <strong>Multi-Stage Delivery & Quality Control:</strong> Enter accepted good units (stocked into Master Inventory) and damaged/rejected units (logged for debit memo/replacement). If remaining units are unsupplied, designate them as <strong>To Follow</strong> or <strong>Sold Out</strong>.
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm bg-white mb-3 p-3">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-2 text-center d-block">
                            <i class="bi bi-paperclip me-1 text-primary"></i> Proof of Receipt (Upload Image or Take
                            Live Photo)
                        </label>

                        <!-- Nav Pills for Dual Options: Upload Image vs Live Camera -->
                        <ul class="nav nav-pills nav-justified mb-3 shadow-sm bg-light p-1 rounded-3"
                            id="receiptProofTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold small py-2" id="upload-receipt-tab"
                                    data-bs-toggle="pill" data-bs-target="#uploadReceiptPane" type="button" role="tab"
                                    onclick="if(typeof stopReceiptCamera==='function') stopReceiptCamera();">
                                    <i class="bi bi-upload me-1"></i> Upload Image / File
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold small py-2" id="camera-receipt-tab"
                                    data-bs-toggle="pill" data-bs-target="#cameraReceiptPane" type="button" role="tab">
                                    <i class="bi bi-camera-fill me-1"></i> Live Camera Capture
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="receiptProofTabContent">
                            <!-- Option 1: File Upload -->
                            <div class="tab-pane fade show active p-2" id="uploadReceiptPane" role="tabpanel">
                                <div class="mb-2">
                                    <input type="file" name="proof_of_receipt" id="proofOfReceiptFileInput"
                                        class="form-control fw-bold shadow-sm" accept="image/*,.pdf">
                                </div>
                                <small class="text-muted d-block text-center" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle me-1"></i>Upload a photo or scanned file of the delivery
                                    receipt / invoice (JPG, PNG, WEBP, PDF).
                                </small>
                            </div>

                            <!-- Option 2: Live Camera Capture -->
                            <div class="tab-pane fade p-2 text-center" id="cameraReceiptPane" role="tabpanel">
                                <div id="receiptCameraContainer" class="d-flex flex-column align-items-center">
                                    <!-- Start Camera Trigger Button -->
                                    <button type="button" id="openCameraBtn"
                                        class="btn btn-outline-primary fw-bold shadow-sm px-4 py-2"
                                        onclick="startReceiptCamera()">
                                        <i class="bi bi-camera-fill me-1"></i> Open Camera
                                    </button>

                                    <!-- Compact Video Stream Viewport -->
                                    <video id="receiptCameraVideo" autoplay playsinline
                                        class="rounded-3 border border-2 border-primary shadow-sm d-none mt-2"
                                        style="width: 100%; max-width: 360px; height: 180px; object-fit: cover; background: #1a1a1a;"></video>
                                    <canvas id="receiptCameraCanvas" class="d-none"></canvas>

                                    <!-- Captured Image Preview -->
                                    <img id="receiptCapturedImage"
                                        class="rounded-3 border border-2 border-success shadow-sm d-none mt-2"
                                        style="width: 100%; max-width: 360px; height: 180px; object-fit: contain; background: #f8f9fa;">

                                    <input type="hidden" name="captured_proof_base64" id="capturedProofBase64">

                                    <!-- Action Buttons -->
                                    <div class="d-flex justify-content-center gap-2 mt-2">
                                        <button type="button" id="captureReceiptBtn"
                                            class="btn btn-primary btn-sm fw-bold shadow-sm px-4 d-none"
                                            onclick="takeReceiptPhoto()">
                                            <i class="bi bi-camera me-1"></i> Snap Photo
                                        </button>
                                        <button type="button" id="retakeReceiptBtn"
                                            class="btn btn-outline-secondary btn-sm fw-bold shadow-sm px-3 d-none"
                                            onclick="startReceiptCamera()">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Retake Photo
                                        </button>
                                    </div>
                                </div>

                                <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle me-1"></i>Click "Open Camera" to capture live photo
                                    proof of delivery.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Manifest Table -->
                    <div class="table-responsive border rounded shadow-sm bg-white mb-2">
                        <table class="table table-hover align-middle mb-0 text-nowrap" id="receiveItemsTable">
                            <thead class="table-light text-muted" style="font-size: 0.8rem;">
                                <tr>
                                    <th style="min-width: 170px;">Item Description</th>
                                    <th class="text-center" style="width: 70px;">Ordered</th>
                                    <th class="text-center" style="width: 70px;">Prior</th>
                                    <th class="text-center" style="width: 75px;">Remaining</th>
                                    <th class="text-center" style="min-width: 145px; width: 150px;">Accepted Today</th>
                                    <th class="text-center" style="min-width: 125px; width: 130px;">Damaged / Defect</th>
                                    <th class="text-center" style="min-width: 180px;">Defect Reason (If Any)</th>
                                    <th class="text-center" style="min-width: 120px; width: 125px;">Unit Price (₱)</th>
                                    <th class="text-center" style="min-width: 185px;">Supplier Status / Remainder</th>
                                    <th class="text-end" style="width: 110px;">Batch Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="receiveItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr>
                                    <td colspan="9" class="text-end fw-bold text-muted text-uppercase small py-2">Batch Accepted Total:</td>
                                    <td class="text-end fw-bold text-success fs-6 py-2" id="receiveBatchTotalVal">₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white p-3 border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal"
                        onclick="if(typeof stopReceiptCamera==='function') stopReceiptCamera();">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" id="confirmReceiveBtn"><i
                            class="bi bi-check2-all me-1"></i>Confirm & Stock In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
  4. MODAL: VIEW DELIVERY INTAKE & DISCREPANCY LOG
=========================================== -->
<div class="modal fade" id="discrepancyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" id="discModalCard"
            style="border-top: 4px solid #ffc107 !important;">
            <div class="modal-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <div id="discModalIconWrap"
                        class="rounded-circle p-2 bg-warning-subtle text-warning d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="bi bi-clock-history fs-5" id="discModalIcon"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="discModalTitle">Delivery Intake & Audit
                            History</h5>
                        <small class="text-muted" id="discModalSubtitle">Multi-Stage Fulfillment & Discrepancy
                            Timeline</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <!-- PO Header Summary Card -->
                <div class="card border-0 shadow-sm bg-white mb-3 p-3 rounded-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">
                                <i class="bi bi-file-earmark-text me-1 text-primary"></i>Purchase Order
                            </span>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span id="discPoNo"
                                    class="fw-bold font-monospace text-primary fs-6 bg-light px-3 py-1 rounded border"></span>
                                <span id="discSupplierDrBadge"
                                    class="badge bg-secondary-subtle text-dark border border-secondary-subtle px-2.5 py-1.5 font-monospace d-none"></span>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1">Order Status</span>
                            <span id="discPoStatusBadge" class="badge px-3 py-1.5 shadow-sm text-uppercase"></span>
                        </div>
                    </div>
                </div>

                <!-- Timeline Header & Batch Count -->
                <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                    <h6 class="fw-bold text-secondary text-uppercase small mb-0">
                        <i class="bi bi-activity me-1 text-primary"></i> Intake Batches & Discrepancy Records
                    </h6>
                    <span id="discBatchCountBadge" class="badge bg-secondary-subtle text-secondary px-2 py-1"></span>
                </div>

                <!-- Structured Timeline Cards Container -->
                <div id="discTimelineContainer" class="d-flex flex-column gap-3 mb-3">
                    <!-- Populated dynamically via JS -->
                </div>

                <!-- Attached Proof of Receipt -->
                <div id="discProofContainer" class="mt-3 d-none">
                    <!-- Proof link injected here -->
                </div>

                <!-- Collapsible Raw Audit Log -->
                <div class="mt-3 pt-2 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none small" type="button"
                            data-bs-toggle="collapse" data-bs-target="#discRawLogCollapse" aria-expanded="false">
                            <i class="bi bi-code-square me-1"></i> <span id="discRawToggleText">View Raw System
                                Log</span>
                        </button>
                    </div>
                    <div class="collapse mt-2" id="discRawLogCollapse">
                        <div class="p-3 bg-white border rounded shadow-sm font-monospace text-muted small"
                            style="max-height: 180px; overflow-y: auto; white-space: pre-wrap; font-size: 0.78rem;"
                            id="discRawLog"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between bg-white border-top py-2 px-3">
                <button type="button" class="btn btn-light text-muted fw-bold px-4"
                    data-bs-dismiss="modal">Close</button>
                <div id="discActionButtons"></div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
  5. MODAL: REVIEW AND SEND VIBER ORDER
=========================================== -->
<div class="modal fade" id="viberPreviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" style="border-top: 4px solid #7360f2 !important;">
            <div class="modal-header bg-white">
                <h5 class="modal-title fw-bold" style="color: #7360f2;"><i class="fa-brands fa-viber me-2"></i>Review &
                    Send Viber Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="viberPreviewForm">
                <div class="modal-body p-4 bg-light">
                    <!-- PO Details Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Purchase Order
                                Number</label>
                            <input type="text" id="viberPoNo"
                                class="form-control fw-bold bg-white text-primary shadow-sm" readonly>
                            <input type="hidden" id="viberPoId">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase d-flex justify-content-between align-items-center mb-1">
                                <span>Recipient Phone Number <span class="text-danger">*</span></span>
                                <span id="poViberBadge" class="badge bg-purple d-none shadow-sm" style="font-size: 0.68rem;">
                                    <i class="fa-brands fa-viber me-1"></i>Viber Ready
                                </span>
                            </label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i
                                        class="bi bi-telephone-fill"></i></span>
                                <input type="text" id="viberPhone" class="form-control fw-bold bg-white text-dark"
                                    placeholder="e.g. 0917-123-4567 or +63 917 123 4567" required>
                            </div>
                            <div id="viberPhoneFeedback" class="small mt-1 text-muted" style="font-size: 0.75rem;">
                                <i class="bi bi-info-circle me-1"></i>Accepts 09XX or +639XX format for direct Viber messaging.
                            </div>
                        </div>
                    </div>

                    <!-- Select Supplier Dropdown -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Supplier <span
                                class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" id="viberSupplierSelect" required>
                            <option value="" disabled>-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"
                                    data-phone="<?= htmlspecialchars($sup['contact_number'] ?? '') ?>">
                                    <?= htmlspecialchars($sup['company_name']) ?>
                                    (<?= htmlspecialchars($sup['contact_number'] ?: 'No Phone') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Changing the
                            supplier will send the order to the selected supplier and update this PO's record.</small>
                    </div>

                    <!-- Materials to Buy List Preview -->
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Materials to Buy
                            Preview</label>
                        <div class="table-responsive border rounded shadow-sm bg-white"
                            style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0 text-nowrap"
                                style="font-size: 0.85rem;">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th class="text-center">Quantity & Unit</th>
                                    </tr>
                                </thead>
                                <tbody id="viberItemsBody">
                                    <!-- Populated dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Viber Message Editor -->
                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Edit Viber Message
                            Content</label>
                        <textarea class="form-control fw-bold text-dark shadow-sm" id="viberMessageText" rows="6"
                            style="font-family: monospace; font-size: 0.9rem;" required></textarea>
                        <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>You can review and
                            modify the message above before dispatching via Viber.</small>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0 justify-content-between p-3">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="sendViberSubmitBtn" class="btn btn-viber fw-bold px-4 shadow-sm"
                        onclick="triggerViberPoSend()"><i class="fa-brands fa-viber me-1"></i> Send via Viber</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
  MODAL: PRE-PO VIBER / STOCK INQUIRY (Method A: Direct Vendor Inquiry)
=========================================== -->
<div class="modal fade" id="prePoInquiryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" style="border-top: 4px solid #7360f2 !important;">
            <div class="modal-header bg-white pb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background-color: rgba(115, 96, 242, 0.12); color: #7360f2;">
                        <i class="fa-brands fa-viber fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" style="color: #7360f2;">Quick Vendor Stock &amp; Price Inquiry</h5>
                        <small class="text-muted" style="font-size: 0.75rem;">Inquire availability &amp; pricing with supplier prior to PO finalization</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <!-- Informative Callout -->
                <div class="alert alert-info py-2 px-3 mb-3 border-0 shadow-sm d-flex align-items-start gap-2" style="font-size: 0.8rem; background-color: #eff6ff; color: #1e40af; border-left: 3px solid #3b82f6 !important;">
                    <i class="bi bi-info-circle-fill fs-6 mt-0.5 text-primary flex-shrink-0"></i>
                    <div>
                        <strong>Pre-Order Verification:</strong> Contact the vendor via Viber or chat to confirm that these items are on hand. If they only have partial items, you can keep those selected in the PO and order the remaining items from another supplier!
                    </div>
                </div>

                <!-- Supplier & Contact Card -->
                <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-7">
                                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.70rem;">Target Supplier</div>
                                <div class="fw-bold text-dark fs-6" id="prePoInquirySupplierName">-</div>
                                <div class="text-muted small" id="prePoInquiryContactPerson"><i class="bi bi-person me-1"></i>Contact: <span>-</span></div>
                            </div>
                            <div class="col-12 col-md-5 text-md-end">
                                <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.70rem;">Recipient Contact Number</div>
                                <div class="d-inline-flex align-items-center gap-1.5 mt-0.5">
                                    <span class="font-monospace fw-bold text-dark" id="prePoInquiryPhoneDisplay">-</span>
                                    <span id="prePoInquiryViberBadge" class="badge shadow-sm" style="font-size: 0.68rem; background-color: #7360f2; color: #fff;">
                                        <i class="fa-brands fa-viber me-1"></i>Viber Ready
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selected Items Preview Chips -->
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1.5">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-0" style="font-size: 0.75rem;">
                            <i class="bi bi-boxes me-1 text-primary"></i>Items To Inquire (<span id="prePoInquiryItemCount">0</span> items selected)
                        </label>
                        <span class="badge bg-light text-secondary border font-monospace" id="prePoInquiryRsRef">RS Ref: -</span>
                    </div>
                    <div class="p-2.5 bg-white border rounded-3 shadow-sm" style="max-height: 140px; overflow-y: auto;" id="prePoInquiryItemsChips">
                        <!-- Dynamic items rendered via JS -->
                    </div>
                </div>

                <!-- Generated Inquiry Message Editor -->
                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-0" style="font-size: 0.75rem;">
                            <i class="bi bi-chat-left-dots text-primary me-1"></i>Inquiry Message Text
                        </label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-primary" onclick="resetPrePoInquiryMessage()" style="font-size: 0.72rem;">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Template
                        </button>
                    </div>
                    <textarea class="form-control fw-medium text-dark shadow-sm bg-white" id="prePoInquiryMessage" rows="7"
                        style="font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.85rem; line-height: 1.45;"></textarea>
                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                        <i class="bi bi-pencil me-1"></i>You can review and edit this message before copying or launching Viber.
                    </small>
                </div>
            </div>
            <div class="modal-footer bg-white border-top justify-content-between p-3">
                <button type="button" class="btn btn-light text-muted fw-bold px-3" data-bs-dismiss="modal">
                    <i class="bi bi-arrow-left me-1"></i> Back to PO Form
                </button>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary fw-bold px-3 shadow-sm" id="btnCopyPrePoInquiry" onclick="copyPrePoInquiryText()">
                        <i class="bi bi-clipboard me-1"></i> Copy Message
                    </button>
                    <a id="btnLaunchPrePoViber" href="#" target="_blank" class="btn fw-bold px-4 text-white shadow-sm" onclick="onLaunchPrePoViberClick(event)" style="background-color: #7360f2; border-color: #7360f2;">
                        <i class="fa-brands fa-viber me-1"></i> Open Chat in Viber
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!defined('CIMS_EDIT_ETA_MODAL_LOADED')): define('CIMS_EDIT_ETA_MODAL_LOADED', true); ?>
<!-- ==========================================
  MODAL: UPDATE PO ETA (Warehouse Delivery Target)
=========================================== -->
<div class="modal fade" id="editEtaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar2-week me-2"
                        style="color: var(--gb-yellow);"></i>Update Warehouse Supply ETA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEtaForm" onsubmit="handleUpdatePoEta(event)">
                <div class="modal-body bg-light p-4">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" id="editEtaPoId" name="po_id" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Purchase Order No.</label>
                        <input type="text" class="form-control fw-bold text-primary bg-white shadow-sm" id="editEtaPoNo"
                            readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Expected Time of Arrival
                            (Warehouse ETA) <span class="text-danger">*</span></label>
                        <input type="date" class="form-control fw-bold shadow-sm" id="editEtaInputDate"
                            name="expected_delivery_date" required>
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle me-1"></i>Updating this ETA will alert the Warehouse In-charge
                            & Management in real-time.</small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i
                            class="bi bi-check2-circle me-1"></i> Save Updated ETA</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==========================================
  6. MODAL: VIRTUAL PURCHASE ORDER PAPER & PRINT VIEW
=========================================== -->
<div class="modal fade" id="poPrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white d-print-none">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h5 class="modal-title fw-bold mb-0"><i class="bi bi-file-earmark-text-fill me-2"
                            style="color: var(--gb-yellow);"></i>Purchase Order Details & Fulfillment</h5>
                    <span class="badge bg-primary font-monospace px-2 py-1" id="poModalHeaderPoNo">PO-000000</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-2 p-sm-3 p-md-4 bg-light" id="poPrintDocumentBody">
                <div class="placeholder-wave p-3 p-md-4 bg-white rounded-3 border shadow-sm my-2" id="poPrintLoadingSpinner">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="placeholder cims-shimmer rounded" style="width: 38px; height: 38px; display: inline-block;"></span>
                            <div>
                                <span class="placeholder cims-shimmer col-8 rounded py-2 d-block mb-1" style="width: 140px;"></span>
                                <span class="placeholder cims-shimmer col-5 rounded d-block" style="width: 100px;"></span>
                            </div>
                        </div>
                        <span class="placeholder cims-shimmer rounded-pill py-2" style="width: 70px; display: inline-block;"></span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><div class="p-2 bg-light rounded border"><span class="placeholder cims-shimmer col-10 rounded d-block mb-2"></span><span class="placeholder cims-shimmer col-7 rounded d-block"></span></div></div>
                        <div class="col-6"><div class="p-2 bg-light rounded border"><span class="placeholder cims-shimmer col-10 rounded d-block mb-2"></span><span class="placeholder cims-shimmer col-7 rounded d-block"></span></div></div>
                    </div>
                    <div class="table-responsive border rounded bg-light p-2 mb-2">
                        <div class="d-flex justify-content-between py-2 border-bottom"><span class="placeholder cims-shimmer col-4 rounded"></span><span class="placeholder cims-shimmer col-2 rounded"></span></div>
                        <div class="d-flex justify-content-between py-2 border-bottom"><span class="placeholder cims-shimmer col-5 rounded"></span><span class="placeholder cims-shimmer col-2 rounded"></span></div>
                        <div class="d-flex justify-content-between py-2"><span class="placeholder cims-shimmer col-3 rounded"></span><span class="placeholder cims-shimmer col-2 rounded"></span></div>
                    </div>
                    <div class="text-center text-muted small py-1"><span class="spinner-border spinner-border-sm me-1 text-primary"></span> Retrieving Purchase Order Data...</div>
                </div>

                <div id="poPrintModalContent" class="d-none">
                    <!-- Navigation Tab Bar (Pills: Document, Attached Receipt, Timeline) -->
                    <ul class="nav nav-pills nav-fill bg-white border rounded-3 p-1 mb-3 shadow-xs d-print-none" id="poDetailsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold py-2 d-flex align-items-center justify-content-center gap-1.5" id="poTabDocBtn" data-bs-toggle="tab" data-bs-target="#poTabDocPane" type="button" role="tab" aria-controls="poTabDocPane" aria-selected="true">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>PO Document</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold py-2 d-flex align-items-center justify-content-center gap-1.5 position-relative" id="poTabReceiptBtn" data-bs-toggle="tab" data-bs-target="#poTabReceiptPane" type="button" role="tab" aria-controls="poTabReceiptPane" aria-selected="false">
                                <i class="bi bi-receipt"></i>
                                <span>Attached Receipt</span>
                                <span id="poReceiptTabBadge" class="badge rounded-pill bg-secondary ms-1" style="font-size: 0.65rem;">None</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold py-2 d-flex align-items-center justify-content-center gap-1.5" id="poTabTimelineBtn" data-bs-toggle="tab" data-bs-target="#poTabTimelinePane" type="button" role="tab" aria-controls="poTabTimelinePane" aria-selected="false">
                                <i class="bi bi-clock-history"></i>
                                <span>Fulfillment Timeline</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="poDetailsTabContent">
                        <!-- ==========================================
                             TAB 1: OFFICIAL PO DOCUMENT & MANIFEST
                        =========================================== -->
                        <div class="tab-pane fade show active" id="poTabDocPane" role="tabpanel" aria-labelledby="poTabDocBtn">
                            <!-- Printable Virtual Paper Container (Adaptive Desktop & Half-A4 Mobile Layout) -->
                            <div class="bg-white border p-2.5 p-sm-3 p-md-4 rounded-3 shadow-sm po-half-a4-paper"
                                id="poPrintPaper" style="margin: 0 auto;">
                    <!-- Letterhead Header -->
                    <div class="row align-items-center pb-2 mb-2 border-bottom border-2 border-dark">
                        <div class="col-7 col-sm-8">
                            <div class="d-flex align-items-center">
                                <img src="assets/LogoGB.png" alt="GB Construction Logo" class="me-2 po-doc-logo"
                                    style="height: 38px; width: auto; object-fit: contain;">
                                <div>
                                    <h4 class="fw-bold text-dark mb-0 po-doc-brand"
                                        style="letter-spacing: -0.5px; font-size: 1.05rem; line-height: 1.15;">GENETIAN
                                        BUILDERS</h4>
                                    <div class="text-uppercase fw-bold text-primary po-doc-subbrand"
                                        style="letter-spacing: 0.6px; font-size: 0.68rem; line-height: 1.15;">
                                        CONSTRUCTION & ENTERPRISE INC.</div>
                                    <small class="text-muted d-block po-doc-manifest-lbl"
                                        style="font-size: 0.60rem; line-height: 1.1;">Official Purchase Order & Supplier
                                        Manifest</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-5 col-sm-4 text-end">
                            <span class="badge bg-dark px-2 py-0.5 text-uppercase po-doc-status" id="printPoStatus"
                                style="font-size: 0.68rem;">Pending</span>
                            <div class="fw-bold text-primary mt-1 text-nowrap po-doc-pono" id="printPoNo"
                                style="font-size: 0.98rem; letter-spacing: -0.2px; line-height: 1.1;">PO-000000
                            </div>
                        </div>
                    </div>

                    <!-- Metadata Grid: Supplier & Order Specs (Always 2 Columns Side-by-Side) -->
                    <div class="row g-1 mb-2 p-1.5 bg-light rounded-2 border po-meta-grid" style="font-size: 0.76rem;">
                        <div class="col-6 border-end pe-1 pe-sm-2">
                            <h6 class="fw-bold text-uppercase text-muted mb-0" style="font-size: 0.66rem;"><i
                                    class="bi bi-building me-1"></i> Supplier Information</h6>
                            <div class="fw-bold text-dark text-truncate" id="printSupplierName"
                                style="font-size: 0.78rem; line-height: 1.2;">-</div>
                            <div class="text-secondary text-truncate" id="printSupplierContact"
                                style="font-size: 0.72rem; line-height: 1.2;">-</div>
                            <div class="text-secondary" id="printSupplierPhone"
                                style="font-size: 0.72rem; line-height: 1.2;">-</div>
                            <div class="text-secondary" id="printSupplierAddress"
                                style="font-size: 0.72rem; line-height: 1.2; word-break: break-word;">-</div>
                        </div>
                        <div class="col-6 ps-1 ps-sm-2">
                            <h6 class="fw-bold text-uppercase text-muted mb-0" style="font-size: 0.66rem;"><i
                                    class="bi bi-info-circle me-1"></i> Order & Delivery Specs</h6>
                            <div style="font-size: 0.72rem; line-height: 1.25;"><strong>Date Generated:</strong> <span
                                    id="printPoDate">-</span></div>
                            <div style="font-size: 0.72rem; line-height: 1.25;"><strong>Linked Requisition:</strong>
                                <span id="printRsNo">-</span>
                            </div>
                            <div class="text-truncate" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Project:</strong>
                                <span id="printProjectName">-</span>
                            </div>
                            <div class="text-truncate" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Payment Terms:</strong>
                                <span id="printPoTerms" class="fw-bold text-dark">-</span>
                            </div>
                            <div class="text-truncate" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Delivery Destination:</strong>
                                <span id="printDeliveryDestination" class="fw-bold text-primary">-</span>
                            </div>
                            <div id="printDeliveryAddressRow" class="text-secondary d-none" style="font-size: 0.70rem; line-height: 1.2; word-break: break-word;">
                                <strong>Drop Location:</strong> <span id="printDeliveryAddress">-</span>
                            </div>
                            <div class="text-danger fw-bold" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Warehouse Target ETA:</strong> <span id="printPoEta">-</span>
                            </div>
                            <div id="printSupplierDrRow" class="text-success fw-bold d-none" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Supplier DR / SI No.:</strong> <span id="printSupplierDrNo" class="badge bg-success-subtle text-success border border-success-subtle font-monospace">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3-Way Match Verification Banner & Quality Defect Banner -->
                    <div id="print3WayMatchBanner" class="alert alert-success d-flex align-items-center justify-content-between p-2 mb-2 border border-success-subtle rounded d-none" style="font-size: 0.72rem; background-color: #e8f5e9;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-shield-check text-success fs-5"></i>
                            <div>
                                <strong class="text-success text-uppercase">3-Way Match Verified (Audit Ready):</strong>
                                <span class="text-muted d-block" style="font-size: 0.68rem;">Purchase Order matched against Supplier Delivery Receipt / Sales Invoice & Physical Receiving Manifest.</span>
                            </div>
                        </div>
                        <span id="print3WayMatchDrBadge" class="badge bg-success text-white font-monospace px-2 py-1"></span>
                    </div>

                    <div id="printQualityDefectBanner" class="alert alert-warning d-flex align-items-center justify-content-between p-2 mb-2 border border-warning-subtle rounded d-none" style="font-size: 0.72rem; background-color: #fff9e6;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                            <div>
                                <strong class="text-dark text-uppercase">Quality Non-Conformance Recorded:</strong>
                                <span id="printDefectSummaryText" class="text-muted d-block" style="font-size: 0.68rem;">Defective or damaged items flagged during receiving inspection.</span>
                            </div>
                        </div>
                        <span id="printDefectTotalBadge" class="badge bg-danger text-white font-monospace px-2 py-1"></span>
                    </div>

                    <!-- Itemized Order Table -->
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h6 class="fw-bold text-dark text-uppercase mb-0" style="font-size: 0.72rem;"><i
                                class="bi bi-box-seam me-1"></i> Itemized Purchase Manifest</h6>
                    </div>
                    <div class="table-responsive border rounded mb-2 po-paper-table-wrap"
                        style="overflow-x: auto !important; -webkit-overflow-scrolling: touch;">
                        <table class="table table-bordered table-sm align-middle mb-0 po-doc-table"
                            style="font-size: 0.76rem; width: 100%;">
                            <thead class="table-dark text-uppercase"
                                style="background-color: #212529 !important; color: #ffffff !important; font-size: 0.68rem; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                                <tr style="background-color: #212529 !important; color: #ffffff !important;">
                                    <th class="text-center py-1 px-1"
                                        style="width: 22px; min-width: 20px; background-color: #212529 !important; color: #ffffff !important;">
                                        #</th>
                                    <th class="py-1 px-1 text-nowrap"
                                        style="width: 78px; min-width: 72px; background-color: #212529 !important; color: #ffffff !important;">
                                        Code</th>
                                    <th class="py-1 px-1"
                                        style="background-color: #212529 !important; color: #ffffff !important; min-width: 85px;">
                                        Item Name</th>
                                    <th class="text-center py-1 px-1"
                                        style="width: 34px; min-width: 30px; background-color: #212529 !important; color: #ffffff !important;">
                                        Qty</th>
                                    <th class="text-end py-1 px-1 text-nowrap"
                                        style="width: 58px; min-width: 52px; background-color: #212529 !important; color: #ffffff !important;">
                                        Price (₱)</th>
                                    <th class="text-end py-1 px-1 text-nowrap"
                                        style="width: 66px; min-width: 60px; background-color: #212529 !important; color: #ffffff !important;">
                                        Total (₱)</th>
                                </tr>
                            </thead>
                            <tbody id="printPoItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="5" class="text-end text-uppercase py-1 px-2"
                                        style="font-size: 0.74rem;">
                                        Total Order Value:</td>
                                    <td class="text-end text-primary py-1 px-1 fw-bold text-nowrap"
                                        id="printPoTotalValue" style="font-size: 0.82rem;">₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Remarks / Discrepancy Alert Note if present -->
                    <div id="printRemarksSection" class="mb-2 d-none">
                        <h6 class="fw-bold text-dark text-uppercase mb-0" style="font-size: 0.68rem;"><i
                                class="bi bi-chat-square-text me-1"></i> Logistics / Receiving Notes:</h6>
                        <div class="p-1.5 bg-light border rounded text-dark" id="printPoRemarks"
                            style="font-size: 0.72rem; line-height: 1.25;"></div>
                    </div>

                    <!-- Official Signatures Footer (Signature Over Printed Name) -->
                    <div class="row text-center mt-2 pt-1">
                        <div class="col-6">
                            <div class="d-flex flex-column align-items-center justify-content-end"
                                style="min-height: 28px;">
                                <div id="preparedSigImgWrap" class="d-none"
                                    style="position: relative; margin-bottom: -12px; z-index: 2; pointer-events: none;">
                                    <img id="printPreparedSigImg" src="" alt="Purchasing Signature"
                                        style="max-height: 36px; max-width: 130px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="border-bottom border-dark pb-0 fw-bold text-dark text-uppercase position-relative text-truncate px-1"
                                style="z-index: 1; font-size: 0.76rem; line-height: 1.2;" id="printPreparedBy">-</div>
                            <small class="text-muted text-uppercase fw-bold d-block mt-0"
                                style="font-size: 0.62rem;">Prepared By (Purchasing)</small>
                        </div>
                        <div class="col-6">
                            <div class="d-flex flex-column align-items-center justify-content-end"
                                style="min-height: 28px;">
                                <div id="approvedSigImgWrap" class="d-none"
                                    style="position: relative; margin-bottom: -12px; z-index: 2; pointer-events: none;">
                                    <img id="printApprovedSigImg" src="" alt="Management Signature"
                                        style="max-height: 36px; max-width: 130px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="border-bottom border-dark pb-0 fw-bold text-dark text-uppercase position-relative text-truncate px-1"
                                style="z-index: 1; font-size: 0.76rem; line-height: 1.2;" id="printApprovedBy">
                                Management Authorization</div>
                            <small class="text-muted text-uppercase fw-bold d-block mt-0"
                                style="font-size: 0.62rem;">Approved By (Management)</small>
                        </div>
                    </div>

                    <!-- Cryptographic Seal & Verification QR (Clean Minimalist PKI Style) -->
                    <div class="d-flex align-items-center justify-content-between p-2 mt-2 border-top seal-block"
                        style="border-top: 1px solid #e2e8f0 !important; background-color: #f8fafc; border-radius: 6px;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center bg-success-subtle text-success rounded-circle"
                                style="width: 32px; height: 32px; min-width: 32px;">
                                <i class="bi bi-shield-lock-fill" style="font-size: 1rem;"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"
                                    style="font-size: 0.76rem; letter-spacing: -0.2px; line-height: 1.15;">
                                    Certified Document
                                </div>
                                <div class="text-muted" style="font-size: 0.62rem; margin-top: 2px; line-height: 1.15;">
                                    <em>Scan QR code for tamper-evident audit trail & authenticity</em>
                                </div>
                            </div>
                        </div>
                        <div class="text-end ps-2 flex-shrink-0">
                            <img id="printPoQrCode" src="" alt="Verification QR" class="border rounded bg-white p-1 shadow-sm"
                                style="height: 62px; width: 62px; object-fit: contain;">
                        </div>
                    </div>
                            </div>
                        </div><!-- /#poTabDocPane -->

                        <!-- ==========================================
                             TAB 2: ATTACHED DELIVERY RECEIPT
                        =========================================== -->
                        <div class="tab-pane fade" id="poTabReceiptPane" role="tabpanel" aria-labelledby="poTabReceiptBtn">
                            <!-- State A: Receipt Is Attached -->
                            <div id="poReceiptAttachedView" class="d-none">
                                <div class="card border shadow-xs mb-3 bg-white">
                                    <div class="card-body p-3">
                                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-2 pb-2 mb-2 border-bottom">
                                            <div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                                                        <i class="bi bi-shield-check me-1"></i>Official Delivery Receipt Attached
                                                    </span>
                                                    <span class="badge bg-light text-dark border font-monospace" id="poReceiptFileExtBadge">JPG</span>
                                                </div>
                                                <div class="text-muted small mt-1" id="poReceiptUploadMeta">
                                                    <i class="bi bi-file-earmark-arrow-up me-1"></i>Document archived in secure storage
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 w-100 w-sm-auto justify-content-end">
                                                <a href="#" id="poReceiptExternalLink" target="_blank" class="btn btn-sm btn-outline-primary fw-bold">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i> Full Window
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold" id="poReceiptReplaceBtn" onclick="triggerPoReceiptModal(true)">
                                                    <i class="bi bi-arrow-repeat me-1"></i> Replace
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Preview Viewer Box (Responsive Image or PDF) -->
                                        <div class="bg-light rounded border p-2 text-center position-relative d-flex align-items-center justify-content-center" style="min-height: 280px; max-height: 560px; overflow: auto;">
                                            <!-- Image Preview Element -->
                                            <img id="poReceiptImagePreview" src="" alt="Proof of Delivery Receipt" class="img-fluid rounded shadow-sm d-none mx-auto" style="max-height: 520px; object-fit: contain; cursor: zoom-in;" onclick="window.open(this.src, '_blank')" title="Click to open high-resolution document in new tab">
                                            
                                            <!-- PDF Viewer Wrap -->
                                            <div id="poReceiptPdfWrap" class="ratio ratio-16x9 rounded w-100 d-none" style="min-height: 480px;">
                                                <iframe id="poReceiptPdfFrame" src="" class="rounded border-0" style="width: 100%; height: 100%;"></iframe>
                                            </div>
                                        </div>

                                        <!-- Receipt Reference / Invoicing Notes (if present) -->
                                        <div id="poReceiptNotesBox" class="mt-3 p-2.5 bg-light rounded border d-none">
                                            <h6 class="fw-bold text-dark text-uppercase mb-1" style="font-size: 0.72rem;">
                                                <i class="bi bi-file-earmark-medical me-1 text-primary"></i> Receipt / Invoicing Reference & Notes:
                                            </h6>
                                            <p class="text-secondary small mb-0 font-monospace" id="poReceiptNotesText" style="white-space: pre-wrap;"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- State B: Receipt NOT Attached (Clean ISO-standard Empty State) -->
                            <div id="poReceiptEmptyView" class="card border shadow-xs bg-white text-center py-5 px-3">
                                <div class="card-body">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-primary-subtle text-primary rounded-circle mb-3" style="width: 68px; height: 68px;">
                                        <i class="bi bi-receipt" style="font-size: 2.2rem;"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-1">No Delivery Receipt Document Attached</h5>
                                    <p class="text-muted small mx-auto mb-3" style="max-width: 480px;">
                                        The physical delivery receipt (DR), sales invoice, or delivery photo has not been uploaded for this purchase order yet.
                                    </p>
                                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                                        <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="triggerPoReceiptModal(false)">
                                            <i class="bi bi-cloud-arrow-up me-2"></i> Attach Delivery Receipt Now
                                        </button>
                                    </div>
                                    <div class="text-muted mt-3" style="font-size: 0.72rem;">
                                        <i class="bi bi-shield-check me-1"></i>ISO 9001 Clause 8.5.2 & 7.5 requires documentation of physical material transfers.
                                    </div>
                                </div>
                            </div>
                        </div><!-- /#poTabReceiptPane -->

                        <!-- ==========================================
                             TAB 3: LOGISTICS & FULFILLMENT TIMELINE
                        =========================================== -->
                        <div class="tab-pane fade" id="poTabTimelinePane" role="tabpanel" aria-labelledby="poTabTimelineBtn">
                            <!-- Order Specs Quick Summary -->
                            <div class="card border shadow-xs mb-3 bg-white">
                                <div class="card-body p-3">
                                    <div class="row g-2 align-items-center text-center text-sm-start">
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.68rem;">Current Status</small>
                                            <span id="poTimelineStatusBadge" class="badge bg-primary px-2 py-1 mt-0.5">Pending</span>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.68rem;">Supplier</small>
                                            <span id="poTimelineSupplier" class="fw-bold text-dark text-truncate d-block small mt-0.5">-</span>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.68rem;">Requisition</small>
                                            <span id="poTimelineRsNo" class="fw-bold text-primary text-truncate d-block small mt-0.5">-</span>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.68rem;">Target ETA</small>
                                            <span id="poTimelineEta" class="fw-bold text-danger text-truncate d-block small mt-0.5">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vertical Milestone Steps -->
                            <div class="card border shadow-xs mb-3 bg-white">
                                <div class="card-body p-3 p-sm-4">
                                    <h6 class="fw-bold text-dark text-uppercase mb-3" style="font-size: 0.78rem;">
                                        <i class="bi bi-signpost-split me-1.5 text-primary"></i> Order Lifecycle & Audit Trail
                                    </h6>
                                    <div class="po-timeline" id="poTimelineContainer">
                                        <!-- Dynamically populated via openPoPrintModal -->
                                    </div>
                                </div>
                            </div>

                            <!-- Discrepancy & Delay Alerts Log (if present) -->
                            <div id="poTimelineAlertsSection" class="card border shadow-xs bg-white mb-2 d-none">
                                <div class="card-header bg-warning-subtle text-warning-emphasis fw-bold py-2 px-3 d-flex align-items-center justify-content-between" style="font-size: 0.78rem;">
                                    <span><i class="bi bi-exclamation-triangle-fill me-1.5"></i> Logistics Alerts & Discrepancy History</span>
                                    <span class="badge bg-warning text-dark" id="poTimelineAlertCount">1</span>
                                </div>
                                <div class="card-body p-3" id="poTimelineAlertsBody" style="font-size: 0.78rem;">
                                    <!-- Populated dynamically -->
                                </div>
                            </div>
                        </div><!-- /#poTabTimelinePane -->

                    </div><!-- /#poDetailsTabContent -->
                </div><!-- /#poPrintModalContent -->
            </div><!-- /#poPrintDocumentBody -->

            <div class="modal-footer justify-content-between bg-white border-top-0 d-print-none">
                <button type="button" class="btn btn-light text-muted fw-bold px-4"
                    data-bs-dismiss="modal">Close</button>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary fw-bold px-3 d-none" id="poModalAttachReceiptBtn" onclick="triggerPoReceiptModal()">
                        <i class="bi bi-paperclip me-1"></i> Attach Receipt
                    </button>
                    <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="printPoDocument()"><i
                            class="bi bi-printer me-2"></i> Print Purchase Order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
  7. MODAL: VOID / CANCEL PURCHASE ORDER (ISO 9001 AUDITED)
=========================================== -->
<div class="modal fade" id="cancelPoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-slash-circle-fill text-danger me-2"></i>Void / Cancel Purchase Order
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="cancelPoForm" onsubmit="handleCancelPoSubmit(event)">
                <div class="modal-body p-4 bg-light">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" id="cancelPoId" name="po_id" value="">

                    <!-- Warning Alert Banner -->
                    <div class="alert alert-danger d-flex align-items-start p-3 mb-3 border-0 shadow-sm"
                        style="border-radius: 8px; font-size: 0.85rem;">
                        <i class="bi bi-exclamation-triangle-fill fs-5 text-danger me-2 mt-1 flex-shrink-0"></i>
                        <div>
                            <strong>Permanent Administrative Action:</strong> Cancelling this PO will permanently void
                            it, mark remaining unfulfilled items as Cancelled, and revert the linked Requisition back to
                            <strong>Approved</strong> so Purchasing can immediately reissue an order.
                        </div>
                    </div>

                    <!-- PO Reference Information -->
                    <div class="card border-0 shadow-sm mb-3 bg-white">
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label text-muted text-uppercase fw-bold mb-1"
                                        style="font-size: 0.72rem;">PO Number</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-danger bg-light"
                                        id="cancelPoNoDisplay" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted text-uppercase fw-bold mb-1"
                                        style="font-size: 0.72rem;">Linked Requisition</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-dark bg-light"
                                        id="cancelPoRsDisplay" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-muted text-uppercase fw-bold mb-1"
                                        style="font-size: 0.72rem;">Supplier</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-dark bg-light"
                                        id="cancelPoSupplierDisplay" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reason Selection -->
                    <div class="mb-3">
                        <label for="cancelPoReason" class="form-label fw-bold text-dark small text-uppercase">
                            Reason for Cancellation <span class="text-danger">*</span>
                        </label>
                        <select class="form-select shadow-sm" id="cancelPoReason" name="cancellation_reason" required>
                            <option value="" disabled selected>Select reason for cancellation...</option>
                            <option value="Supplier Out of Stock / Unfulfillable">Supplier Out of Stock / Unfulfillable
                            </option>
                            <option value="Pricing / Quotation Discrepancy">Pricing / Quotation Discrepancy</option>
                            <option value="Duplicate Order Created">Duplicate Order Created</option>
                            <option value="Project Requirement Cancelled / Revised">Project Requirement Cancelled /
                                Revised</option>
                            <option value="Supplier Lead Time Unacceptable / Severe Delay">Supplier Lead Time
                                Unacceptable / Severe Delay</option>
                            <option value="Supplier Unresponsive / Communication Breakdown">Supplier Unresponsive /
                                Communication Breakdown</option>
                            <option value="Administrative / Other Reason">Administrative / Other Reason</option>
                        </select>
                    </div>

                    <!-- Remarks Textarea -->
                    <div class="mb-2">
                        <label for="cancelPoNotes" class="form-label fw-bold text-dark small text-uppercase">
                            Audit Notes / Explanation <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control shadow-sm" id="cancelPoNotes" name="cancellation_notes" rows="3"
                            placeholder="Enter detailed audit justification for cancellation..." required></textarea>
                        <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-shield-check me-1"></i>This note will be permanently stamped into
                            audit trail.
                        </small>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white border-top-0 p-3">
                    <button type="button" class="btn btn-light text-muted fw-bold px-3" data-bs-dismiss="modal">Keep Order Active</button>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="btnSwitchSupplierPo" class="btn btn-warning fw-bold px-3 shadow-sm" onclick="handleCancelPoSubmit(event, true)">
                            <i class="bi bi-arrow-repeat me-1"></i> Void &amp; Switch Supplier
                        </button>
                        <button type="submit" id="confirmCancelPoBtn" class="btn btn-danger fw-bold px-3 shadow-sm">
                            <i class="bi bi-slash-circle me-1"></i> Void Purchase Order
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- ==========================================
  8. MODAL: UPLOAD / ATTACH POST-DELIVERY RECEIPT (ISO 9001 AUDITED)
=========================================== -->
<div class="modal fade" id="uploadReceiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="uploadReceiptModalTitle">
                    <i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Attach Delivery Receipt
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="uploadReceiptForm" onsubmit="handleUploadReceiptSubmit(event)" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_po_receipt">
                <input type="hidden" name="po_id" id="uploadReceiptPoId">

                <div class="modal-body p-3 p-sm-4 bg-light">
                    <!-- PO Details Summary Card -->
                    <div class="card border border-primary-subtle shadow-xs mb-3 bg-white">
                        <div class="card-body p-2.5">
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label text-muted text-uppercase fw-bold mb-1" style="font-size: 0.72rem;">PO Number</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-primary font-monospace bg-light"
                                        id="uploadReceiptPoNoDisplay" readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label text-muted text-uppercase fw-bold mb-1" style="font-size: 0.72rem;">Supplier</label>
                                    <input type="text" class="form-control form-control-sm fw-bold text-dark bg-light"
                                        id="uploadReceiptSupplierDisplay" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- File / Camera Selector Card -->
                    <div class="card border shadow-xs mb-3 bg-white">
                        <div class="card-body p-3">
                            <label class="form-label fw-bold text-dark small text-uppercase mb-2">
                                <i class="bi bi-paperclip text-primary me-1"></i> Receipt Document / Photo <span class="text-danger">*</span>
                            </label>

                            <input type="file" name="proof_of_receipt" id="uploadReceiptFileInput"
                                class="form-control fw-bold shadow-sm mb-2" accept="image/*,.pdf" capture="environment" required>

                            <small class="text-muted d-block" style="font-size: 0.75rem;">
                                <i class="bi bi-info-circle me-1"></i>Accepted formats: <strong>JPG, PNG, WebP, or PDF</strong> (max 10MB). On mobile devices, this opens camera or photo gallery directly.
                            </small>
                        </div>
                    </div>

                    <!-- Receipt Reference / Invoicing Notes -->
                    <div class="mb-2">
                        <label for="uploadReceiptNotes" class="form-label fw-bold text-dark small text-uppercase">
                            Official Receipt / Invoice Details <span class="text-muted fw-normal">(Optional)</span>
                        </label>
                        <textarea class="form-control shadow-sm" id="uploadReceiptNotes" name="receipt_notes" rows="2"
                            placeholder="e.g. Official Sales Invoice #SI-98421, received on Sept 11, 2026."></textarea>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-shield-check me-1"></i>A permanent ISO 9001 audit entry will be recorded with your name and timestamp.
                        </small>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-white border-top">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="confirmUploadReceiptBtn" class="btn btn-primary fw-bold px-4 shadow-sm">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Save Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
  9. MODAL: MANAGEMENT PO AUTHORIZATION (TWO-STEP APPROVAL)
=========================================== -->
<div class="modal fade" id="approvePoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark, #1e293b); color: white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-check me-2 text-success"></i>Management PO Authorization
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvePoForm" onsubmit="handleAuthorizePoSubmit(event)">
                <div class="modal-body bg-light p-3 p-md-4">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" id="authPoAction" value="approve_po">
                    <input type="hidden" name="po_id" id="authPoId" value="">

                    <!-- Order Summary Card -->
                    <div class="card border border-primary-subtle shadow-xs mb-3 bg-white">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                                <div>
                                    <span class="text-muted small text-uppercase fw-bold" style="font-size: 0.70rem;">Purchase Order</span>
                                    <h5 class="mb-0 fw-bold text-primary font-monospace" id="authPoNoDisplay">PO-0000</h5>
                                </div>
                                <span class="badge bg-warning text-dark border px-2.5 py-1.5 fw-semibold" id="authPoStatusDisplay" style="font-size: 0.72rem;">
                                    <i class="bi bi-hourglass-split me-1"></i>Pending Authorization
                                </span>
                            </div>

                            <div class="row g-2" style="font-size: 0.82rem;">
                                <div class="col-6">
                                    <span class="text-muted d-block small">Supplier:</span>
                                    <strong class="text-dark d-block text-truncate" id="authPoSupplierDisplay">-</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block small">Project / RS:</span>
                                    <strong class="text-dark d-block text-truncate" id="authPoProjectDisplay">-</strong>
                                </div>
                                <div class="col-12 mt-1">
                                    <span class="text-muted d-block small">Destination:</span>
                                    <span class="badge bg-light text-dark border px-2 py-0.5 fw-semibold" id="authPoDestinationDisplay">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authorization Remarks -->
                    <div class="mb-3" id="authPoNotesGroup">
                        <label for="authPoNotes" class="form-label fw-bold text-dark small text-uppercase">
                            Authorization Notes / Internal Remarks <span class="text-muted fw-normal">(Optional)</span>
                        </label>
                        <textarea class="form-control bg-white shadow-sm" id="authPoNotes" name="approval_notes" rows="2"
                            placeholder="e.g. Reviewed order specifications and approved for supplier release."></textarea>
                    </div>

                    <!-- Rejection Reason Group (Hidden by default, shown in reject mode) -->
                    <div class="mb-3 d-none" id="authPoRejectGroup">
                        <label for="authPoRejectReason" class="form-label fw-bold text-danger small text-uppercase">
                            Rejection / Disapproval Reason <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control bg-white border-danger shadow-sm" id="authPoRejectReason" name="rejection_reason" rows="2"
                            placeholder="Please specify why this purchase order is rejected (e.g. Incorrect pricing, change of supplier, budget constraint)..."></textarea>
                        <small class="text-danger d-block mt-1" style="font-size: 0.72rem;">
                            <i class="bi bi-exclamation-circle me-1"></i>Disapproval will revert the linked Requisition to Approved so Purchasing can prepare an amended PO.
                        </small>
                    </div>

                    <div class="alert alert-info px-3 py-2 mb-0 shadow-sm" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle-fill me-1 text-primary"></i>
                        Authorizing Officer: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Authorized Officer') ?></strong> (<?= strtoupper($_SESSION['user_role'] ?? 'OFFICER') ?>).
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-white border-top p-3 flex-wrap gap-2">
                    <div>
                        <button type="button" class="btn btn-outline-danger fw-bold px-3 d-flex align-items-center justify-content-center" id="toggleRejectPoBtn" onclick="toggleAuthPoRejectMode()" style="min-height: 44px;">
                            <i class="bi bi-x-circle me-1"></i> Disapprove PO
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light text-muted fw-bold px-3 d-flex align-items-center justify-content-center" data-bs-dismiss="modal" style="min-height: 44px;">Cancel</button>
                        <button type="submit" id="confirmAuthPoBtn" class="btn btn-success fw-bold px-4 shadow-sm d-flex align-items-center justify-content-center" style="min-height: 44px;">
                            <i class="bi bi-check2-circle me-1"></i> Authorize &amp; Approve PO
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.openApprovePoModal = function (id, poNo, supplierName, projectName, destination) {
        document.getElementById('authPoId').value = id;
        document.getElementById('authPoNoDisplay').innerText = poNo || ('PO-' + id);
        document.getElementById('authPoSupplierDisplay').innerText = supplierName || '-';
        document.getElementById('authPoProjectDisplay').innerText = projectName || '-';
        document.getElementById('authPoDestinationDisplay').innerText = destination || 'Warehouse (Central Storage)';
        document.getElementById('authPoNotes').value = '';
        document.getElementById('authPoRejectReason').value = '';

        // Reset to approve mode
        const rejectGroup = document.getElementById('authPoRejectGroup');
        const notesGroup = document.getElementById('authPoNotesGroup');
        const toggleBtn = document.getElementById('toggleRejectPoBtn');
        const confirmBtn = document.getElementById('confirmAuthPoBtn');
        const actionInput = document.getElementById('authPoAction');

        if (rejectGroup) rejectGroup.classList.add('d-none');
        if (notesGroup) notesGroup.classList.remove('d-none');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Disapprove PO';
            toggleBtn.className = 'btn btn-outline-danger btn-sm fw-bold px-3';
        }
        if (confirmBtn) {
            confirmBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Authorize &amp; Approve PO';
            confirmBtn.className = 'btn btn-success fw-bold px-4 shadow-sm';
        }
        if (actionInput) actionInput.value = 'approve_po';

        var myModalEl = document.getElementById('approvePoModal');
        var authModal = bootstrap.Modal.getInstance(myModalEl);
        if (!authModal) {
            authModal = new bootstrap.Modal(myModalEl);
        }
        authModal.show();
    };

    window.toggleAuthPoRejectMode = function () {
        const rejectGroup = document.getElementById('authPoRejectGroup');
        const notesGroup = document.getElementById('authPoNotesGroup');
        const toggleBtn = document.getElementById('toggleRejectPoBtn');
        const confirmBtn = document.getElementById('confirmAuthPoBtn');
        const actionInput = document.getElementById('authPoAction');
        const isCurrentlyReject = actionInput && actionInput.value === 'reject_po';

        if (isCurrentlyReject) {
            // Switch back to Approve
            if (actionInput) actionInput.value = 'approve_po';
            if (rejectGroup) rejectGroup.classList.add('d-none');
            if (notesGroup) notesGroup.classList.remove('d-none');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Disapprove PO';
                toggleBtn.className = 'btn btn-outline-danger fw-bold px-3 d-flex align-items-center justify-content-center';
            }
            if (confirmBtn) {
                confirmBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Authorize &amp; Approve PO';
                confirmBtn.className = 'btn btn-success fw-bold px-4 shadow-sm d-flex align-items-center justify-content-center';
            }
        } else {
            // Switch to Reject
            if (actionInput) actionInput.value = 'reject_po';
            if (rejectGroup) rejectGroup.classList.remove('d-none');
            if (notesGroup) notesGroup.classList.add('d-none');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i> Back to Approve';
                toggleBtn.className = 'btn btn-outline-secondary fw-bold px-3 d-flex align-items-center justify-content-center';
            }
            if (confirmBtn) {
                confirmBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Confirm Disapproval';
                confirmBtn.className = 'btn btn-danger fw-bold px-4 shadow-sm d-flex align-items-center justify-content-center';
            }
            const reasonInput = document.getElementById('authPoRejectReason');
            if (reasonInput) reasonInput.focus();
        }
    };

    window.handleAuthorizePoSubmit = function (event) {
        event.preventDefault();
        const form = document.getElementById('approvePoForm');
        if (!form) return;

        const action = document.getElementById('authPoAction')?.value || 'approve_po';
        const confirmBtn = document.getElementById('confirmAuthPoBtn');

        if (action === 'reject_po') {
            const reason = document.getElementById('authPoRejectReason')?.value?.trim();
            if (!reason) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Disapproval Reason Required',
                        text: 'Please specify the reason for rejecting/disapproving this Purchase Order.'
                    });
                } else {
                    alert('Please specify the reason for rejecting/disapproving this Purchase Order.');
                }
                document.getElementById('authPoRejectReason')?.focus();
                return;
            }
        }

        const originalBtnHtml = confirmBtn ? confirmBtn.innerHTML : '';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
        }

        const formData = new FormData(form);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        };
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        (window.cimsFetchWithTimeout || fetch)('process/process.php', {
            method: 'POST',
            body: formData,
            headers: headers
        })
            .then(res => res.json())
            .then(async data => {
                const isSuccess = data.status === 'success' || data.success === true || (data.status && data.status.toLowerCase() === 'ok');
                if (isSuccess) {
                    const myModalEl = document.getElementById('approvePoModal');
                    const authModal = bootstrap.Modal.getInstance(myModalEl);
                    if (authModal) authModal.hide();

                    if (typeof loadCombinedAlerts === 'function') loadCombinedAlerts();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: action === 'reject_po' ? 'PO Disapproved' : 'PO Authorized!',
                            text: data.message || (action === 'reject_po' ? 'Purchase order has been disapproved.' : 'Purchase order has been authorized and digitally approved.'),
                            timer: 1600,
                            showConfirmButton: false
                        });
                    }
                    location.reload();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Authorization Failed',
                            text: data.message || 'Failed to process PO authorization.'
                        });
                    } else {
                        alert(data.message || 'Failed to process PO authorization.');
                    }
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = originalBtnHtml;
                    }
                }
            })
            .catch(err => {
                console.error('Error authorizing PO:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'An error occurred while connecting to the server. Please try again.'
                    });
                } else {
                    alert('An error occurred. Please try again.');
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalBtnHtml;
                }
            });
    };
    window.openUploadReceiptModal = function (id, poNo, supplierName, hasExisting) {
        document.getElementById('uploadReceiptPoId').value = id;
        document.getElementById('uploadReceiptPoNoDisplay').value = poNo || ('PO-' + id);
        document.getElementById('uploadReceiptSupplierDisplay').value = supplierName || '-';
        document.getElementById('uploadReceiptFileInput').value = '';
        document.getElementById('uploadReceiptNotes').value = '';

        const titleEl = document.getElementById('uploadReceiptModalTitle');
        if (titleEl) {
            titleEl.innerHTML = (hasExisting && hasExisting != '0')
                ? '<i class="bi bi-arrow-repeat text-warning me-2"></i>Update / Replace Delivery Receipt'
                : '<i class="bi bi-cloud-arrow-up-fill text-primary me-2"></i>Attach Delivery Receipt';
        }

        var myModalEl = document.getElementById('uploadReceiptModal');
        var uploadModal = bootstrap.Modal.getInstance(myModalEl);
        if (!uploadModal) {
            uploadModal = new bootstrap.Modal(myModalEl);
        }
        uploadModal.show();
    };
    window.openCancelPoModal = function (id, poNo, supplierName, rsNo) {
        document.getElementById('cancelPoId').value = id;
        document.getElementById('cancelPoNoDisplay').value = poNo || ('PO-' + id);
        document.getElementById('cancelPoSupplierDisplay').value = supplierName || '-';
        document.getElementById('cancelPoRsDisplay').value = rsNo || 'N/A';
        document.getElementById('cancelPoReason').value = '';
        document.getElementById('cancelPoNotes').value = '';

        var myModalEl = document.getElementById('cancelPoModal');
        var cancelModal = bootstrap.Modal.getInstance(myModalEl);
        if (!cancelModal) {
            cancelModal = new bootstrap.Modal(myModalEl);
        }
        cancelModal.show();
    };
    window.openEditEtaModal = function (id, po_no, currentEta) {
        document.getElementById('editEtaPoId').value = id;
        document.getElementById('editEtaPoNo').value = po_no;
        if (currentEta) {
            document.getElementById('editEtaInputDate').value = currentEta;
        } else {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('editEtaInputDate').value = today;
        }

        var myModalEl = document.getElementById('editEtaModal');
        var editEtaModal = bootstrap.Modal.getInstance(myModalEl);
        if (!editEtaModal) {
            editEtaModal = new bootstrap.Modal(myModalEl);
        }
        editEtaModal.show();
    };

    window.handleUpdatePoEta = function (event) {
        event.preventDefault();
        const form = document.getElementById('editEtaForm');
        if (!form) return;

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-check2-circle me-1"></i> Save Updated ETA';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving ETA...';
        }

        const poId = document.getElementById('editEtaPoId').value;
        const etaDate = document.getElementById('editEtaInputDate').value;

        const formData = new FormData(form);
        formData.append('action', 'update_po_eta');
        formData.append('po_id', poId);
        formData.append('expected_delivery_date', etaDate);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        };
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        (window.cimsFetchWithTimeout || fetch)('process/process.php', {
            method: 'POST',
            body: formData,
            headers: headers
        })
            .then(res => res.json())
            .then(async data => {
                const isSuccess = data.status === 'success' || data.success === true || (data.status && data.status.toLowerCase() === 'ok');
                if (isSuccess) {
                    var myModalEl = document.getElementById('editEtaModal');
                    var editEtaModal = bootstrap.Modal.getInstance(myModalEl);
                    if (editEtaModal) editEtaModal.hide();

                    if (typeof loadCombinedAlerts === 'function') loadCombinedAlerts();

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'ETA Updated!',
                            text: data.message || 'Warehouse ETA has been successfully updated.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                    location.reload();
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update Failed',
                            text: data.message || 'Failed to update ETA.'
                        });
                    } else {
                        alert(data.message || 'Failed to update ETA');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }
            })
            .catch(err => {
                console.error('Error updating ETA:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'An error occurred while updating ETA.'
                    });
                } else {
                    alert('An error occurred while updating ETA.');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
    };

    // ==========================================================
    // DELAY MODAL LOGIC (SPA-Proofed & Cache Bypassed)
    // ==========================================================
    window.openDelayModal = function (id, po_no, currentEta) {
        document.getElementById('delayPoId').value = id;
        document.getElementById('delayPoNo').value = po_no;
        const etaInput = document.getElementById('delayNewEta');
        if (etaInput) {
            if (currentEta) {
                etaInput.value = currentEta;
            } else {
                const today = new Date().toISOString().split('T')[0];
                etaInput.value = today;
            }
        }

        // Safely retrieve or instantiate the Bootstrap modal to prevent backdrop glitches
        var myModalEl = document.getElementById('delayModal');
        var delayModal = bootstrap.Modal.getInstance(myModalEl);
        if (!delayModal) {
            delayModal = new bootstrap.Modal(myModalEl);
        }
        delayModal.show();
    };

    // ==========================================================
    // DELIVERY DESTINATION TOGGLE (Warehouse vs Direct to Jobsite)
    // ==========================================================
    window.togglePoDestinationFields = function () {
        const destTypeJobsite = document.getElementById('destTypeJobsite');
        const hiddenDest = document.getElementById('poDeliveryDestination');
        const addressGroup = document.getElementById('jobsiteAddressGroup');
        const addressInput = document.getElementById('poDeliveryAddress');
        const rsSelect = document.getElementById('poRsSelect');

        let projectName = '';
        if (rsSelect && rsSelect.selectedIndex >= 0) {
            const opt = rsSelect.options[rsSelect.selectedIndex];
            projectName = opt ? (opt.getAttribute('data-project') || '') : '';
        }

        if (destTypeJobsite && destTypeJobsite.checked) {
            if (hiddenDest) {
                hiddenDest.value = projectName ? `Direct to Jobsite: ${projectName}` : 'Direct to Jobsite';
            }
            if (addressGroup) addressGroup.classList.remove('d-none');
        } else {
            if (hiddenDest) {
                hiddenDest.value = 'Warehouse (Central Storage)';
            }
            if (addressGroup) addressGroup.classList.add('d-none');
        }
    };

    window.updatePoDestinationOnRsChange = function () {
        const rsSelect = document.getElementById('poRsSelect');
        if (!rsSelect || rsSelect.selectedIndex < 0) return;

        const opt = rsSelect.options[rsSelect.selectedIndex];
        if (!opt || !opt.value) return;

        const rsType = opt.getAttribute('data-type') || 'project';
        const projectName = opt.getAttribute('data-project') || '';
        const projectAddress = opt.getAttribute('data-address') || '';

        const badge = document.getElementById('rsTypeBadge');
        const destWarehouse = document.getElementById('destTypeWarehouse');
        const destJobsite = document.getElementById('destTypeJobsite');
        const addressInput = document.getElementById('poDeliveryAddress');

        const isRestock = (rsType === 'restock' || projectName === 'Warehouse Restock');

        if (badge) {
            badge.innerText = isRestock ? 'Central Storage' : (projectName || 'Jobsite');
        }

        if (isRestock) {
            if (destWarehouse) destWarehouse.checked = true;
            if (destJobsite) {
                destJobsite.disabled = true;
                destJobsite.closest('.destination-radio-wrap')?.classList.add('opacity-50');
            }
        } else {
            if (destJobsite) {
                destJobsite.disabled = false;
                destJobsite.closest('.destination-radio-wrap')?.classList.remove('opacity-50');
                destJobsite.checked = true;
            }
            if (addressInput && projectAddress && !addressInput.value) {
                addressInput.value = projectAddress;
            }
        }
        window.togglePoDestinationFields();
    };

    // ==========================================================
    // MODAL LIFECYCLE MANAGEMENT & DOUBLE-SUBMISSION LOCKING (SPA-SAFE)
    // ==========================================================
    window.initPoModalLifecycle = function () {
        // 1. Create PO Form (AJAX & Double-Submit Guard per cims-modal-ajax-handler)
        const createPoForm = document.getElementById('createPoForm');
        if (createPoForm && !createPoForm.dataset.boundAjax) {
            createPoForm.dataset.boundAjax = 'true';
            createPoForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!this.checkValidity()) {
                    this.reportValidity();
                    return;
                }

                const rsSelect = document.getElementById('poRsSelect');
                if (!rsSelect || !rsSelect.value) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Requisition Required',
                            text: 'Please select an approved requisition slip to generate the Purchase Order.'
                        });
                    } else {
                        alert('Please select an approved requisition slip.');
                    }
                    return;
                }

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-check-circle me-1"></i> Generate & Save PO';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating & Saving PO...';
                }

                try {
                    const formData = new FormData(this);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.querySelector('[name="csrf_token"]')?.value || '';
                    const headers = { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    };
                    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

                    const response = await (window.cimsFetchWithTimeout || fetch)('process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: headers
                    });

                    const rawText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(rawText);
                    } catch (jsonErr) {
                        console.error('Non-JSON response in createPoForm:', rawText);
                        throw new Error('Server returned an invalid response. Please refresh and try again.');
                    }

                    const isSuccess = result.status === 'success' || result.success === true;
                    if (isSuccess) {
                        const modalEl = document.getElementById('poModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Purchase Order Created!',
                                text: result.message || 'Purchase Order generated and sent to Supplier successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(result.message || 'Failed to create Purchase Order.');
                    }
                } catch (err) {
                    console.error('Error creating PO:', err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Action Failed',
                            text: err.message || 'An error occurred while creating the Purchase Order.'
                        });
                    } else {
                        alert(err.message || 'An error occurred while creating the Purchase Order.');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            });
        }

        // 2. Log Delay Form (AJAX & Double-Submit Guard per cims-modal-ajax-handler)
        const delayForm = document.getElementById('delayForm');
        if (delayForm && !delayForm.dataset.boundAjax) {
            delayForm.dataset.boundAjax = 'true';
            delayForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                if (!this.checkValidity()) {
                    this.reportValidity();
                    return;
                }

                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '<i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Delay Alert';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Submitting Delay Alert...';
                }

                try {
                    const formData = new FormData(this);
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || this.querySelector('[name="csrf_token"]')?.value || '';
                    const headers = { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    };
                    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

                    const response = await (window.cimsFetchWithTimeout || fetch)('process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: headers
                    });

                    const rawText = await response.text();
                    let result;
                    try {
                        result = JSON.parse(rawText);
                    } catch (jsonErr) {
                        console.error('Non-JSON response in delayForm:', rawText);
                        throw new Error('Server returned an invalid response. Please refresh and try again.');
                    }

                    const isSuccess = result.status === 'success' || result.success === true;
                    if (isSuccess) {
                        const modalEl = document.getElementById('delayModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'warning',
                                title: 'Delay Alert Logged',
                                text: result.message || 'Logistics delay & revised ETA successfully logged.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(result.message || 'Failed to log delay.');
                    }
                } catch (err) {
                    console.error('Error logging delay:', err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Action Failed',
                            text: err.message || 'An error occurred while logging the supply delay.'
                        });
                    } else {
                        alert(err.message || 'An error occurred while logging the supply delay.');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }
            });
        }

        // (Note: Receive / Stock In Form submission is managed via full AJAX handler in po.php per cims-modal-ajax-handler)

        // Modal Lifecycle Event Listeners (Autofocus & Form Cleanup)
        const poModal = document.getElementById('poModal');
        if (poModal && !poModal.dataset.boundLifecycle) {
            poModal.dataset.boundLifecycle = 'true';
            poModal.addEventListener('shown.bs.modal', function () {
                const rsSelect = document.getElementById('poRsSelect');
                if (rsSelect) rsSelect.focus();
            });
            poModal.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('createPoForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Generate & Save PO';
                }
                const preview = document.getElementById('rsItemsPreviewContainer');
                if (preview) preview.classList.add('d-none');
                
                // Reset destination fields
                const badge = document.getElementById('rsTypeBadge');
                if (badge) badge.innerText = 'Central Storage';
                const destWarehouse = document.getElementById('destTypeWarehouse');
                if (destWarehouse) destWarehouse.checked = true;
                const destJobsite = document.getElementById('destTypeJobsite');
                if (destJobsite) {
                    destJobsite.disabled = false;
                    destJobsite.closest('.destination-radio-wrap')?.classList.remove('opacity-50');
                }
                const addressGroup = document.getElementById('jobsiteAddressGroup');
                if (addressGroup) addressGroup.classList.add('d-none');
                const hiddenDest = document.getElementById('poDeliveryDestination');
                if (hiddenDest) hiddenDest.value = 'Warehouse (Central Storage)';
            });
        }

        const editEtaModal = document.getElementById('editEtaModal');
        if (editEtaModal && !editEtaModal.dataset.boundLifecycle) {
            editEtaModal.dataset.boundLifecycle = 'true';
            editEtaModal.addEventListener('shown.bs.modal', function () {
                const dateInput = document.getElementById('editEtaInputDate');
                if (dateInput) dateInput.focus();
            });
            editEtaModal.addEventListener('hidden.bs.modal', function () {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save Updated ETA';
                }
            });
        }

        const delayModal = document.getElementById('delayModal');
        if (delayModal && !delayModal.dataset.boundLifecycle) {
            delayModal.dataset.boundLifecycle = 'true';
            delayModal.addEventListener('shown.bs.modal', function () {
                const sel = this.querySelector('select[name="delay_type"]');
                if (sel) sel.focus();
            });
            delayModal.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('delayForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Delay Alert';
                }
            });
        }

        const receiveModal = document.getElementById('receiveModal');
        if (receiveModal && !receiveModal.dataset.boundLifecycle) {
            receiveModal.dataset.boundLifecycle = 'true';
            receiveModal.addEventListener('shown.bs.modal', function () {
                const drInput = document.getElementById('receiveSupplierDrNo');
                if (drInput) drInput.focus();
            });
            receiveModal.addEventListener('hidden.bs.modal', function () {
                if (typeof stopReceiptCamera === 'function') stopReceiptCamera();
                const form = document.getElementById('receiveForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                const drInput = document.getElementById('receiveSupplierDrNo');
                if (drInput) drInput.value = '';
                const notesInput = document.getElementById('receiveInspectionNotes');
                if (notesInput) notesInput.value = '';
                const submitBtn = document.getElementById('confirmReceiveBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Confirm & Stock In';
                }
            });
        }

        const viberModal = document.getElementById('viberPreviewModal');
        if (viberModal && !viberModal.dataset.boundLifecycle) {
            viberModal.dataset.boundLifecycle = 'true';
            viberModal.addEventListener('shown.bs.modal', function () {
                const phoneInput = document.getElementById('viberPhone');
                if (phoneInput && !phoneInput.value) phoneInput.focus();
            });
            viberModal.addEventListener('hidden.bs.modal', function () {
                const sendBtn = document.getElementById('sendViberSubmitBtn');
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fa-brands fa-viber me-1"></i> Send via Viber';
                }
            });
        }

        const cancelPoModal = document.getElementById('cancelPoModal');
        if (cancelPoModal && !cancelPoModal.dataset.boundLifecycle) {
            cancelPoModal.dataset.boundLifecycle = 'true';
            cancelPoModal.addEventListener('shown.bs.modal', function () {
                const reasonSelect = document.getElementById('cancelPoReason');
                if (reasonSelect) reasonSelect.focus();
            });
            cancelPoModal.addEventListener('hidden.bs.modal', function () {
                const submitBtn = document.getElementById('confirmCancelPoBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-slash-circle me-1"></i> Void Purchase Order';
                }
            });
        }

        const approvePoModal = document.getElementById('approvePoModal');
        if (approvePoModal && !approvePoModal.dataset.boundLifecycle) {
            approvePoModal.dataset.boundLifecycle = 'true';
            approvePoModal.addEventListener('shown.bs.modal', function () {
                const action = document.getElementById('authPoAction')?.value;
                if (action === 'reject_po') {
                    const reason = document.getElementById('authPoRejectReason');
                    if (reason) reason.focus();
                } else {
                    const notes = document.getElementById('authPoNotes');
                    if (notes) notes.focus();
                }
            });
            approvePoModal.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('approvePoForm');
                if (form) {
                    form.reset();
                    form.classList.remove('was-validated');
                }
                const confirmBtn = document.getElementById('confirmAuthPoBtn');
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Authorize &amp; Approve PO';
                    confirmBtn.className = 'btn btn-success fw-bold px-4 shadow-sm';
                }
                // Reset mode back to approve
                const rejectGroup = document.getElementById('authPoRejectGroup');
                const notesGroup = document.getElementById('authPoNotesGroup');
                const toggleBtn = document.getElementById('toggleRejectPoBtn');
                const actionInput = document.getElementById('authPoAction');
                if (actionInput) actionInput.value = 'approve_po';
                if (rejectGroup) rejectGroup.classList.add('d-none');
                if (notesGroup) notesGroup.classList.remove('d-none');
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> Disapprove PO';
                    toggleBtn.className = 'btn btn-outline-danger btn-sm fw-bold px-3';
                }
            });
        }
    };

    // Immediate execution for SPA compatibility
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initPoModalLifecycle);
    } else {
        window.initPoModalLifecycle();
    }
</script>