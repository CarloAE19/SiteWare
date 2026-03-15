<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];
$inventory = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$audits = $pdo->query("SELECT a.*, u.name as auditor_name FROM inventory_audits a LEFT JOIN users u ON a.conducted_by = u.id ORDER BY a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$auditItemsData = $pdo->query("SELECT ai.*, i.item_name, i.unit FROM audit_items ai LEFT JOIN inventory i ON ai.item_code = i.item_code")->fetchAll(PDO::FETCH_ASSOC);
$groupedAuditItems = [];
foreach($auditItemsData as $item) { $groupedAuditItems[$item['audit_id']][] = $item; }

include 'layout/header.php';
?>

<!-- Premium Mobile Card Table CSS -->
<style>
    /* =============================================
       HISTORY TABLE — Label+Value Card Layout
       ============================================= */
    @media (max-width: 767.98px) {
        .table-responsive {
            overflow-x: hidden !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        /* --- History Table --- */
        #historyTable { display: block; width: 100%; background: transparent !important; }
        #historyTable thead { display: none; }
        #historyTable tbody { display: block; width: 100%; }

        #historyTable tbody tr {
            display: flex;
            flex-direction: column;
            border: none;
            border-radius: 16px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Audit Month — highlighted header row */
        #historyTable tbody td[data-label="Audit Month"] {
            display: block;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff !important;
            font-size: 1.05rem;
            font-weight: 800;
            padding: 12px 16px;
            border: none;
            text-align: left;
        }
        #historyTable tbody td[data-label="Audit Month"]::before { display: none; }

        /* Remaining rows */
        #historyTable tbody td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border: none;
            border-bottom: 1px solid #f0f0f0;
            text-align: right;
            white-space: normal !important;
            word-break: break-word;
        }
        #historyTable tbody td::before {
            content: attr(data-label);
            font-weight: 700;
            font-size: 0.72rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            text-align: left;
            flex-shrink: 0;
            padding-right: 12px;
        }

        /* Actions cell */
        #historyTable tbody td[data-label="Actions"] {
            border-bottom: none;
            justify-content: center !important;
            padding: 14px 16px;
        }
        #historyTable tbody td[data-label="Actions"]::before { display: none; }
        #historyTable tbody td[data-label="Actions"] .btn {
            width: 100%;
            justify-content: center;
        }

        /* =============================================
           RECOUNT TABLE — Inventory Item Card
           ============================================= */
        #recountTable { display: block; width: 100%; background: transparent !important; }
        #recountTable thead { display: none; }
        #recountTable tbody { display: block; width: 100%; }

        #recountTable tbody tr {
            display: flex;
            flex-wrap: wrap;
            border: none;
            border-radius: 16px;
            margin-bottom: 1rem;
            background: #fff;
            padding: 0;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Visual reorder: Item → System|Discrepancy → Physical Input */
        #recountTable tbody td[data-label="Item Name"]    { order: 1; }
        #recountTable tbody td[data-label="System Record"] { order: 2; }
        #recountTable tbody td[data-label="Physical Count"] { order: 4; }
        #recountTable tbody td[data-label="Discrepancy"]  { order: 3; }

        /* Item Name — full-width header */
        #recountTable tbody td[data-label="Item Name"] {
            flex: 0 0 100%;
            display: block;
            background: #212529;
            color: #fff !important;
            padding: 12px 16px;
            border: none;
            text-align: left;
        }
        #recountTable tbody td[data-label="Item Name"]::before { display: none; }
        #recountTable tbody td[data-label="Item Name"] .text-end { text-align: left !important; }

        /* System Record — left half */
        #recountTable tbody td[data-label="System Record"] {
            flex: 0 0 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 10px;
            background: #f8f9fa;
            border: none;
            border-right: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
        }
        #recountTable tbody td[data-label="System Record"]::before {
            content: "📋 System Qty";
            font-weight: 700;
            font-size: 0.68rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: block;
        }
        #recountTable tbody td[data-label="System Record"] .text-end { text-align: center !important; }

        /* Discrepancy — right half (moved alongside System Record) */
        #recountTable tbody td[data-label="Discrepancy"] {
            flex: 0 0 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px 10px;
            border: none;
            border-bottom: 1px solid #e9ecef;
            text-align: center;
        }
        #recountTable tbody td[data-label="Discrepancy"]::before {
            content: "⚖️ Difference";
            font-weight: 700;
            font-size: 0.68rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: block;
        }
        #recountTable tbody td[data-label="Discrepancy"] .badge {
            width: 100%;
            font-size: 0.78rem !important;
        }

        /* Physical Count input — full width at bottom */
        #recountTable tbody td[data-label="Physical Count"] {
            flex: 0 0 100%;
            display: block;
            padding: 14px 16px;
            border: none;
            text-align: center;
        }
        #recountTable tbody td[data-label="Physical Count"]::before {
            content: "✏️ Enter Physical Count";
            display: block;
            font-weight: 700;
            font-size: 0.72rem;
            color: #0d6efd;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
            text-align: left;
        }
        #recountTable tbody td[data-label="Physical Count"] .input-group {
            min-width: unset !important;
            max-width: 100% !important;
            margin: 0 !important;
        }
        #recountTable tbody td[data-label="Physical Count"] .form-control {
            font-size: 1.3rem !important;
        }
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

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-primary"></i>Weekly Physical Recount</h4>
        </div>
    </div>

    <!-- Premium Styled Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm border d-inline-flex" id="auditTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold px-4" id="history-tab" data-bs-toggle="pill" data-bs-target="#history" type="button" role="tab">
                <i class="bi bi-clock-history me-1"></i> Audit History <span class="d-none d-sm-inline">(Weekly Logs)</span>
            </button>
        </li>
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold px-4" id="recount-tab" data-bs-toggle="pill" data-bs-target="#recount" type="button" role="tab">
                <i class="bi bi-calculator me-1"></i> Perform <span class="d-none d-sm-inline">Physical</span> Count
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content" id="auditTabsContent">
        <!-- ==========================================
          TAB 1: AUDIT HISTORY
        =========================================== -->
        <div class="tab-pane fade show active" id="history" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-0 table-responsive border rounded bg-white">
                    <table class="table table-hover align-middle mb-0 text-nowrap" id="historyTable">
                        <thead class="table-dark">
                            <tr>
                                <th class="py-3 px-3">Audit Month</th>
                                <th class="py-3">Conducted By</th>
                                <th class="py-3">Date Completed</th>
                                <th class="py-3">Discrepancies</th>
                                <th class="text-center py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($audits) > 0): ?>
                                <?php foreach ($audits as $audit): ?>
                                    <tr>
                                        <td class="fw-bold text-primary px-3" data-label="Audit Month"><?= htmlspecialchars($audit['audit_month']) ?></td>
                                        
                                        <!-- FIX: Added d-inline-flex to lock the icon and text together -->
                                        <td data-label="Conducted By">
                                            <span class="d-inline-flex align-items-center text-dark fw-bold">
                                                <i class="bi bi-person-badge me-2 text-muted"></i><?= htmlspecialchars($audit['auditor_name']) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="text-muted fw-bold small" data-label="Date Completed"><?= date('M d, Y h:i A', strtotime($audit['created_at'])) ?></td>
                                        <td data-label="Discrepancies">
                                            <?php if($audit['total_discrepancy_items'] > 0): ?>
                                                <span class="badge bg-danger shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $audit['total_discrepancy_items'] ?> Items Adjusted</span>
                                            <?php else: ?>
                                                <span class="badge bg-success shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-check-circle-fill me-1"></i>Match</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" data-label="Actions">
                                            <?php $itemsJson = htmlspecialchars(json_encode($groupedAuditItems[$audit['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                            <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm" onclick="viewAuditDetails('<?= $audit['audit_month'] ?>', '<?= addslashes($audit['remarks']) ?>', '<?= $itemsJson ?>')"><i class="bi bi-eye"></i> View Trail</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No audit history found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==========================================
          TAB 2: PERFORM RECOUNT
        =========================================== -->
        <?php if (in_array($role, ['warehouse', 'admin'])): ?>
        <div class="tab-pane fade" id="recount" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body bg-light p-3 p-md-4">
                    
                    <div class="alert alert-warning px-3 py-2 mb-4 shadow-sm" style="border-left: 4px solid #ffc107;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Warning:</strong> Submitting this form will automatically overwrite the main inventory with your weekly physical counts.
                    </div>

                    <form method="POST" action="process/process.php" onsubmit="return confirm('CRITICAL: This will overwrite the system inventory with this week\'s physical count. Ensure all entries are correct before submitting.');">
                        <input type="hidden" name="action" value="submit_audit">
                        
                        <div class="table-responsive bg-white border rounded shadow-sm">
                            <table class="table table-hover align-middle mb-0 text-nowrap" id="recountTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="min-width: 200px;" class="py-3 px-3">Item Name</th>
                                        <th class="text-center py-3">System Record</th>
                                        <th class="text-center py-3" style="min-width: 220px;">Physical Count (This Week)</th>
                                        <th class="text-center py-3" style="min-width: 140px;">Discrepancy (+/-)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory as $i => $item): ?>
                                        <tr>
                                            <td class="px-3" data-label="Item Name">
                                                <div class="text-end text-md-start">
                                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($item['item_name']) ?></span>
                                                    <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;"><?= $item['item_code'] ?></small>
                                                </div>
                                                <input type="hidden" name="item_code[]" value="<?= $item['item_code'] ?>">
                                            </td>
                                            
                                            <td class="text-center bg-light" data-label="System Record">
                                                <div class="text-end text-md-center">
                                                    <span class="fs-5 fw-bold text-secondary" id="sysQty_<?= $i ?>"><?= $item['quantity'] ?></span> 
                                                    <small class="text-muted d-block" style="font-size: 0.75rem;"><?= $item['unit'] ?></small>
                                                </div>
                                                <input type="hidden" name="system_qty[]" value="<?= $item['quantity'] ?>">
                                            </td>
                                            
                                            <td data-label="Physical Count">
                                                <div class="input-group shadow-sm ms-auto mx-md-auto" style="min-width: 180px; max-width: 250px;">
                                                    <input type="number" class="form-control text-center fw-bold fs-5 text-primary phys-input" 
                                                           name="physical_qty[]" data-index="<?= $i ?>" value="<?= $item['quantity'] ?>" 
                                                           required min="0" style="min-width: 70px;">
                                                    <span class="input-group-text bg-light text-muted fw-bold"><?= $item['unit'] ?></span>
                                                </div>
                                            </td>
                                            
                                            <td class="text-center" data-label="Discrepancy">
                                                <span class="badge bg-success fs-6 w-100 py-2 shadow-sm text-uppercase diff-badge" id="diff_<?= $i ?>">
                                                    <i class="bi bi-check-circle me-1"></i> Match
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4 mb-3 p-3 bg-white border rounded shadow-sm">
                            <label class="form-label fw-bold small text-muted text-uppercase">Audit Remarks / Notes</label>
                            <textarea class="form-control fw-bold bg-light border-0" name="remarks" rows="2" placeholder="Explain any missing items or damaged goods found during the recount..."></textarea>
                        </div>
                        
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-danger btn-lg w-100 w-md-auto px-5 fw-bold shadow-lg text-uppercase" style="letter-spacing: 1px;">
                                <i class="bi bi-save me-2"></i>Finalize Audit & Adjust Inventory
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- EXTERNAL MODAL -->
<?php include 'components/audit_modal.php'; ?>

<!-- SPA-PROOF LOGIC & REAL-TIME DISCREPANCY CALCULATOR -->
<script>
// 1. Pagination Logic
window.initAuditPagination = function() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;
        if (table.parentElement.querySelector('.pagination-wrapper')) return;

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
        if (rows.length <= rowsPerPage) return;

        let currentPage = 1;
        const totalPages = Math.ceil(rows.length / rowsPerPage);

        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper gap-2';
        
        const infoText = document.createElement('span');
        infoText.className = 'text-muted small fw-bold';
        
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group shadow-sm';

        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        prevBtn.type = 'button';
        prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';

        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
        pageIndicator.type = 'button';
        
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button';
        nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';

        btnGroup.appendChild(prevBtn);
        btnGroup.appendChild(pageIndicator);
        btnGroup.appendChild(nextBtn);
        
        paginationWrapper.appendChild(infoText);
        paginationWrapper.appendChild(btnGroup);

        table.parentElement.appendChild(paginationWrapper);

        function showPage(page) {
            currentPage = page;
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            rows.forEach((row, index) => {
                row.style.display = (index >= start && index < end) ? '' : 'none';
            });

            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b> of <b>${rows.length}</b> entries`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            
            prevBtn.disabled = page === 1;
            nextBtn.disabled = page === totalPages;
        }

        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });

        showPage(1);
    }
    setupPagination('historyTable', 10);
    setupPagination('recountTable', 10);
}

// 2. Real-Time Discrepancy Calculator
window.initDiscrepancyCalculator = function() {
    document.querySelectorAll('.phys-input').forEach(input => {
        // Remove old listeners to prevent double-firing in SPA
        const new_input = input.cloneNode(true);
        input.parentNode.replaceChild(new_input, input);
        
        new_input.addEventListener('input', function() {
            let index = this.getAttribute('data-index');
            let sysQty = parseInt(document.getElementById('sysQty_' + index).innerText) || 0;
            let physQty = parseInt(this.value) || 0;
            let diff = physQty - sysQty;
            let badge = document.getElementById('diff_' + index);

            if (diff === 0) {
                badge.className = 'badge bg-success fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-check-circle me-1"></i> Match';
            } else if (diff > 0) {
                badge.className = 'badge bg-warning text-dark fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i> +' + diff + ' Over';
            } else {
                badge.className = 'badge bg-danger fs-6 w-100 py-2 shadow-sm text-uppercase';
                badge.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i> ' + diff + ' Short';
            }
        });
        
        // Trigger once to initialize
        new_input.dispatchEvent(new Event('input'));
    });
};

// Initialize Everything
window.initAuditPagination();
window.initDiscrepancyCalculator();
</script>

<?php include 'layout/footer.php'; ?>