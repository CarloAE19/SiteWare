<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

// Allowed roles for this module
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
    header("Location: index");
    exit;
}
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// AUTO-PATCH DB: Ensures the PO table can handle SMS Status and Weather Delays!
try {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN status VARCHAR(50) DEFAULT 'Generated'");
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN delay_remarks TEXT");
} catch (PDOException $e) { /* Columns already exist */
}

// Fetch Purchase Orders
$query = "
    SELECT p.*, s.company_name, s.contact_number, r.rs_no, r.project_name 
    FROM purchase_orders p 
    LEFT JOIN suppliers s ON p.supplier_id = s.id 
    LEFT JOIN requisitions r ON p.rs_id = r.id 
    ORDER BY p.created_at DESC
";
$pos = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalPO = count($pos);
$pendingDelivery = count(array_filter($pos, fn($p) => in_array($p['status'], ['Generated', 'SMS Sent', 'Pending Delivery'])));
$delayedPO = count(array_filter($pos, fn($p) => strpos($p['status'], 'Delayed') !== false));

include 'layout/header.php';
?>

<!-- Premium Mobile Card Table CSS -->
<style>
    @media (max-width: 767.98px) {
        .table-responsive {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        #poTable {
            display: block;
            width: 100%;
            background: transparent !important;
        }

        #poTable thead {
            display: none;
        }

        #poTable tbody {
            display: block;
            width: 100%;
        }

        #poTable tbody tr {
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        #poTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: right;
            padding: 10px 4px;
            border: none;
            border-bottom: 1px dashed #e9ecef;
            white-space: normal !important;
            word-break: break-word;
        }

        /* Center the Actions button at the bottom of the card */
        #poTable tbody td:last-child {
            border-bottom: none;
            justify-content: center !important;
            gap: 8px;
            padding-top: 16px;
            margin-top: 4px;
            flex-wrap: wrap;
        }

        #poTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
        }

        #poTable tbody td:last-child::before {
            display: none;
        }

        /* Receive Modal Table Mobile Stack */
        #receiveItemsTable {
            white-space: normal !important;
            background: transparent !important;
        }

        #receiveItemsTable thead {
            display: none;
        }

        #receiveItemsTable tbody tr {
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e4e8;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        #receiveItemsTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            text-align: right;
            padding: 10px 4px;
            border: none;
            border-bottom: 1px dashed #e9ecef;
            white-space: normal !important;
            word-break: break-word;
            width: 100%;
        }

        #receiveItemsTable tbody td:last-child {
            border-bottom: none;
            align-items: center;
        }

        #receiveItemsTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            text-align: left;
            padding-right: 15px;
            flex-shrink: 0;
            white-space: nowrap;
        }
</style>

<div class="container-fluid px-3 px-md-4 py-4">

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <!-- PO Stats Cards (Premium Hover Effects applied via existing CSS) -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid var(--gb-blue) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Total Purchase
                            Orders</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalPO ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important; opacity: 0.8;"><i
                            class="bi bi-file-earmark-text-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid var(--gb-yellow) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Pending Deliveries
                        </h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $pendingDelivery ?></h3>
                    </div>
                    <div class="fs-1 text-warning" style="opacity: 0.8;"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3"
                style="border-left: 5px solid #dc3545 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Delayed Orders
                        </h6>
                        <h3 class="mb-0 fw-bold text-danger"><?= $delayedPO ?></h3>
                    </div>
                    <div class="fs-1 text-danger" style="opacity: 0.8;"><i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Datatable Card -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-5 text-center text-xl-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Purchase
                    Orders</h4>
            </div>

            <div class="col-12 col-xl-7">
                <div
                    class="d-flex flex-wrap justify-content-center justify-content-xl-end align-items-center gap-2 w-100">

                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0"
                        style="max-width: 320px; min-width: 200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i
                                class="bi bi-search"></i></span>
                        <input type="text" id="searchPo" class="form-control border-start-0 ps-0 bg-white fw-bold"
                            placeholder="Search PO No or Supplier...">
                    </div>

                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                        <div>
                            <button class="btn btn-brand fw-bold text-nowrap shadow-sm px-4" data-bs-toggle="modal"
                                data-bs-target="#poModal">
                                <i class="bi bi-plus-lg me-1"></i> Create PO
                            </button>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm bg-white">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="poTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">PO Number</th>
                        <th class="py-3">Linked RS / Project</th>
                        <th class="py-3">Supplier</th>
                        <th class="py-3">Status</th>
                        <th class="text-center py-3">Logistics Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pos) > 0): ?>
                        <?php foreach ($pos as $po): ?>
                            <?php
                            $statusClass = 'bg-secondary';
                            if ($po['status'] === 'Generated')
                                $statusClass = 'bg-primary';
                            if ($po['status'] === 'SMS Sent')
                                $statusClass = 'bg-success';
                            if ($po['status'] === 'Pending Delivery')
                                $statusClass = 'bg-secondary';
                            if ($po['status'] === 'Delayed (Weather)')
                                $statusClass = 'bg-danger';
                            if ($po['status'] === 'Delivered')
                                $statusClass = 'bg-info text-dark';
                            ?>
                            <tr class="po-row">
                                <td class="fw-bold text-dark po-no" data-label="PO Number"><?= htmlspecialchars($po['po_no']) ?>
                                </td>

                                <td data-label="Linked RS / Project">
                                    <span class="d-block">
                                        <span
                                            class="badge bg-light text-dark border me-1 shadow-sm"><?= htmlspecialchars($po['rs_no']) ?></span>
                                        <small class="text-muted fw-bold"><?= htmlspecialchars($po['project_name']) ?></small>
                                    </span>
                                </td>

                                <td class="fw-bold text-primary po-supplier" data-label="Supplier">
                                    <span class="d-inline-flex align-items-center">
                                        <i
                                            class="bi bi-building me-2 text-muted"></i><?= htmlspecialchars($po['company_name']) ?>
                                    </span>
                                </td>

                                <td data-label="Status">
                                    <span class="badge <?= $statusClass ?> px-3 py-2 shadow-sm text-uppercase"
                                        id="status_<?= $po['id'] ?>">
                                        <?= htmlspecialchars($po['status'] ?? 'Generated') ?>
                                    </span>
                                    <?php if ($po['status'] === 'Delayed (Weather)'): ?>
                                        <small class="d-block text-danger mt-2 fw-bold"
                                            style="font-size: 0.75rem; white-space: normal;"><i
                                                class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($po['delay_remarks']) ?></small>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center" data-label="Actions">
                                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                        <button class="btn btn-sm btn-outline-success fw-bold shadow-sm me-1"
                                            id="smsBtn_<?= $po['id'] ?>"
                                            onclick="openSmsPreviewModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', <?= (int) $po['supplier_id'] ?>, '<?= $po['contact_number'] ?>')">
                                            <i class="bi bi-chat-text-fill"></i> <span class="d-none d-md-inline ms-1">SMS</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger fw-bold shadow-sm me-1"
                                            onclick="openDelayModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                            <i class="bi bi-cloud-lightning-rain-fill"></i> <span
                                                class="d-none d-md-inline ms-1">Delay</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'warehouse']) && $po['status'] !== 'Delivered' && $po['status'] !== 'Delivered (Discrepancy)'): ?>
                                        <!-- WAREHOUSE ACTION: Receive Order (STOCK IN) -->
                                        <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-1"
                                            onclick="openReceiveModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                            <i class="bi bi-box-arrow-in-down"></i> <span
                                                class="d-none d-md-inline ms-1">Receive</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'management', 'purchasing']) && $po['status'] === 'Delivered (Discrepancy)'): ?>
                                        <!-- VIEW DISCREPANCY BUTTON -->
                                        <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm me-1"
                                            title="View Discrepancy" data-pono="<?= htmlspecialchars($po['po_no']) ?>"
                                            data-remarks="<?= htmlspecialchars($po['delay_remarks'] ?? 'No remarks provided.') ?>"
                                            onclick="viewDiscrepancy(this)">
                                            <i class="bi bi-search"></i> <span class="d-none d-md-inline ms-1">View Issue</span>
                                        </button>
                                    <?php endif; ?>

                                    <button class="btn btn-sm btn-outline-secondary shadow-sm" title="View/Print PO"><i
                                            class="bi bi-printer"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted"><i
                                    class="bi bi-folder-x fs-1 d-block mb-2"></i>No Purchase Orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODALS -->
<?php include 'components/po_modal.php'; ?>

<!-- SPA-PROOF JAVASCRIPT LOGIC -->
<script>
    // ==========================================
    // NEW: FETCH RS ITEMS & SUPPLIER HISTORY
    // ==========================================
    document.addEventListener('DOMContentLoaded', function () {
        const rsSelect = document.getElementById('poRsSelect');
        if (rsSelect) {
            rsSelect.addEventListener('change', async function () {
                const rsId = this.value;
                if (!rsId) return;

                const container = document.getElementById('rsItemsPreviewContainer');
                const tbody = document.getElementById('rsItemsPreviewBody');

                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div> Loading items...</td></tr>';
                container.classList.remove('d-none');

                let formData = new FormData();
                formData.append('action', 'fetch_rs_with_history');
                formData.append('rs_id', rsId);

                try {
                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        tbody.innerHTML = '';
                        if (data.items.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2"><i class="bi bi-info-circle me-1"></i> No items found.</td></tr>';
                            return;
                        }
                        data.items.forEach(item => {
                            const tr = document.createElement('tr');
                            const supplierText = item.last_purchased ?
                                `<span class="text-primary fw-bold" style="font-size: 0.8rem;">${item.last_supplier} <br><small class="text-muted fw-normal">${item.last_purchased}</small></span>` :
                                `${item.last_supplier}`;

                            tr.innerHTML = `
                            <td class="fw-bold text-dark text-wrap">${item.item_name}</td>
                            <td class="text-center fw-bold text-danger">${item.quantity}</td>
                            <td>${supplierText}</td>
                        `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-2">Error: ${data.message}</td></tr>`;
                    }
                } catch (e) {
                    tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-2">Network Error: Could not fetch RS items.</td></tr>`;
                }
            });
        }
    });

    // ==========================================
    // NEW: RECEIVE MODAL LOGIC (Discrepancy Checks)
    // ==========================================
    window.openReceiveModal = async function (id, po_no) {
        document.getElementById('receivePoId').value = id;
        document.getElementById('receivePoNo').value = po_no;

        const tbody = document.getElementById('receiveItemsBody');
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><div class="spinner-border text-success spinner-border-sm me-2"></div> Fetching Manifest...</td></tr>';

        var myModalEl = document.getElementById('receiveModal');
        var receiveModal = bootstrap.Modal.getInstance(myModalEl);
        if (!receiveModal) receiveModal = new bootstrap.Modal(myModalEl);
        receiveModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_items');
        formData.append('po_id', id);

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No items linked to this manifest.</td></tr>';
                    document.getElementById('confirmReceiveBtn').disabled = true;
                    return;
                }

                document.getElementById('confirmReceiveBtn').disabled = false;

                data.items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                    <td class="fw-bold text-muted" style="font-size: 0.8rem;" data-label="Item Code">
                        ${item.item_code}
                        <input type="hidden" name="item_codes[]" value="${item.item_code}">
                        <input type="hidden" name="expected_qtys[]" value="${item.expected_qty}">
                    </td>
                    <td class="fw-bold text-dark text-wrap" data-label="Item Name">${item.item_name}</td>
                    <td class="text-center fw-bold text-primary fs-6" data-label="Expected Qty">${item.expected_qty}</td>
                    <td class="text-center align-middle" data-label="Actual Received">
                        <input type="number" name="actual_qtys[]" class="form-control form-control-sm text-center fw-bold text-success border-success shadow-sm ms-auto" 
                            style="max-width: 90px; font-size: 1.1rem; height: 35px;" value="${item.expected_qty}" min="0" onclick="this.select()" onfocus="this.select()" required>
                    </td>
                `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">Error: ${data.message}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger py-3">Network Error: Could not load the manifest.</td></tr>`;
        }
    }

    window.viewDiscrepancy = function (btnElem) {
        document.getElementById('discPoNo').innerText = btnElem.getAttribute('data-pono');

        let rawText = btnElem.getAttribute('data-remarks');
        rawText = rawText.replace(/\[DELIVERY DISCREPANCY\]:/g, '<span class="d-block text-danger fw-bold mb-1 border-bottom border-danger border-opacity-25 pb-2"><i class="bi bi-x-circle-fill me-1"></i> DELIVERY DISCREPANCY ISSUES</span>');
        document.getElementById('discRemarks').innerHTML = rawText;

        var myModalEl = document.getElementById('discrepancyModal');
        var discModal = bootstrap.Modal.getInstance(myModalEl);
        if (!discModal) discModal = new bootstrap.Modal(myModalEl);
        discModal.show();
    }

    // SPA Fix: Attach search listener globally so it never breaks on page transitions
    window.initPoSearch = function () {
        const searchPo = document.getElementById('searchPo');
        if (searchPo) {
            searchPo.onkeyup = function (e) {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.po-row').forEach(row => {
                    const no = row.querySelector('.po-no').textContent.toLowerCase();
                    const sup = row.querySelector('.po-supplier').textContent.toLowerCase();
                    row.style.display = (no.includes(term) || sup.includes(term)) ? '' : 'none';
                });
            };
        }
    };
    // Initialize immediately
    window.initPoSearch();

    // Make sure openSmsPreviewModal is attached to window for SPA compatibility
    window.openSmsPreviewModal = async function (poId, poNo, supplierId, phone) {
        document.getElementById('smsPoId').value = poId;
        document.getElementById('smsPoNo').value = poNo;
        document.getElementById('smsPhone').value = phone || '';

        const supplierSelect = document.getElementById('smsSupplierSelect');
        if (supplierSelect) {
            supplierSelect.value = supplierId;
        }

        const tbody = document.getElementById('smsItemsBody');
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div> Loading items...</td></tr>';
        document.getElementById('smsMessageText').value = 'Loading SMS message template...';

        var myModalEl = document.getElementById('smsPreviewModal');
        var smsModal = bootstrap.Modal.getInstance(myModalEl);
        if (!smsModal) smsModal = new bootstrap.Modal(myModalEl);
        smsModal.show();

        let formData = new FormData();
        formData.append('action', 'fetch_po_sms_preview');
        formData.append('po_id', poId);

        try {
            const response = await fetch('process/process.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-2">No items found.</td></tr>';
                } else {
                    data.items.forEach(item => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="fw-bold text-dark text-wrap">${item.item_name}</td>
                            <td class="text-center fw-bold text-danger">${item.quantity}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                const msg = `Genetian Builders Construction PO: ${poNo}\nItems to purchase:\n${data.item_list}If you have any concerns or clarifications text or email here`;
                document.getElementById('smsMessageText').value = msg;

                // Load recent SMS history for this supplier / PO
                loadPoSmsConversation(poId, phone, supplierId);
            } else {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-2">Failed to load items.</td></tr>';
                document.getElementById('smsMessageText').value = 'Error loading items template.';
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-2">Network error.</td></tr>';
            document.getElementById('smsMessageText').value = 'Network error loading template.';
        }
    };

    async function loadPoSmsConversation(poId, phone, supplierId) {
        const section = document.getElementById('smsPoConversationSection');
        const container = document.getElementById('smsPoConversationThread');
        if (!section || !container) return;

        section.classList.remove('d-none');
        container.innerHTML = '<div class="text-center text-muted py-2"><span class="spinner-border spinner-border-sm me-2"></span>Loading SMS thread...</div>';

        try {
            const formData = new FormData();
            formData.append('action', 'fetch_sms_messages');
            formData.append('po_id', poId);
            if (phone) formData.append('sender_number', phone);
            if (supplierId) formData.append('supplier_id', supplierId);

            const res = await fetch('process/process.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.status === 'success' && data.messages && data.messages.length > 0) {
                let html = '';
                data.messages.forEach(m => {
                    const isInbound = m.direction === 'inbound';
                    const badge = isInbound ? '<span class="badge bg-primary me-1">Supplier Reply</span>' : '<span class="badge bg-success me-1">Sent PO SMS</span>';
                    html += `
                        <div class="mb-2 pb-2 border-bottom">
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>${badge} <strong>${isInbound ? (m.company_name || m.sender_number) : 'CIMS'}</strong></span>
                                <span>${m.created_at}</span>
                            </div>
                            <div class="text-dark">${escapeSmsHtml(m.message_text)}</div>
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="text-muted text-center py-2">No previous SMS replies from this supplier yet.</div>';
            }
        } catch (err) {
            container.innerHTML = '<div class="text-muted text-center py-2">Could not load conversation history.</div>';
        }
    }

    // Attach form and select change listeners
    document.addEventListener('DOMContentLoaded', function () {
        const smsForm = document.getElementById('smsPreviewForm');
        if (smsForm) {
            smsForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const poId = document.getElementById('smsPoId').value;
                const poNo = document.getElementById('smsPoNo').value;
                const phone = document.getElementById('smsPhone').value;
                const supplierId = document.getElementById('smsSupplierSelect').value;
                const message = document.getElementById('smsMessageText').value;

                if (!phone || phone.trim() === '') {
                    alert("Please specify a valid recipient phone number.");
                    return;
                }

                const submitBtn = document.getElementById('sendSmsSubmitBtn');
                const originalHtml = submitBtn.innerHTML;

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';

                const tableBtn = document.getElementById('smsBtn_' + poId);
                let tableBtnHtml = '';
                if (tableBtn) {
                    tableBtnHtml = tableBtn.innerHTML;
                    tableBtn.disabled = true;
                    tableBtn.classList.replace('btn-outline-success', 'btn-success');
                    tableBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }

                let formData = new FormData();
                formData.append('action', 'send_po_sms');
                formData.append('po_id', poId);
                formData.append('po_no', poNo);
                formData.append('supplier_id', supplierId);
                formData.append('contact_number', phone);
                formData.append('message', message);

                try {
                    const response = await fetch('process/process.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.status === 'success') {
                        new Audio('assets/sounds/success.mp3').play().catch(e => { });

                        var myModalEl = document.getElementById('smsPreviewModal');
                        var smsModal = bootstrap.Modal.getInstance(myModalEl);
                        if (smsModal) smsModal.hide();

                        alert("SMS sent successfully to supplier!");

                        if (tableBtn) {
                            tableBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                            tableBtn.disabled = false;
                        }
                        const statusBadge = document.getElementById('status_' + poId);
                        if (statusBadge) {
                            statusBadge.className = 'badge bg-success px-3 py-2 shadow-sm text-uppercase';
                            statusBadge.innerText = 'SMS Sent';
                        }

                        window.location.reload();
                    } else {
                        alert("Error sending SMS: " + data.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        if (tableBtn) {
                            tableBtn.disabled = false;
                            tableBtn.innerHTML = tableBtnHtml;
                            tableBtn.classList.replace('btn-success', 'btn-outline-success');
                        }
                    }
                } catch (err) {
                    alert("Network Error: Could not connect to SMS Gateway.");
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                    if (tableBtn) {
                        tableBtn.disabled = false;
                        tableBtn.innerHTML = tableBtnHtml;
                        tableBtn.classList.replace('btn-success', 'btn-outline-success');
                    }
                }
            });
        }

        const smsSupplierSelect = document.getElementById('smsSupplierSelect');
        if (smsSupplierSelect) {
            smsSupplierSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const phone = selectedOption.getAttribute('data-phone');
                document.getElementById('smsPhone').value = phone || '';
            });
        }
    });
</script>

<?php include 'layout/footer.php'; ?>