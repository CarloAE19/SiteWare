<!-- ExcelJS CDN for exporting tables to excel with styles -->
<script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>

<!-- Premium Mobile Card Table CSS for Modal -->
<style>
    @media (max-width: 767.98px) {
        #auditDetailsTable { display: block; width: 100%; background: transparent !important; border: none !important; }
        #auditDetailsTable thead { display: none; }
        #auditDetailsTable tbody { display: block; width: 100%; }

        #auditDetailsTable tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #fff;
            border-radius: 12px;
            margin-bottom: 0.85rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        /* 1. Item Code Header with Unit */
        #auditDetailsTable tbody td.audit-td-code {
            grid-column: 1 / -1;
            background: #1e293b;
            color: #f8fafc !important;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 7px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: none;
        }
        #auditDetailsTable tbody td.audit-td-code::before { display: none; }

        /* 2. Item Name */
        #auditDetailsTable tbody td.audit-td-name {
            grid-column: 1 / -1;
            padding: 9px 12px 5px;
            font-weight: 700;
            font-size: 0.92rem;
            color: #0f172a;
            border: none;
            display: block;
        }
        #auditDetailsTable tbody td.audit-td-name::before { display: none; }

        /* 3. System Qty Box */
        #auditDetailsTable tbody td.audit-td-sys {
            grid-column: 1 / 2;
            padding: 7px 10px;
            margin: 4px 5px 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        #auditDetailsTable tbody td.audit-td-sys::before {
            content: "SYSTEM";
            font-size: 0.65rem;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }

        /* 4. Physical Qty Box */
        #auditDetailsTable tbody td.audit-td-phys {
            grid-column: 2 / 3;
            padding: 7px 10px;
            margin: 4px 12px 8px 5px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        #auditDetailsTable tbody td.audit-td-phys::before {
            content: "PHYSICAL";
            font-size: 0.65rem;
            font-weight: 800;
            color: #2563eb;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }

        /* 5. Discrepancy Badge */
        #auditDetailsTable tbody td.audit-td-diff {
            grid-column: 1 / -1;
            padding: 0 12px 10px;
            border: none;
            display: flex;
            justify-content: center;
        }
        #auditDetailsTable tbody td.audit-td-diff::before { display: none; }
        #auditDetailsTable tbody td.audit-td-diff .badge {
            width: 100%;
            padding: 7px !important;
            font-size: 0.8rem !important;
            border-radius: 6px;
            letter-spacing: 0.04em;
        }
    }
</style>


<div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background-color: var(--gb-dark); color: white;">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-ruled me-2" style="color: var(--gb-yellow);"></i>Audit Trail Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-3 p-md-4">
                
                <!-- Audit Title & Subtitle -->
                <div class="border-bottom pb-2 mb-3">
                    <h5 class="fw-bold text-primary mb-0" id="modalAuditMonth">Audit Details</h5>
                    <small class="text-muted fw-bold text-uppercase">Weekly Recount Report</small>

                    <!-- Real-time KPI Summary Badges -->
                    <div class="d-flex flex-wrap gap-2 mt-2" id="modalAuditKpis">
                        <span class="badge bg-white text-dark border shadow-sm px-2 py-1"><i class="bi bi-box-seam me-1 text-primary"></i>Total: <strong id="modalKpiTotal">0</strong></span>
                        <span class="badge bg-white text-success border border-success-subtle shadow-sm px-2 py-1"><i class="bi bi-check-circle-fill me-1 text-success"></i>In-Sync: <strong id="modalKpiMatch">0</strong></span>
                        <span class="badge bg-white text-danger border border-danger-subtle shadow-sm px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Adjusted: <strong id="modalKpiDiff">0</strong></span>
                    </div>
                </div>

                <!-- Auditor Remarks -->
                <div class="p-2 p-md-3 bg-white rounded border shadow-sm mb-3">
                    <small class="text-muted fw-bold text-uppercase d-block mb-1" style="font-size: 0.68rem;">Auditor Remarks / Notes:</small>
                    <div class="text-dark small" id="modalAuditRemarks" style="min-height: 20px;">No remarks.</div>
                </div>

                <!-- Itemized Results Header & Filter Controls -->
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <span class="fw-bold text-uppercase small text-muted">Itemized Count Results:</span>
                    <div class="btn-group btn-group-sm shadow-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active fw-bold" id="btnAuditFilterAll" onclick="filterAuditModalRows('all')">
                            All (<span id="countFilterAll">0</span>)
                        </button>
                        <button type="button" class="btn btn-outline-danger fw-bold" id="btnAuditFilterDiff" onclick="filterAuditModalRows('diff')">
                            Discrepancies (<span id="countFilterDiff">0</span>)
                        </button>
                    </div>
                </div>

                <div class="table-responsive mb-0 rounded border bg-white shadow-sm" style="border: none !important; box-shadow: none !important; background: transparent !important;">
                    <table class="table table-sm table-hover mb-0 bg-white text-nowrap" id="auditDetailsTable">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-3">Item Code</th>
                                <th class="py-3">Item Name</th>
                                <th class="text-center py-3">System Qty</th>
                                <th class="text-center py-3">Physical Qty</th>
                                <th class="text-center py-3" style="width: 140px;">Discrepancy</th>
                            </tr>
                        </thead>
                        <tbody id="auditModalBody"></tbody>
                    </table>
                </div>
                
            </div>
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between flex-wrap gap-2">
                <button type="button" class="btn btn-success fw-bold px-4 w-100 w-md-auto shadow-sm" onclick="exportAuditToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
                <button type="button" class="btn btn-secondary fw-bold px-4 w-100 w-md-auto shadow-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================================
// AUDIT MODAL LOGIC (Directly injected to bypass mobile cache!)
// ==========================================================

window.viewAuditDetailsFromBtn = function(btn) {
    if (!btn) return;
    const month = btn.getAttribute('data-month') || '';
    const remarks = btn.getAttribute('data-remarks') || '';
    const itemsJson = btn.getAttribute('data-items') || '[]';
    window.viewAuditDetails(month, remarks, itemsJson);
};

window.filterAuditModalRows = function(filterType) {
    const btnAll = document.getElementById('btnAuditFilterAll');
    const btnDiff = document.getElementById('btnAuditFilterDiff');
    const rows = document.querySelectorAll('#auditModalBody tr');

    if (filterType === 'diff') {
        if (btnDiff) btnDiff.classList.add('active', 'btn-danger');
        if (btnDiff) btnDiff.classList.remove('btn-outline-danger');
        if (btnAll) btnAll.classList.remove('active', 'btn-secondary');
        if (btnAll) btnAll.classList.add('btn-outline-secondary');

        rows.forEach(r => {
            r.style.display = r.getAttribute('data-diff') === '1' ? '' : 'none';
        });
    } else {
        if (btnAll) btnAll.classList.add('active', 'btn-secondary');
        if (btnAll) btnAll.classList.remove('btn-outline-secondary');
        if (btnDiff) btnDiff.classList.remove('active', 'btn-danger');
        if (btnDiff) btnDiff.classList.add('btn-outline-danger');

        rows.forEach(r => {
            r.style.display = '';
        });
    }
};

window.viewAuditDetails = function(month, remarks, itemsJson) {
    document.getElementById('modalAuditMonth').innerText = "Audit: " + month;
    
    const remarksEl = document.getElementById('modalAuditRemarks');
    remarksEl.innerText = remarks ? remarks : 'No notes provided.';
    remarksEl.style.whiteSpace = 'pre-wrap';
    
    let tbody = document.getElementById('auditModalBody');
    tbody.innerHTML = '';
    
    let items = [];
    let matchCount = 0;
    let diffCount = 0;

    try {
        items = typeof itemsJson === 'string' ? JSON.parse(itemsJson) : (itemsJson || []);
        if (items.length > 0) {
            items.forEach(item => {
                let diff = parseInt(item.discrepancy) || 0;
                let isDiff = diff !== 0;
                if (isDiff) {
                    diffCount++;
                } else {
                    matchCount++;
                }

                let diffDisplay = '';
                if (diff < 0) {
                    diffDisplay = `<span class="badge bg-danger shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-arrow-down-circle-fill me-1"></i>${diff} Short</span>`;
                } else if (diff > 0) {
                    diffDisplay = `<span class="badge bg-warning text-dark shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-arrow-up-circle-fill me-1"></i>+${diff} Over</span>`;
                } else {
                    diffDisplay = `<span class="badge bg-success shadow-sm px-3 py-2 text-uppercase"><i class="bi bi-check-circle-fill me-1"></i>Match</span>`;
                }
                
                const unitStr = item.unit ? `<span class="text-muted small ms-1">${item.unit}</span>` : '';
                const unitBadge = item.unit ? `<span class="badge bg-dark-subtle text-light border border-secondary small">${item.unit}</span>` : '';

                tbody.innerHTML += `
                    <tr data-diff="${isDiff ? '1' : '0'}">
                        <td class="audit-td-code text-muted small align-middle px-3 fw-bold">
                            <span><i class="bi bi-tag me-1"></i>${item.item_code}</span>
                            ${unitBadge}
                        </td>
                        <td class="audit-td-name fw-bold align-middle text-dark">
                            ${item.item_name}
                        </td>
                        <td class="audit-td-sys text-end text-md-center align-middle text-secondary fw-bold fs-6">
                            <span>${item.system_qty}</span>${unitStr}
                        </td>
                        <td class="audit-td-phys text-end text-md-center align-middle text-primary fw-bold fs-6">
                            <span>${item.physical_qty}</span>${unitStr}
                        </td>
                        <td class="audit-td-diff text-end text-md-center align-middle">
                            ${diffDisplay}
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No items recorded in this audit.</td></tr>`;
        }
    } catch (e) {
        items = [];
        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error loading audit details.</td></tr>`;
    }

    // Update KPI badges & filter counts
    document.getElementById('modalKpiTotal').innerText = items.length;
    document.getElementById('modalKpiMatch').innerText = matchCount;
    document.getElementById('modalKpiDiff').innerText = diffCount;
    document.getElementById('countFilterAll').innerText = items.length;
    document.getElementById('countFilterDiff').innerText = diffCount;

    // Reset filter to 'all'
    window.filterAuditModalRows('all');
    
    // Save active audit data globally for ExcelJS export
    window.activeAuditData = {
        month: month,
        remarks: remarks ? remarks : 'No notes provided.',
        items: items
    };

    // SPA-Safe Modal Instantiation
    var myModalEl = document.getElementById('auditModal');
    var auditModal = bootstrap.Modal.getInstance(myModalEl);
    if (!auditModal) {
        auditModal = new bootstrap.Modal(myModalEl);
    }

    // Clean up on modal hide (Standards compliance)
    if (!myModalEl.dataset.hasCleanListener) {
        myModalEl.addEventListener('hidden.bs.modal', function() {
            document.getElementById('auditModalBody').innerHTML = '';
            window.activeAuditData = null;
        });
        myModalEl.dataset.hasCleanListener = 'true';
    }

    auditModal.show();
}

window.exportAuditToExcel = async function() {
    if (!window.activeAuditData || !window.activeAuditData.items || window.activeAuditData.items.length === 0) {
        alert("No data available to export.");
        return;
    }

    const auditData = window.activeAuditData;
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Recount Trail');

    // Ensure grid lines are visible
    worksheet.views = [{ showGridLines: true }];

    // Set precise column widths
    worksheet.getColumn('A').width = 18; // Item Code
    worksheet.getColumn('B').width = 35; // Item Name
    worksheet.getColumn('C').width = 15; // System Qty
    worksheet.getColumn('D').width = 15; // Physical Qty
    worksheet.getColumn('E').width = 15; // Discrepancy
    worksheet.getColumn('F').width = 18; // Status

    // 1. Try to load the company logo
    let logoLoaded = false;
    try {
        const logoUrl = 'assets/clearLogo.png';
        const response = await fetch(logoUrl);
        if (response.ok) {
            const blob = await response.blob();
            const logoBase64 = await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result.split(',')[1]);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });

            const logoId = workbook.addImage({
                base64: logoBase64,
                extension: 'png',
            });
            
            // Embed logo on top-left (Column A, spanning rows 1-3)
            // Column A width is 18 (approx 130px), Row 1+2+3 height is 75 points (approx 100px)
            // A 70x70 pixel square image will fit perfectly.
            worksheet.addImage(logoId, {
                tl: { col: 0.15, row: 0.15 },
                ext: { width: 70, height: 70 }
            });
            logoLoaded = true;
        }
    } catch (e) {
        console.warn("Logo could not be loaded for Excel export: ", e);
    }

    // Set heights for the header rows
    worksheet.getRow(1).height = 20;
    worksheet.getRow(2).height = 30;
    worksheet.getRow(3).height = 25;

    // 2. Company Name and Title Placement
    // If logo loaded, start text in column B to leave space. Otherwise start in column A.
    const textCol = logoLoaded ? 'B' : 'A';

    // Write Company Name
    worksheet.mergeCells(`${textCol}2:F2`);
    const companyCell = worksheet.getCell(`${textCol}2`);
    companyCell.value = "GB Construction & Enterprise Inc.";
    companyCell.font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FF212529' } };
    companyCell.alignment = { horizontal: 'left', vertical: 'middle' };

    // Write Sheet Title
    worksheet.mergeCells(`${textCol}3:F3`);
    const titleCell = worksheet.getCell(`${textCol}3`);
    titleCell.value = "WEEKLY PHYSICAL RECOUNT REPORT";
    titleCell.font = { name: 'Arial', size: 10, bold: true, color: { argb: 'FF0D6EFD' } };
    titleCell.alignment = { horizontal: 'left', vertical: 'middle' };

    // Row 4: Spacer
    worksheet.addRow([]);
    worksheet.getRow(4).height = 15;

    // 3. Metadata Info (Row 5 & 6)
    const periodRow = worksheet.addRow(["Audit Period:", auditData.month]);
    worksheet.mergeCells('B5:F5');
    periodRow.getCell(1).font = { name: 'Arial', size: 10, bold: true };
    periodRow.getCell(2).font = { name: 'Arial', size: 10, bold: true, color: { argb: 'FF0D6EFD' } };
    periodRow.height = 20;
    periodRow.alignment = { vertical: 'middle' };

    const remarksRow = worksheet.addRow(["Remarks / Notes:", auditData.remarks]);
    worksheet.mergeCells('B6:F6');
    remarksRow.getCell(1).font = { name: 'Arial', size: 10, bold: true };
    remarksRow.getCell(2).font = { name: 'Arial', size: 10, italic: true };
    remarksRow.height = 24;
    remarksRow.alignment = { vertical: 'middle', wrapText: true };

    // Row 7: Spacer
    worksheet.addRow([]);
    worksheet.getRow(7).height = 15;

    // 4. Header Row (Row 8)
    const headerRow = worksheet.addRow(['Item Code', 'Item Name', 'System Qty', 'Physical Qty', 'Discrepancy', 'Status']);
    headerRow.height = 26;

    const headerFill = {
        type: 'pattern',
        pattern: 'solid',
        fgColor: { argb: 'FF212529' } // Dark theme matching table-dark
    };
    const headerFont = {
        name: 'Arial',
        size: 10,
        bold: true,
        color: { argb: 'FFFFFFFF' }
    };
    const borderStyle = {
        top: { style: 'thin', color: { argb: 'FFCCCCCC' } },
        left: { style: 'thin', color: { argb: 'FFCCCCCC' } },
        bottom: { style: 'thin', color: { argb: 'FFCCCCCC' } },
        right: { style: 'thin', color: { argb: 'FFCCCCCC' } }
    };

    for (let col = 1; col <= 6; col++) {
        const cell = headerRow.getCell(col);
        cell.fill = headerFill;
        cell.font = headerFont;
        cell.alignment = { 
            horizontal: col === 1 || col === 2 ? 'left' : 'center', 
            vertical: 'middle' 
        };
        cell.border = borderStyle;
    }

    // 5. Populate Data Rows
    auditData.items.forEach(item => {
        const diff = parseInt(item.discrepancy) || 0;
        let status = 'Match';
        let statusColor = 'FFD1E7DD'; // green bg
        let textColor = 'FF0F5132';   // dark green text
        
        if (diff < 0) {
            status = `${diff} Short`;
            statusColor = 'FFF8D7DA'; // red bg
            textColor = 'FF842029';   // dark red text
        } else if (diff > 0) {
            status = `+${diff} Over`;
            statusColor = 'FFFFF3CD'; // yellow bg
            textColor = 'FF664D03';   // dark yellow text
        }

        const dataRow = worksheet.addRow([
            item.item_code,
            item.item_name,
            `${item.system_qty} ${item.unit || ''}`.trim(),
            `${item.physical_qty} ${item.unit || ''}`.trim(),
            diff === 0 ? 'Match' : (diff > 0 ? `+${diff}` : `${diff}`),
            status
        ]);
        dataRow.height = 22;

        for (let col = 1; col <= 6; col++) {
            const cell = dataRow.getCell(col);
            cell.font = { name: 'Arial', size: 10 };
            cell.border = borderStyle;
            cell.alignment = { 
                horizontal: col === 1 || col === 2 ? 'left' : 'center', 
                vertical: 'middle' 
            };

            // Status and Discrepancy column highlighting
            if (col === 5 || col === 6) {
                cell.fill = {
                    type: 'pattern',
                    pattern: 'solid',
                    fgColor: { argb: statusColor }
                };
                cell.font = {
                    name: 'Arial',
                    size: 10,
                    bold: true,
                    color: { argb: textColor }
                };
            }
        }
    });

    // 6. Trigger download
    workbook.xlsx.writeBuffer().then(function(buffer) {
        const blob = new Blob([buffer], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        const safeMonth = auditData.month.replace(/[^a-zA-Z0-9]/g, "_");
        link.download = `Recount_Report_${safeMonth}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }).catch(function(err) {
        console.error("Excel export error: ", err);
        alert("Failed to export Excel report. Please try again.");
    });
};
</script>