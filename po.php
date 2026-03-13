<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }

// Allowed roles for this module
if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) { header("Location: index"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// AUTO-PATCH DB: Ensures the PO table can handle SMS Status and Weather Delays!
try {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN status VARCHAR(50) DEFAULT 'Generated'");
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN delay_remarks TEXT");
} catch (PDOException $e) { /* Columns already exist */ }

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
        .table-responsive { overflow-x: hidden !important; border: none !important; box-shadow: none !important; background: transparent !important; }
        #poTable { display: block; width: 100%; background: transparent !important; }
        #poTable thead { display: none; }
        #poTable tbody { display: block; width: 100%; }
        
        #poTable tbody tr { 
            display: flex; flex-direction: column; border: 1px solid #e0e4e8; border-radius: 12px; 
            margin-bottom: 1rem; background: #fff; padding: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); 
        }
        
        #poTable tbody td { 
            display: flex; justify-content: space-between; align-items: center; text-align: right; 
            padding: 10px 4px; border: none; border-bottom: 1px dashed #e9ecef; white-space: normal !important; word-break: break-word; 
        }
        
        /* Center the Actions button at the bottom of the card */
        #poTable tbody td:last-child { 
            border-bottom: none; justify-content: center !important; gap: 8px; padding-top: 16px; margin-top: 4px; flex-wrap: wrap;
        }
        
        #poTable tbody td::before { 
            content: attr(data-label); font-weight: 700; font-size: 0.75rem; color: #6c757d; 
            text-transform: uppercase; text-align: left; padding-right: 15px; flex-shrink: 0; 
        }
        
        #poTable tbody td:last-child::before { display: none; }
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
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3" style="border-left: 5px solid var(--gb-blue) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Total Active POs</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $totalPO ?></h3>
                    </div>
                    <div class="fs-1 text-primary" style="color: var(--gb-blue) !important; opacity: 0.8;"><i class="bi bi-file-earmark-text-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3" style="border-left: 5px solid var(--gb-yellow) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Pending Deliveries</h6>
                        <h3 class="mb-0 fw-bold text-dark"><?= $pendingDelivery ?></h3>
                    </div>
                    <div class="fs-1 text-warning" style="opacity: 0.8;"><i class="bi bi-truck"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card bg-white h-100 p-3 shadow-sm border-0 rounded-3" style="border-left: 5px solid #dc3545 !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size:0.75rem;">Delayed Orders</h6>
                        <h3 class="mb-0 fw-bold text-danger"><?= $delayedPO ?></h3>
                    </div>
                    <div class="fs-1 text-danger" style="opacity: 0.8;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Datatable Card -->
    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white rounded-3">
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-5 text-center text-xl-start">
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Purchase Orders</h4>
                <small class="text-muted">Manage procurement, track deliveries, and process Stock In.</small>
            </div>
            
            <div class="col-12 col-xl-7">
                <div class="d-flex flex-wrap justify-content-center justify-content-xl-end align-items-center gap-2 w-100">
                    
                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 320px; min-width: 200px;">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchPo" class="form-control border-start-0 ps-0 bg-white fw-bold" placeholder="Search PO No or Supplier...">
                    </div>
                    
                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                    <div>
                        <button class="btn btn-brand fw-bold text-nowrap shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#poModal">
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
                    <?php if(count($pos) > 0): ?>
                        <?php foreach ($pos as $po): ?>
                            <?php 
                                $statusClass = 'bg-secondary';
                                if ($po['status'] === 'Generated') $statusClass = 'bg-primary';
                                if ($po['status'] === 'SMS Sent') $statusClass = 'bg-success';
                                if ($po['status'] === 'Pending Delivery') $statusClass = 'bg-secondary';
                                if ($po['status'] === 'Delayed (Weather)') $statusClass = 'bg-danger';
                                if ($po['status'] === 'Delivered') $statusClass = 'bg-info text-dark';
                            ?>
                            <tr class="po-row">
                                <td class="fw-bold text-dark po-no" data-label="PO Number"><?= htmlspecialchars($po['po_no']) ?></td>
                                
                                <td data-label="Linked RS / Project">
                                    <span class="d-block">
                                        <span class="badge bg-light text-dark border me-1 shadow-sm"><?= htmlspecialchars($po['rs_no']) ?></span>
                                        <small class="text-muted fw-bold"><?= htmlspecialchars($po['project_name']) ?></small>
                                    </span>
                                </td>
                                
                                <td class="fw-bold text-primary po-supplier" data-label="Supplier">
                                    <span class="d-inline-flex align-items-center">
                                        <i class="bi bi-building me-2 text-muted"></i><?= htmlspecialchars($po['company_name']) ?>
                                    </span>
                                </td>
                                
                                <td data-label="Status">
                                    <span class="badge <?= $statusClass ?> px-3 py-2 shadow-sm text-uppercase" id="status_<?= $po['id'] ?>">
                                        <?= htmlspecialchars($po['status'] ?? 'Generated') ?>
                                    </span>
                                    <?php if ($po['status'] === 'Delayed (Weather)'): ?>
                                        <small class="d-block text-danger mt-2 fw-bold" style="font-size: 0.75rem; white-space: normal;"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($po['delay_remarks']) ?></small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center" data-label="Actions">
                                    <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                        <button class="btn btn-sm btn-outline-success fw-bold shadow-sm me-1" id="smsBtn_<?= $po['id'] ?>" onclick="sendSmsBlaster(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', '<?= addslashes($po['company_name']) ?>', '<?= $po['contact_number'] ?>')">
                                            <i class="bi bi-chat-text-fill"></i> <span class="d-none d-md-inline ms-1">SMS</span>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger fw-bold shadow-sm me-1" onclick="openDelayModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                            <i class="bi bi-cloud-lightning-rain-fill"></i> <span class="d-none d-md-inline ms-1">Delay</span>
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($role, ['admin', 'warehouse']) && $po['status'] !== 'Delivered'): ?>
                                        <!-- WAREHOUSE ACTION: Receive Order (STOCK IN) -->
                                        <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Confirm Stock In? This will automatically add all items from this Purchase Order into the Master Inventory.');">
                                            <input type="hidden" name="action" value="mark_po_delivered">
                                            <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                            <input type="hidden" name="po_no" value="<?= $po['po_no'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success fw-bold shadow-sm me-1">
                                                <i class="bi bi-box-arrow-in-down"></i> <span class="d-none d-md-inline ms-1">Receive</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-outline-secondary shadow-sm" title="View/Print PO"><i class="bi bi-printer"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No Purchase Orders found.</td></tr>
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
// SPA Fix: Attach search listener globally so it never breaks on page transitions
window.initPoSearch = function() {
    const searchPo = document.getElementById('searchPo');
    if(searchPo) {
        searchPo.onkeyup = function(e) {
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

// Make sure SMS Blaster is attached to window for SPA compatibility
window.sendSmsBlaster = async function(poId, poNo, company, phone) {
    if (!phone || phone.trim() === '') {
        alert("Cannot send SMS: " + company + " does not have a registered phone number in the system.");
        return;
    }

    if (!confirm("Are you sure you want to send an automated SMS order notification to " + company + " (" + phone + ")?")) {
        return;
    }

    const btn = document.getElementById('smsBtn_' + poId);
    const originalHtml = btn.innerHTML;
    
    btn.disabled = true;
    btn.classList.replace('btn-outline-success', 'btn-success');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    let formData = new FormData();
    formData.append('action', 'send_po_sms');
    formData.append('po_id', poId);
    formData.append('po_no', poNo);
    formData.append('company', company);

    try {
        const response = await fetch('process/process.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.status === 'success') {
            new Audio('assets/sounds/success.mp3').play().catch(e => {});
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            const statusBadge = document.getElementById('status_' + poId);
            if(statusBadge) {
                statusBadge.className = 'badge bg-success px-3 py-2 shadow-sm text-uppercase';
                statusBadge.innerText = 'SMS Sent';
            }
        } else {
            alert("Error sending SMS: " + data.message);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            btn.classList.replace('btn-success', 'btn-outline-success');
        }
    } catch (e) {
        alert("Network Error: Could not connect to SMS Gateway.");
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        btn.classList.replace('btn-success', 'btn-outline-success');
    }
}
</script>

<?php include 'layout/footer.php'; ?>