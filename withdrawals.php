<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
require_once 'Connection/db.php';

$role = $_SESSION['user_role'];

// Fetch all Withdrawals
$query = "SELECT w.*, u.name as releaser_name, r.requestor_name 
          FROM withdrawals w
          LEFT JOIN users u ON w.released_by = u.id
          LEFT JOIN requisitions r ON (w.remarks LIKE CONCAT('%', r.rs_no, '%') AND r.rs_no != '')
          ORDER BY w.date_withdrawn DESC";
$withdrawals = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$itemsQuery = $pdo->query("SELECT wi.withdrawal_id, wi.quantity, wi.item_code, i.item_name, i.unit 
                           FROM withdrawal_items wi 
                           LEFT JOIN inventory i ON wi.item_code = i.item_code");
$allItems = $itemsQuery->fetchAll(PDO::FETCH_ASSOC);
$wdItemsGrouped = [];
foreach($allItems as $item) {
    $wdItemsGrouped[$item['withdrawal_id']][] = $item;
}

// Fetch Inventory items for the dropdown
$inventoryItems = $pdo->query("SELECT item_code, item_name, quantity, unit FROM inventory WHERE status != 'Out of Stock' AND quantity > 0 ORDER BY item_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Projects
$activeProjects = $pdo->query("SELECT project_name FROM projects WHERE status = 'active' ORDER BY project_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Approved/Staged RS list for Manual Lookup
$approvedRSList = $pdo->query("SELECT rs_no, project_name, requestor_name, status FROM requisitions WHERE status IN ('Approved', 'PO Created', 'Staged (Ready for Pickup)') ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'layout/header.php';
?>

<!-- Import External Styles -->
<link rel="stylesheet" href="assets/css/withdrawals.css">
<!-- IMPORT CAMERA SCANNER LIBRARY -->
<script src="https://unpkg.com/html5-qrcode"></script>

<div class="container-fluid px-3 px-md-4 py-4"> 
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-3 p-md-4 bg-white">
        
        <div class="row align-items-center mb-4 g-3">
            <div class="col-12 col-xl-4 text-center text-xl-start">
                <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-tools me-2 text-primary"></i>Material Withdrawals</h3>
            </div>
            
            <div class="col-12 col-xl-8">
                <div class="d-flex flex-wrap justify-content-start justify-content-xl-end align-items-center gap-2 w-100 action-bar-mobile">
                    
                    <!-- LIVE SEARCH -->
                    <div class="input-group shadow-sm flex-grow-1 flex-md-grow-0" style="max-width: 300px; min-width: 200px;">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="searchWithdrawals" class="form-control border-start-0 ps-0 bg-white" placeholder="Search project or slip no...">
                    </div>
                    
                    <!-- COLUMN TOGGLE CHECKBOX FILTER -->
                    <div class="dropdown shadow-sm flex-grow-1 flex-md-grow-0 d-none d-md-block">
                        <button class="btn btn-white border w-100 text-start d-flex justify-content-between align-items-center fw-bold text-dark" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" style="min-width: 120px;">
                            <span><i class="bi bi-sliders text-primary me-2"></i>Filter</span>
                            <i class="bi bi-chevron-down ms-2 text-muted" style="font-size: 0.8rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow p-3" style="min-width: 220px; border-radius: 10px;">
                            <li><h6 class="dropdown-header text-dark fw-bold px-1 mb-2">Show/Hide Columns</h6></li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col1" value="1" checked>
                                    <label class="form-check-label ms-1" for="col1">Slip No.</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col2" value="2" checked>
                                    <label class="form-check-label ms-1" for="col2">Project Assigned</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col3" value="3" checked>
                                    <label class="form-check-label ms-1" for="col3">Released By</label>
                                </div>
                            </li>
                            <li>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col4" value="4" checked>
                                    <label class="form-check-label ms-1" for="col4">Date & Time</label>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input col-toggle" type="checkbox" id="col5" value="5" checked>
                                    <label class="form-check-label ms-1" for="col5">Actions (Buttons)</label>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <?php if (in_array($role, ['warehouse', 'admin'])): ?>
                    <div class="d-flex gap-2 w-100 w-md-auto flex-grow-1 flex-md-grow-0">
                        <button class="btn btn-outline-brand border-2 fw-bold flex-fill text-nowrap shadow-sm" onclick="startRsScanner()">
                            <i class="bi bi-upc-scan me-1"></i> Scan RS
                        </button>
                        <button class="btn btn-brand flex-fill text-nowrap shadow-sm" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                            <i class="bi bi-pencil-square me-1"></i> Manual Entry
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="table-responsive border rounded shadow-sm">
            <table class="table table-hover align-middle mb-0 text-nowrap" id="withdrawalsTable">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">Slip No.</th>
                        <th class="py-3">Project Assigned</th>
                        <th class="py-3">Released By</th>
                        <th class="py-3">Date & Time</th>
                        <th class="text-center py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($withdrawals) > 0): ?>
                        <?php foreach ($withdrawals as $wd): ?>
                            <tr class="withdrawal-row">
                                <td class="fw-bold text-danger wd-slip" data-label="Slip No."><?= htmlspecialchars($wd['withdrawal_no']) ?></td>
                                <td class="fw-bold wd-project text-dark" data-label="Project Assigned"><?= htmlspecialchars($wd['project_name']) ?></td>
                                
                                <!-- THE FIX: Wrapped the icon and name in a <span> -->
                                <td data-label="Released By">
                                    <span><i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($wd['releaser_name']) ?></span>
                                </td>
                                
                                <td class="text-muted small fw-bold" data-label="Date & Time"><?= date('M d, Y h:i A', strtotime($wd['date_withdrawn'])) ?></td>
                                
                                <td class="text-center" data-label="Actions">
                                    <?php $currentItemsJson = htmlspecialchars(json_encode($wdItemsGrouped[$wd['id']] ?? []), ENT_QUOTES, 'UTF-8'); ?>
                                    <button class="btn btn-sm btn-outline-secondary fw-bold shadow-sm px-3" title="View Details" onclick="viewWdDetails('<?= $wd['withdrawal_no'] ?>', '<?= addslashes($wd['project_name']) ?>', '<?= addslashes($wd['remarks'] ?? '') ?>', '<?= $currentItemsJson ?>', '<?= addslashes($wd['releaser_name'] ?? '') ?>', '<?= addslashes($wd['requestor_name'] ?? 'N/A') ?>', '<?= addslashes($wd['received_by'] ?? 'N/A') ?>', '<?= addslashes($wd['signature_path'] ?? '') ?>', '<?= addslashes($wd['photo_proof_path'] ?? '') ?>')">
                                        <i class="bi bi-qr-code-scan me-1"></i> View Trail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="emptyRow" class="no-records"><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x fs-1 d-block mb-2"></i>No material withdrawals recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CAMERA SCANNER FOR APPROVED RS                    -->
<!-- ======================================================== -->
<div class="modal fade" id="rsScannerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-brand text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-upc-scan me-2"></i>Scan Approved RS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopRsScanner()"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="rsReader" class="mx-auto" style="width: 100%; border-radius: 8px; overflow: hidden; border: 2px solid var(--gb-blue);"></div>
                <div id="rsScannerResult" class="mt-3 fw-bold text-muted">Point your camera at the Approved RS Document QR Code...</div>
                <button type="button" class="btn btn-link text-muted mt-3" data-bs-dismiss="modal" onclick="stopRsScanner()">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Withdraw Details Modal -->
<div class="modal fade" id="viewWdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-list-check me-2" style="color: var(--gb-yellow);"></i>Withdrawal Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="d-flex justify-content-between align-items-start mb-4 border-bottom pb-3">
                    <div>
                        <h4 class="fw-bold text-danger mb-1" id="viewWdNo">WD-0000</h4>
                        <div class="text-dark fw-bold text-uppercase fs-6 mb-1" id="viewWdProject">Project Name</div>
                        <div class="text-muted small fw-bold" id="viewWdMeta">
                            <span class="me-3"><i class="bi bi-person me-1 text-primary"></i> Requested By: <strong class="text-dark" id="viewWdRequestor">N/A</strong></span>
                            <span class="me-3"><i class="bi bi-person-check me-1 text-success"></i> Received By: <strong class="text-dark" id="viewWdReceivedBy">N/A</strong></span>
                            <span><i class="bi bi-person-badge me-1 text-secondary"></i> Released By: <strong class="text-dark" id="viewWdReleaser">N/A</strong></span>
                        </div>
                    </div>
                    <div>
                        <img id="viewWdQrCode" src="" alt="Document QR Code" class="border p-1 bg-white shadow-sm" style="width: 80px; height: 80px; border-radius: 6px;">
                    </div>
                </div>
                
                <div class="table-responsive mb-4 rounded border shadow-sm">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap">
                        <thead class="table-light">
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th class="text-end">Qty Released</th>
                            </tr>
                        </thead>
                        <tbody id="viewWdItemsBody"></tbody>
                    </table>
                </div>
                
                <div class="card border-0 shadow-sm mb-3" id="viewWdProofCard">
                    <div class="card-header bg-white fw-bold small text-uppercase py-2 text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> Proof of Receipt & Audit Trail
                    </div>
                    <div class="card-body p-3 bg-white">
                        <div class="row align-items-center text-center g-3">
                            <div class="col-md-6 border-end-md" id="viewWdSigWrapper">
                                <div class="text-muted small fw-bold mb-2"><i class="bi bi-pen me-1 text-primary"></i> Digital Signature</div>
                                <div id="viewWdSigContent" class="p-1 border rounded shadow-sm d-inline-block bg-white" style="background-color: #ffffff !important;">
                                    <img id="viewWdSignatureImg" src="" class="img-fluid rounded" style="max-height: 100px; background-color: #ffffff !important;">
                                </div>
                            </div>
                            <div class="col-md-6" id="viewWdPhotoWrapper">
                                <div class="text-muted small fw-bold mb-2"><i class="bi bi-camera me-1 text-primary"></i> Handed-Over Photo Proof</div>
                                <div id="viewWdPhotoContent">
                                    <a id="viewWdPhotoLink" href="#" target="_blank">
                                        <img id="viewWdPhotoImg" src="" class="img-fluid border rounded shadow-sm p-1" style="max-height: 100px; object-fit: cover;">
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h6 class="fw-bold mb-2 text-dark small text-uppercase">Remarks / Notes:</h6>
                    <p class="text-muted small border p-3 bg-white rounded shadow-sm mb-0" id="viewWdRemarks">No remarks.</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-white border-top-0">
                <button type="button" class="btn btn-outline-primary fw-bold px-4" onclick="triggerWdPrint()"><i class="bi bi-printer me-2"></i>Print Slip</button>
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- NEW UI SCRIPT: Live Search, Column Filter & Pagination -->
<script>
let searchWdQuery = '';

document.addEventListener("DOMContentLoaded", function() {
    
    // 1. COLUMN TOGGLE LOGIC
    document.querySelectorAll('.col-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const colIndex = this.value;
            const table = document.getElementById('withdrawalsTable');
            if (this.checked) {
                table.classList.remove('hide-col-' + colIndex);
            } else {
                table.classList.add('hide-col-' + colIndex);
            }
        });
    });

    // 2. LIVE SEARCH LOGIC
    const searchInput = document.getElementById('searchWithdrawals');
    if(searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            searchWdQuery = e.target.value.toLowerCase();
            initWithdrawalsPagination();
        });
    }
    
    initWithdrawalsPagination();
});

function initWithdrawalsPagination() {
    const table = document.getElementById('withdrawalsTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr.withdrawal-row'));
    
    if (allRows.length === 0) return;

    // Filter by text search
    const activeRows = allRows.filter(row => {
        return searchWdQuery === '' || row.innerText.toLowerCase().includes(searchWdQuery);
    });

    allRows.forEach(row => row.style.display = 'none');

    // Handle "No Data" Empty State
    let noDataRow = tbody.querySelector('.no-data-alert-row');
    if (activeRows.length === 0) {
        if (!noDataRow) {
            noDataRow = document.createElement('tr');
            noDataRow.className = 'no-data-alert-row';
            noDataRow.innerHTML = '<td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-search fs-1 d-block mb-2"></i>No matching withdrawals found.</td>';
            tbody.appendChild(noDataRow);
        }
        noDataRow.style.display = '';
        const pw = table.parentElement.querySelector('.pagination-wrapper');
        if (pw) pw.style.display = 'none';
        return;
    } else {
        if (noDataRow) noDataRow.style.display = 'none';
    }

    // Pagination variables
    const rowsPerPage = 10;
    let currentPage = window.currentWdPage || 1; 
    const totalPages = Math.ceil(activeRows.length / rowsPerPage);
    if (currentPage > totalPages) currentPage = 1; 
    window.currentWdPage = currentPage;

    // Generate Pagination Footer UI
    let paginationWrapper = table.parentElement.querySelector('.pagination-wrapper');
    if (!paginationWrapper) {
        paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex flex-column flex-md-row justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper gap-3';
        
        paginationWrapper.innerHTML = `
            <span class="text-muted small fw-bold" id="pageInfoTextWd"></span>
            <div class="btn-group shadow-sm">
                <button class="btn btn-sm btn-outline-primary fw-bold px-3" id="prevPageBtnWd"><i class="bi bi-chevron-left me-1"></i> Prev</button>
                <button class="btn btn-sm btn-brand fw-bold px-3 pe-none" id="pageIndicatorBtnWd"></button>
                <button class="btn btn-sm btn-outline-primary fw-bold px-3" id="nextPageBtnWd">Next <i class="bi bi-chevron-right ms-1"></i></button>
            </div>
        `;
        table.parentElement.appendChild(paginationWrapper);

        document.getElementById('prevPageBtnWd').addEventListener('click', () => { 
            if (window.currentWdPage > 1) { window.currentWdPage--; showPage(); }
        });
        document.getElementById('nextPageBtnWd').addEventListener('click', () => { 
            if (window.currentWdPage < Math.ceil(activeRows.length / rowsPerPage)) { window.currentWdPage++; showPage(); }
        });
    }
    paginationWrapper.style.display = 'flex';

    function showPage() {
        activeRows.forEach(row => row.style.display = 'none'); 

        const start = (window.currentWdPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        for (let i = start; i < end && i < activeRows.length; i++) {
            activeRows[i].style.display = ''; 
        }

        document.getElementById('pageInfoTextWd').innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, activeRows.length)}</b> of <b>${activeRows.length}</b> entries`;
        document.getElementById('pageIndicatorBtnWd').innerText = `Page ${window.currentWdPage} / ${totalPages}`;
        
        document.getElementById('prevPageBtnWd').disabled = window.currentWdPage === 1;
        document.getElementById('nextPageBtnWd').disabled = window.currentWdPage === totalPages;
    }

    showPage();
}

// Reactivate if SPA Router navigates back
if (document.readyState !== "loading") {
    initWithdrawalsPagination();
}
</script>

<!-- Load Modals and Separate Javascript Engine -->
<?php include 'components/withdrawal_modal.php'; ?>

<!-- Preserved your scanner and entry logic safely here -->
<script src="assets/js/withdrawals.js?v=<?= time() ?>"></script>

<?php include 'layout/footer.php'; ?>