<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }

// FIXED: Added 'warehouse' to the allowed roles so they can receive deliveries!
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

include 'layout/header.php';
?>

<div class="container-fluid px-4 py-4">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="table-container shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Purchase Orders</h4>
                <small class="text-muted">Manage procurement, track deliveries, and log logistics delays.</small>
            </div>
            <?php if (in_array($role, ['admin', 'purchasing'])): ?>
            <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#poModal">
                <i class="bi bi-plus-circle me-1"></i> Create New PO
            </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>PO Number</th>
                        <th>Linked RS / Project</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th class="text-end">Logistics Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pos as $po): ?>
                        <?php 
                            // Status Badge Logic (Updated to handle all statuses)
                            $statusClass = 'bg-secondary';
                            if ($po['status'] === 'Generated') $statusClass = 'bg-primary';
                            if ($po['status'] === 'SMS Sent') $statusClass = 'bg-success';
                            if ($po['status'] === 'Pending Delivery') $statusClass = 'bg-secondary';
                            if ($po['status'] === 'Delayed (Weather)') $statusClass = 'bg-danger';
                            if ($po['status'] === 'Delivered') $statusClass = 'bg-info text-dark';
                        ?>
                        <tr>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($po['po_no']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($po['rs_no']) ?></span>
                                <small class="text-muted fw-bold"><?= htmlspecialchars($po['project_name']) ?></small>
                            </td>
                            <td class="fw-bold text-primary">
                                <i class="bi bi-building me-1 text-muted"></i><?= htmlspecialchars($po['company_name']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusClass ?> px-2 py-1" id="status_<?= $po['id'] ?>">
                                    <?= htmlspecialchars($po['status'] ?? 'Generated') ?>
                                </span>
                                <?php if ($po['status'] === 'Delayed (Weather)'): ?>
                                    <small class="d-block text-danger mt-1" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= htmlspecialchars($po['delay_remarks']) ?></small>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-end">
                                <?php if (in_array($role, ['admin', 'purchasing'])): ?>
                                    <!-- PURCHASING ACTIONS -->
                                    <button class="btn btn-sm btn-outline-success fw-bold me-1" id="smsBtn_<?= $po['id'] ?>" onclick="sendSmsBlaster(<?= $po['id'] ?>, '<?= $po['po_no'] ?>', '<?= addslashes($po['company_name']) ?>', '<?= $po['contact_number'] ?>')">
                                        <i class="bi bi-chat-text-fill me-1"></i> SMS
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger fw-bold me-1" onclick="openDelayModal(<?= $po['id'] ?>, '<?= $po['po_no'] ?>')">
                                        <i class="bi bi-cloud-lightning-rain-fill me-1"></i> Delay
                                    </button>
                                <?php endif; ?>

                                <?php if (in_array($role, ['admin', 'warehouse']) && $po['status'] !== 'Delivered'): ?>
                                    <!-- WAREHOUSE ACTION: Receive Order -->
                                    <form method="POST" action="process/process.php" class="d-inline" onsubmit="return confirm('Confirm that the truck has arrived and items are received at the warehouse?');">
                                        <input type="hidden" name="action" value="mark_po_delivered">
                                        <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                        <input type="hidden" name="po_no" value="<?= $po['po_no'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success fw-bold me-1">
                                            <i class="bi bi-box-seam me-1"></i> Receive Order
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-secondary" title="View/Print PO"><i class="bi bi-printer"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EXTERNAL MODALS -->
<?php include 'components/po_modal.php'; ?>

<!-- AJAX LOGIC FOR SMS BLASTER -->
<script>
async function sendSmsBlaster(poId, poNo, company, phone) {
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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

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
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Sent!';
            const statusBadge = document.getElementById('status_' + poId);
            statusBadge.className = 'badge bg-success px-2 py-1';
            statusBadge.innerText = 'SMS Sent';
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