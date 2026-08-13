<?php
if (defined('CIMS_ENTITY_MODALS_LOADED')) {
    return;
}
define('CIMS_ENTITY_MODALS_LOADED', true);
?>

<!-- ======================================================== -->
<!-- MODAL: VIEW PURCHASE ORDER (PO) DETAILS                   -->
<!-- ======================================================== -->
<div class="modal fade" id="viewPoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2" style="color: var(--gb-yellow);"></i>Purchase Order Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2 flex-wrap">
                            <span id="viewPoNo">PO-0000</span>
                            <span id="viewPoStatus" class="badge shadow-sm" style="font-size: 0.75rem;">Generated</span>
                        </h4>
                        <div class="text-dark fw-bold mt-1" style="font-size: 1rem;">Supplier: <span id="viewPoSupplier" class="text-primary">Supplier Name</span></div>
                        <div class="mt-2 text-muted small">
                            Project / RS: <strong id="viewPoProject" class="text-dark">Project Name</strong> (<span id="viewPoRsNo" class="text-primary">RS-0000</span>)<br>
                            Expected Delivery (ETA): <strong id="viewPoEta" class="text-dark">Date</strong><br>
                            Prepared By: <strong id="viewPoPreparedBy" class="text-dark">User</strong>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted fw-bold text-uppercase">Total Order Value</div>
                        <h4 class="fw-bold text-success mb-0" id="viewPoTotalVal">₱0.00</h4>
                    </div>
                </div>

                <!-- Delay Remarks Alert Box if any -->
                <div id="viewPoDelayBox" class="alert alert-warning shadow-sm d-none mb-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delay Remarks / Delivery Status</h6>
                    <p class="mb-0 small" id="viewPoDelayRemarks">No delay details.</p>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Ordered Materials & Unit Prices:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody id="viewPoItemsBody"></tbody>
                    </table>
                </div>

                <!-- Signature Over Printed Name Footer -->
                <div class="row text-center mt-4 pt-3 border-top">
                    <div class="col-6">
                        <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                            <div id="viewPoPrepSigWrap" class="d-none" style="position: relative; margin-bottom: -20px; z-index: 2; pointer-events: none;">
                                <img id="viewPoPrepSigImg" src="" alt="Purchasing Signature" style="max-height: 60px; max-width: 200px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase small position-relative" style="z-index: 1;" id="viewPoPreparedByText">-</div>
                        <small class="text-muted text-uppercase fw-bold d-block mt-1" style="font-size: 0.7rem;">Prepared By (Purchasing Officer)</small>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                            <div id="viewPoAppSigWrap" class="d-none" style="position: relative; margin-bottom: -20px; z-index: 2; pointer-events: none;">
                                <img id="viewPoAppSigImg" src="" alt="Management Signature" style="max-height: 60px; max-width: 200px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase small position-relative" style="z-index: 1;" id="viewPoApprovedByText">Management Authorization</div>
                        <small class="text-muted text-uppercase fw-bold d-block mt-1" style="font-size: 0.7rem;">Approved By (Management)</small>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-end bg-white border-top-0">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW MATERIAL WITHDRAWAL DETAILS                   -->
<!-- ======================================================== -->
<div class="modal fade" id="viewWithdrawalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-up-right me-2" style="color: var(--gb-yellow);"></i>Material Withdrawal Slip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2">
                            <span id="viewWdNo">WS-0000</span>
                            <span class="badge bg-success shadow-sm" style="font-size: 0.75rem;">Issued & Released</span>
                        </h4>
                        <div class="text-dark fw-bold mt-1" style="font-size: 1rem;">Project Site: <span id="viewWdProject" class="text-dark">Project Name</span></div>
                        <div class="mt-2 text-muted small">
                            Date Released: <strong id="viewWdDate" class="text-dark">Date</strong><br>
                            Released By: <strong id="viewWdReleaser" class="text-dark">Officer</strong><br>
                            Received By: <strong id="viewWdReceiver" class="text-dark">Receiver</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Released Materials & Quantities:</h6>
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-center">Quantity Issued</th>
                            </tr>
                        </thead>
                        <tbody id="viewWdItemsBody"></tbody>
                    </table>
                </div>

                <div class="mb-3">
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Transaction Notes:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="viewWdRemarks">No remarks provided.</p>
                </div>

                <!-- Signature Over Printed Name Footer -->
                <div class="row text-center mt-4 pt-3 border-top">
                    <div class="col-6">
                        <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                            <div id="viewWdReleaserSigWrap" class="d-none" style="position: relative; margin-bottom: -20px; z-index: 2; pointer-events: none;">
                                <img id="viewWdReleaserSigImg" src="" alt="Releaser Signature" style="max-height: 60px; max-width: 200px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase small position-relative" style="z-index: 1;" id="viewWdReleaserText">-</div>
                        <small class="text-muted text-uppercase fw-bold d-block mt-1" style="font-size: 0.7rem;">Released By (Warehouse Officer)</small>
                    </div>
                    <div class="col-6">
                        <div class="d-flex flex-column align-items-center justify-content-end" style="min-height: 45px;">
                            <div id="viewWdReceiverSigWrap" class="d-none" style="position: relative; margin-bottom: -20px; z-index: 2; pointer-events: none;">
                                <img id="viewWdReceiverSigImg" src="" alt="Recipient Signature" style="max-height: 60px; max-width: 200px; object-fit: contain;">
                            </div>
                        </div>
                        <div class="border-bottom border-dark pb-1 fw-bold text-dark text-uppercase small position-relative" style="z-index: 1;" id="viewWdReceiverText">-</div>
                        <small class="text-muted text-uppercase fw-bold d-block mt-1" style="font-size: 0.7rem;">Received By (Recipient / Requestor)</small>
                    </div>
                </div>
            </div>

            <div class="modal-footer justify-content-end bg-white border-top-0">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW INVENTORY ITEM QUICK DETAILS                  -->
<!-- ======================================================== -->
<div class="modal fade" id="viewItemQuickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2" style="color: var(--gb-yellow);"></i>Inventory Item Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-dark mb-1" id="viewItemName">Item Name</h4>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary fw-bold" id="viewItemCode">ITM-0000</span>
                            <span class="badge bg-secondary" id="viewItemCategory">Category</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted fw-bold text-uppercase">Stock Level</div>
                        <span id="viewItemStatusBadge" class="badge bg-success fs-6 shadow-sm">In Stock</span>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="bg-white p-3 rounded border text-center shadow-sm">
                            <small class="text-muted fw-bold d-block text-uppercase" style="font-size:0.7rem;">Current Quantity</small>
                            <h3 class="fw-bold text-primary mb-0" id="viewItemQty">0</h3>
                            <small class="text-muted fw-semibold" id="viewItemUnit">pcs</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-white p-3 rounded border text-center shadow-sm">
                            <small class="text-muted fw-bold d-block text-uppercase" style="font-size:0.7rem;">Unit Price</small>
                            <h3 class="fw-bold text-success mb-0" id="viewItemPrice">₱0.00</h3>
                            <small class="text-muted fw-semibold">per unit</small>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white fw-bold small text-uppercase text-muted py-2">
                        <i class="bi bi-graph-up-arrow me-1 text-primary"></i> 30-Day Consumption Demand
                    </div>
                    <div class="card-body bg-light py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-dark fw-semibold">Units withdrawn in past 30 days:</span>
                            <strong class="text-primary fs-6" id="viewItem30d">0 units</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-uppercase small text-muted mb-2">Recent Material Releases:</h6>
                <div class="table-responsive rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Slip No</th>
                                <th>Project</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Date</th>
                            </tr>
                        </thead>
                        <tbody id="viewItemRecentBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer justify-content-end bg-white border-top-0">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
