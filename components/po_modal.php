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
// Fetch Approved AND Partially Approved Restock Requisitions for PO creation
// Partially Approved RSes are valid for PO — only their approved items will be included
$approvedRS = $pdo->query("
    SELECT id, rs_no, project_name, status 
    FROM requisitions 
    WHERE status IN ('Approved', 'Partially Approved') 
      AND (type = 'restock' OR project_name = 'Warehouse Restock') 
    ORDER BY 
        FIELD(status, 'Approved', 'Partially Approved'),
        created_at DESC
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
            <form method="POST" action="process/process.php" id="createPoForm">
                <!-- Added p-4 for premium spacing -->
                <div class="modal-body bg-light p-4">
                    <?php if (function_exists('generate_csrf_token')): ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="action" value="create_po">

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Auto-Generated PO
                            Number</label>
                        <input type="text" class="form-control fw-bold text-primary bg-white shadow-sm" name="po_no"
                            value="PO-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Approved Requisition
                            (RS) <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="rs_id" id="poRsSelect" required>
                            <option value="" disabled selected>-- Select an Approved RS --</option>
                            <?php foreach ($approvedRS as $rs):
                                $isPartial = $rs['status'] === 'Partially Approved';
                                $statusLabel = $isPartial ? ' ⚠️ [Partially Approved]' : ' ✅ [Approved]';
                                ?>
                                <option value="<?= $rs['id'] ?>"><?= $rs['rs_no'] ?> -
                                    <?= htmlspecialchars($rs['project_name']) ?>     <?= $statusLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i
                                class="bi bi-info-circle me-1"></i>Approved and Partially Approved RSes appear here.
                            Only approved items from each RS will be included in the PO.</small>
                    </div>

                    <!-- NEW: Item History Preview -->
                    <div class="mb-4 d-none" id="rsItemsPreviewContainer">
                        <label class="form-label fw-bold small text-muted text-uppercase">Items to Purchase &
                            History</label>
                        <div class="table-responsive border rounded shadow-sm bg-white">
                            <table class="table table-sm table-hover align-middle mb-0 text-nowrap"
                                style="font-size: 0.85rem;">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th>Item Name</th>
                                        <th class="text-center">Qty</th>
                                        <th>Past Supplier</th>
                                    </tr>
                                </thead>
                                <tbody id="rsItemsPreviewBody">
                                    <!-- Populated via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Supplier <span
                                class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="supplier_id" required>
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
                                <option value="<?= $sup['id'] ?>">
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

                    <!-- Info Alert Explaining Partial Deliveries -->
                    <div class="alert alert-info border-0 shadow-sm mb-3 d-flex align-items-center py-2 px-3 rounded-3"
                        style="font-size: 0.85rem; background-color: #e8f4fd; color: #0d47a1;">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0 text-primary"></i>
                        <div>
                            <strong>Multi-Stage Delivery:</strong> Enter the quantity physically arriving in this
                            shipment. If fewer units arrive, choose whether the remainder is <strong>To Follow</strong>
                            (supplier has pending stock, keeps PO open) or <strong>Sold Out</strong> (supplier
                            cancelled). Master Inventory only increments by the units received today.
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
                                    <th style="min-width: 180px;">Item Description</th>
                                    <th class="text-center" style="width: 75px;">Ordered</th>
                                    <th class="text-center" style="width: 75px;">Prior Recv</th>
                                    <th class="text-center" style="width: 80px;">Remaining</th>
                                    <th class="text-center" style="width: 110px;">Receive Today</th>
                                    <th class="text-center" style="width: 120px;">Unit Price (₱)</th>
                                    <th class="text-center" style="min-width: 200px;">Supplier Status / If Incomplete
                                    </th>
                                    <th class="text-end" style="width: 110px;">Batch Subtotal</th>
                                </tr>
                            </thead>
                            <tbody id="receiveItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr>
                                    <td colspan="7" class="text-end fw-bold text-muted text-uppercase small py-2">Batch
                                        Delivery Total:</td>
                                    <td class="text-end fw-bold text-success fs-6 py-2" id="receiveBatchTotalVal">₱0.00
                                    </td>
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
                            <span id="discPoNo"
                                class="fw-bold font-monospace text-primary fs-6 bg-light px-3 py-1 rounded border"></span>
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
                <div class="text-center text-muted py-5" id="poPrintLoadingSpinner">
                    <div class="spinner-border text-primary me-2"></div> Loading Purchase Order Details...
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
                            <div class="text-danger fw-bold" style="font-size: 0.72rem; line-height: 1.25;">
                                <strong>Warehouse Target ETA:</strong> <span id="printPoEta">-</span>
                            </div>
                        </div>
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
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Keep
                        Order Active</button>
                    <button type="submit" id="confirmCancelPoBtn" class="btn btn-danger fw-bold px-4 shadow-sm">
                        <i class="bi bi-slash-circle me-1"></i> Void Purchase Order
                    </button>
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

<script>
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

        fetch('process/process.php', {
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
    // MODAL LIFECYCLE MANAGEMENT & DOUBLE-SUBMISSION LOCKING
    // ==========================================================
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Create PO Form
        const createPoForm = document.getElementById('createPoForm');
        if (createPoForm) {
            createPoForm.addEventListener('submit', function (e) {
                if (!this.checkValidity()) {
                    this.reportValidity();
                    e.preventDefault();
                    return;
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Generating & Saving PO...';
                }
            });
        }

        // 2. Log Delay Form
        const delayForm = document.getElementById('delayForm');
        if (delayForm) {
            delayForm.addEventListener('submit', function (e) {
                if (!this.checkValidity()) {
                    this.reportValidity();
                    e.preventDefault();
                    return;
                }
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Submitting Delay Alert...';
                }
            });
        }

        // (Note: Receive / Stock In Form submission is managed via full AJAX handler in po.php per cims-modal-ajax-handler)

        // Modal Lifecycle Event Listeners (Autofocus & Form Cleanup)
        const poModal = document.getElementById('poModal');
        if (poModal) {
            poModal.addEventListener('shown.bs.modal', function () {
                const rsSelect = document.getElementById('poRsSelect');
                if (rsSelect) rsSelect.focus();
            });
            poModal.addEventListener('hidden.bs.modal', function () {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Generate & Save PO';
                }
                const preview = document.getElementById('rsItemsPreviewContainer');
                if (preview) preview.classList.add('d-none');
            });
        }

        const editEtaModal = document.getElementById('editEtaModal');
        if (editEtaModal) {
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
        if (delayModal) {
            delayModal.addEventListener('shown.bs.modal', function () {
                const sel = this.querySelector('select[name="delay_type"]');
                if (sel) sel.focus();
            });
            delayModal.addEventListener('hidden.bs.modal', function () {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Delay Alert';
                }
            });
        }

        const receiveModal = document.getElementById('receiveModal');
        if (receiveModal) {
            receiveModal.addEventListener('hidden.bs.modal', function () {
                if (typeof stopReceiptCamera === 'function') stopReceiptCamera();
                const submitBtn = document.getElementById('confirmReceiveBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-all me-1"></i>Confirm & Stock In';
                }
            });
        }

        const viberModal = document.getElementById('viberPreviewModal');
        if (viberModal) {
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
        if (cancelPoModal) {
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
    });
</script>