<?php
// Fetch Data needed for Create PO Modal
// Include performance score for each supplier so the Purchasing Officer can make informed decisions
$suppliers = $pdo->query("
    SELECT
        s.id,
        s.company_name,
        COUNT(p.id)                                                               AS total_po,
        SUM(CASE WHEN p.status LIKE '%Delayed%' THEN 1 ELSE 0 END)               AS delayed_count,
        SUM(CASE WHEN p.status LIKE '%Discrepancy%' THEN 1 ELSE 0 END)           AS discrepancy_count
    FROM suppliers s
    LEFT JOIN purchase_orders p ON p.supplier_id = s.id
    WHERE s.status = 'Active'
    GROUP BY s.id
    ORDER BY s.company_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$approvedRS = $pdo->query("SELECT id, rs_no, project_name FROM requisitions WHERE status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ==========================================
  1. MODAL: CREATE NEW PURCHASE ORDER
=========================================== -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2" style="color: var(--gb-yellow);"></i>Generate Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <!-- Added p-4 for premium spacing -->
                <div class="modal-body bg-light p-4">
                    <input type="hidden" name="action" value="create_po">

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Auto-Generated PO Number</label>
                        <input type="text" class="form-control fw-bold text-primary bg-white shadow-sm" name="po_no" value="PO-<?= date('Ymd') ?>-<?= rand(100, 999) ?>" readonly>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Approved Requisition (RS) <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="rs_id" id="poRsSelect" required>
                            <option value="" disabled selected>-- Select an Approved RS --</option>
                            <?php foreach ($approvedRS as $rs): ?>
                                <option value="<?= $rs['id'] ?>"><?= $rs['rs_no'] ?> - <?= htmlspecialchars($rs['project_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i class="bi bi-info-circle me-1"></i>Only RS approved by Management appear here.</small>
                    </div>

                    <!-- NEW: Item History Preview -->
                    <div class="mb-4 d-none" id="rsItemsPreviewContainer">
                        <label class="form-label fw-bold small text-muted text-uppercase">Items to Purchase & History</label>
                        <div class="table-responsive border rounded shadow-sm bg-white">
                            <table class="table table-sm table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
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
                        <label class="form-label fw-bold small text-muted text-uppercase">Select Supplier <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm" name="supplier_id" required>
                            <option value="" disabled selected>-- Select Supplier --</option>
                            <?php foreach ($suppliers as $sup):
                                $total = (int)$sup['total_po'];
                                if ($total === 0) {
                                    $tier  = '🔘 New';
                                    $score = '';
                                } else {
                                    $onTime   = ($total - (int)$sup['delayed_count'])     / $total * 100;
                                    $accuracy = ($total - (int)$sup['discrepancy_count']) / $total * 100;
                                    $sc       = round(($onTime + $accuracy) / 2, 1);
                                    if ($sc >= 90)      { $tier = '🟢 Excellent'; }
                                    elseif ($sc >= 70)  { $tier = '🟡 Average';   }
                                    else                { $tier = '🔴 Poor';      }
                                    $score = ' — ' . $sc . '%';
                                }
                            ?>
                                <option value="<?= $sup['id'] ?>">
                                    <?= htmlspecialchars($sup['company_name']) ?> [<?= $tier ?><?= $score ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;"><i class="bi bi-bar-chart-line me-1"></i>Performance score based on delivery history. 🟢 Excellent ≥90% &nbsp; 🟡 Average ≥70% &nbsp; 🔴 Poor &lt;70%</small>
                    </div>
                </div>
                <!-- Clean white footer -->
                <div class="modal-footer justify-content-between bg-white border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand fw-bold px-4 shadow-sm"><i class="bi bi-check-circle me-1"></i> Generate & Save PO</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ==========================================
  2. MODAL: LOG WEATHER/LOGISTICS DELAY
=========================================== -->
<div class="modal fade" id="delayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 border-danger border-top border-4 shadow-lg">
            <div class="modal-header bg-white">
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-cloud-lightning-rain-fill me-2"></i>Log Supply Delay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="log_po_delay">
                    <input type="hidden" name="po_id" id="delayPoId">
                    <input type="hidden" name="po_no" id="delayPoNo">

                    <div class="alert alert-danger px-3 py-2 mb-4 shadow-sm" style="font-size: 0.8rem; border-left: 3px solid #dc3545;">
                        <i class="bi bi-info-circle-fill me-1"></i> Flagging this PO will instantly notify Management.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">Reason for Delay <span class="text-danger">*</span></label>
                        <select class="form-select fw-bold shadow-sm mb-2 text-danger" name="delay_type" required>
                            <option value="Weather / Typhoon">Weather / Typhoon</option>
                            <option value="Road / Traffic Conditions">Road / Traffic Conditions</option>
                            <option value="Supplier Out of Stock">Supplier Out of Stock</option>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-bold small text-muted text-uppercase">Additional Remarks</label>
                        <textarea class="form-control fw-bold shadow-sm" name="remarks" rows="2" placeholder="e.g. Typhoon Basyang blocking port..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white p-3 border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm px-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> Submit Alert</button>
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
                <h5 class="modal-title text-success fw-bold"><i class="bi bi-box-seam me-2"></i>Verify Stock In (Delivery Receipt)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="process/process.php" id="receiveForm">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="mark_po_delivered">
                    <input type="hidden" name="po_id" id="receivePoId">
                    <input type="hidden" name="po_no" id="receivePoNo">

                    <div class="alert alert-warning px-3 py-2 mb-4 shadow-sm" style="font-size: 0.85rem; border-left: 3px solid #ffc107;">
                        <i class="bi bi-exclamation-circle-fill me-1"></i> <strong>Attention Warehouse:</strong> Physically count the items against the manifest. Any mismatch from the expected quantity will automatically trigger a Discrepancy Alert to Management.
                    </div>

                    <div class="table-responsive border rounded shadow-sm bg-white mb-2">
                        <table class="table table-hover align-middle mb-0 text-nowrap" id="receiveItemsTable">
                            <thead class="table-light text-muted" style="font-size: 0.8rem;">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th class="text-center">Expected Qty</th>
                                    <th class="text-center" style="width: 150px;">Actual Received Qty</th>
                                </tr>
                            </thead>
                            <tbody id="receiveItemsBody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer justify-content-between bg-white p-3 border-top-0">
                    <button type="button" class="btn btn-light text-muted fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" id="confirmReceiveBtn"><i class="bi bi-check2-all me-1"></i>Confirm & Stock In</button>
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
                <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i>Discrepancy Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                    <span class="text-muted fw-bold text-uppercase small"><i class="bi bi-file-earmark-text me-1"></i>Purchase Order</span>
                    <span id="discPoNo" class="fw-bold text-dark fs-6 bg-white px-3 py-1 rounded shadow-sm border"></span>
                </div>

                <h6 class="fw-bold text-secondary mb-2 small text-uppercase pb-1">Activity & Log Details</h6>
                <div class="p-3 bg-white border border-danger border-opacity-25 rounded shadow-sm" style="max-height: 350px; overflow-y: auto;">
                    <div id="discRemarks" class="text-dark" style="font-size: 0.95rem; white-space: pre-wrap; line-height: 1.7;"></div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold px-4 shadow-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================================
    // DELAY MODAL LOGIC (SPA-Proofed & Cache Bypassed)
    // ==========================================================
    window.openDelayModal = function(id, po_no) {
        document.getElementById('delayPoId').value = id;
        document.getElementById('delayPoNo').value = po_no;

        // Safely retrieve or instantiate the Bootstrap modal to prevent backdrop glitches
        var myModalEl = document.getElementById('delayModal');
        var delayModal = bootstrap.Modal.getInstance(myModalEl);
        if (!delayModal) {
            delayModal = new bootstrap.Modal(myModalEl);
        }
        delayModal.show();
    }
</script>