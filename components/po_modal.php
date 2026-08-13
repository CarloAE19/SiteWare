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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"
                        style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <!-- Added p-4 for premium spacing -->
                <div class="modal-body bg-light p-4">
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 border-danger border-top border-4 shadow-lg">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-cloud-lightning-rain-fill me-2"></i>Log
                    Supply Delay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4 bg-light">
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg border-top border-success border-4">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-success fw-bold"><i class="bi bi-box-seam me-2"></i>Verify Stock In
                    (Delivery Receipt)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    onclick="if(typeof stopReceiptCamera==='function') stopReceiptCamera();"></button>
            </div>
            <form method="POST" action="process/process.php" id="receiveForm" enctype="multipart/form-data">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="mark_po_delivered">
                    <input type="hidden" name="po_id" id="receivePoId">
                    <input type="hidden" name="po_no" id="receivePoNo">

                    <div class="alert alert-warning px-3 py-2 mb-3 shadow-sm"
                        style="font-size: 0.85rem; border-left: 3px solid #ffc107;">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> <strong>Attention:</strong> Count items &
                        verify unit prices against the delivery receipt/invoice. Stock-in will automatically update item
                        prices and <strong>Total Inventory Value</strong>.
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

                    <div class="table-responsive border rounded shadow-sm bg-white mb-2">
                        <table class="table table-hover align-middle mb-0 text-nowrap" id="receiveItemsTable">
                            <thead class="table-light text-muted" style="font-size: 0.8rem;">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th class="text-center">Expected Qty</th>
                                    <th class="text-center" style="width: 130px;">Actual Received Qty</th>
                                    <th class="text-center" style="width: 145px;">Unit Price (₱)</th>
                                    <th class="text-end" style="width: 120px;">Subtotal (₱)</th>
                                </tr>
                            </thead>
                            <tbody id="receiveItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
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
  4. MODAL: VIEW DISCREPANCY DETAILS
=========================================== -->
<div class="modal fade" id="discrepancyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-top border-danger border-4">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-danger fw-bold"><i
                        class="bi bi-exclamation-octagon-fill me-2"></i>Discrepancy Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                    <span class="text-muted fw-bold text-uppercase small"><i
                            class="bi bi-file-earmark-text me-1"></i>Purchase Order</span>
                    <span id="discPoNo"
                        class="fw-bold text-dark fs-6 bg-white px-3 py-1 rounded shadow-sm border"></span>
                </div>

                <h6 class="fw-bold text-secondary mb-2 small text-uppercase pb-1">Activity & Log Details</h6>
                <div class="p-3 bg-white border border-danger border-opacity-25 rounded shadow-sm"
                    style="max-height: 300px; overflow-y: auto;">
                    <div id="discRemarks" class="text-dark"
                        style="font-size: 0.95rem; white-space: pre-wrap; line-height: 1.7;"></div>
                </div>
                <div id="discProofContainer" class="mt-3 d-none">
                    <!-- Proof of receipt link dynamically injected -->
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold px-4 shadow-sm"
                    data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
  5. MODAL: REVIEW AND SEND VIBER ORDER
=========================================== -->
<div class="modal fade" id="viberPreviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
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
                            <label class="form-label fw-bold small text-muted text-uppercase">Recipient Phone
                                Number</label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white text-muted"><i
                                        class="bi bi-telephone-fill"></i></span>
                                <input type="text" id="viberPhone" class="form-control fw-bold bg-white text-dark"
                                    placeholder="Enter recipient phone number..." required>
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
  MODAL: UPDATE PO ETA (Warehouse Delivery Target)
=========================================== -->
<div class="modal fade" id="editEtaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar2-week me-2"
                        style="color: var(--gb-yellow);"></i>Update Warehouse Supply ETA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editEtaForm" onsubmit="handleUpdatePoEta(event)">
                <div class="modal-body bg-light p-4">
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

<!-- ==========================================
  6. MODAL: VIRTUAL PURCHASE ORDER PAPER & PRINT VIEW
=========================================== -->
<div class="modal fade" id="poPrintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white d-print-none">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-pdf-fill me-2"
                        style="color: var(--gb-yellow);"></i>Purchase Order Virtual Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4 bg-light" id="poPrintDocumentBody">
                <div class="text-center text-muted py-5" id="poPrintLoadingSpinner">
                    <div class="spinner-border text-primary me-2"></div> Loading Purchase Order Document...
                </div>

                <!-- Printable Virtual Paper Container -->
                <div class="bg-white border p-4 p-md-5 rounded-3 shadow-sm d-none" id="poPrintPaper">
                    <!-- Letterhead Header -->
                    <div class="row align-items-center pb-3 mb-4 border-bottom border-2 border-dark">
                        <div class="col-8">
                            <div class="d-flex align-items-center">
                                <img src="assets/LogoGB.png" alt="GB Construction Logo" class="me-3"
                                    style="height: 52px; width: auto; object-fit: contain;">
                                <div>
                                    <h3 class="fw-bold text-dark mb-0" style="letter-spacing: -0.5px;">GENETIAN BUILDERS
                                    </h3>
                                    <div class="text-uppercase fw-bold text-primary small" style="letter-spacing: 1px;">
                                        CONSTRUCTION & ENTERPRISE INC.</div>
                                    <small class="text-muted d-block mt-1">Official Purchase Order & Supplier
                                        Manifest</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <span class="badge bg-dark px-3 py-2 fs-6 text-uppercase" id="printPoStatus">Pending</span>
                            <div class="fw-bold fs-5 text-primary mt-2" id="printPoNo">PO-000000</div>
                        </div>
                    </div>

                    <!-- Metadata Grid: Supplier & Order Specs -->
                    <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                        <div class="col-12 col-md-6 border-end-md">
                            <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="bi bi-building me-1"></i>
                                Supplier Information</h6>
                            <div class="fw-bold text-dark fs-6" id="printSupplierName">-</div>
                            <div class="text-secondary small" id="printSupplierContact">-</div>
                            <div class="text-secondary small" id="printSupplierPhone">-</div>
                            <div class="text-secondary small" id="printSupplierAddress">-</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <h6 class="fw-bold text-uppercase text-muted small mb-2"><i
                                    class="bi bi-info-circle me-1"></i> Order & Delivery Specs</h6>
                            <div class="small"><strong>Date Generated:</strong> <span id="printPoDate">-</span></div>
                            <div class="small"><strong>Linked Requisition:</strong> <span id="printRsNo">-</span></div>
                            <div class="small"><strong>Project Destination:</strong> <span
                                    id="printProjectName">-</span></div>
                            <div class="small text-danger fw-bold"><strong>Warehouse Target ETA:</strong> <span
                                    id="printPoEta">-</span></div>
                        </div>
                    </div>

                    <!-- Itemized Order Table -->
                    <h6 class="fw-bold text-dark text-uppercase mb-2 small"><i class="bi bi-box-seam me-1"></i> Itemized
                        Purchase Manifest</h6>
                    <div class="table-responsive border rounded mb-4">
                        <table class="table table-bordered align-middle mb-0 text-nowrap" style="font-size: 0.9rem;">
                            <thead class="table-dark text-uppercase small"
                                style="background-color: #212529 !important; color: #ffffff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                                <tr style="background-color: #212529 !important; color: #ffffff !important;">
                                    <th class="text-center"
                                        style="width: 40px; background-color: #212529 !important; color: #ffffff !important;">
                                        #</th>
                                    <th style="background-color: #212529 !important; color: #ffffff !important;">Item
                                        Code</th>
                                    <th style="background-color: #212529 !important; color: #ffffff !important;">Item
                                        Name</th>
                                    <th class="text-center"
                                        style="background-color: #212529 !important; color: #ffffff !important;">
                                        Quantity</th>
                                    <th class="text-end"
                                        style="background-color: #212529 !important; color: #ffffff !important;">Unit
                                        Price (₱)</th>
                                    <th class="text-end"
                                        style="background-color: #212529 !important; color: #ffffff !important;">Total
                                        Amount (₱)</th>
                                </tr>
                            </thead>
                            <tbody id="printPoItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="5" class="text-end text-uppercase fs-6">Total Order Value:</td>
                                    <td class="text-end text-primary fs-5" id="printPoTotalValue">₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Remarks / Discrepancy Alert Note if present -->
                    <div id="printRemarksSection" class="mb-4 d-none">
                        <h6 class="fw-bold text-dark text-uppercase small mb-1"><i
                                class="bi bi-chat-square-text me-1"></i> Logistics / Receiving Notes:</h6>
                        <div class="p-3 bg-light border rounded small text-dark" id="printPoRemarks"></div>
                    </div>

                    <!-- Official Signatures Footer (Signature Over Printed Name) -->
                    <div class="row text-center mt-4 pt-3">
                        <div class="col-6">
                            <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                                <div id="preparedSigImgWrap" class="d-none" style="position: relative; margin-bottom: -22px; z-index: 2; pointer-events: none;">
                                    <img id="printPreparedSigImg" src="" alt="Purchasing Signature" style="max-height: 65px; max-width: 220px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase position-relative" style="z-index: 1;" id="printPreparedBy">-</div>
                            <small class="text-muted text-uppercase fw-bold d-block mt-1"
                                style="font-size: 0.75rem;">Prepared By (Purchasing Officer)</small>
                        </div>
                        <div class="col-6">
                            <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                                <div id="approvedSigImgWrap" class="d-none" style="position: relative; margin-bottom: -22px; z-index: 2; pointer-events: none;">
                                    <img id="printApprovedSigImg" src="" alt="Management Signature" style="max-height: 65px; max-width: 220px; object-fit: contain;">
                                </div>
                            </div>
                            <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase position-relative" style="z-index: 1;" id="printApprovedBy">
                                Management Authorization</div>
                            <small class="text-muted text-uppercase fw-bold d-block mt-1"
                                style="font-size: 0.75rem;">Approved By (Management)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-between bg-white border-top-0 d-print-none">
                <button type="button" class="btn btn-light text-muted fw-bold px-4"
                    data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm" onclick="printPoDocument()"><i
                        class="bi bi-printer me-2"></i> Print Purchase Order</button>
            </div>
        </div>
    </div>
</div>

<script>
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
        const poId = document.getElementById('editEtaPoId').value;
        const etaDate = document.getElementById('editEtaInputDate').value;

        const formData = new FormData();
        formData.append('action', 'update_po_eta');
        formData.append('po_id', poId);
        formData.append('expected_delivery_date', etaDate);

        fetch('process/process.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    var myModalEl = document.getElementById('editEtaModal');
                    var editEtaModal = bootstrap.Modal.getInstance(myModalEl);
                    if (editEtaModal) editEtaModal.hide();

                    if (typeof loadCombinedAlerts === 'function') loadCombinedAlerts();

                    // Reload or update DOM
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update ETA');
                }
            })
            .catch(err => {
                console.error('Error updating ETA:', err);
                alert('An error occurred while updating ETA.');
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
    }
</script>