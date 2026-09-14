let currentProjectFilter = 'all';

function initProjectPagination() {
    function setupPagination(tableId, rowsPerPage) {
        const table = document.getElementById(tableId);
        if (!table) return;

        // Remove old wrapper if re-initializing
        const oldWrapper = table.parentElement.querySelector('.pagination-wrapper');
        if (oldWrapper) oldWrapper.remove();

        const tbody = table.querySelector('tbody');
        const allRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));

        // Filter rows based on current active filter
        const visibleRows = allRows.filter(row => {
            if (currentProjectFilter === 'all') return true;
            const status = (row.getAttribute('data-status') || '').toLowerCase();
            return status === currentProjectFilter;
        });

        // Hide rows that don't match the filter
        allRows.forEach(row => {
            if (!visibleRows.includes(row)) {
                row.style.display = 'none';
            }
        });

        if (visibleRows.length === 0) return;
        if (visibleRows.length <= rowsPerPage) {
            visibleRows.forEach(row => { row.style.display = ''; });
            return;
        }

        let currentPage = 1;
        const totalPages = Math.ceil(visibleRows.length / rowsPerPage);
        const paginationWrapper = document.createElement('div');
        paginationWrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';
        const infoText = document.createElement('span');
        infoText.className = 'text-muted small fw-bold';
        const btnGroup = document.createElement('div');
        btnGroup.className = 'btn-group shadow-sm';
        const prevBtn = document.createElement('button');
        prevBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        prevBtn.type = 'button'; prevBtn.innerHTML = '<i class="bi bi-chevron-left me-1"></i> Prev';
        const pageIndicator = document.createElement('button');
        pageIndicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none'; pageIndicator.type = 'button';
        const nextBtn = document.createElement('button');
        nextBtn.className = 'btn btn-sm btn-outline-primary fw-bold px-3';
        nextBtn.type = 'button'; nextBtn.innerHTML = 'Next <i class="bi bi-chevron-right ms-1"></i>';
        btnGroup.appendChild(prevBtn); btnGroup.appendChild(pageIndicator); btnGroup.appendChild(nextBtn);
        paginationWrapper.appendChild(infoText); paginationWrapper.appendChild(btnGroup);
        table.parentElement.appendChild(paginationWrapper);

        function showPage(page) {
            currentPage = page;
            const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
            visibleRows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
            infoText.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, visibleRows.length)}</b> of <b>${visibleRows.length}</b>`;
            pageIndicator.innerText = `Page ${page} / ${totalPages}`;
            prevBtn.disabled = page === 1; nextBtn.disabled = page === totalPages;
        }
        prevBtn.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
        nextBtn.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
        showPage(1);
    }

    setupPagination('projectsTable', 10);
}

window.filterProjectsTable = function (filter, btnEl) {
    currentProjectFilter = filter;

    // Update active pill styling
    document.querySelectorAll('.proj-filter-btn').forEach(btn => {
        btn.classList.remove('active', 'btn-dark', 'btn-success', 'btn-secondary');
        const f = btn.getAttribute('data-filter');
        if (f === 'all') btn.classList.add('btn-outline-dark');
        else if (f === 'active') btn.classList.add('btn-outline-success');
        else if (f === 'inactive') btn.classList.add('btn-outline-secondary');
    });

    if (btnEl) {
        btnEl.classList.add('active');
        if (filter === 'all') {
            btnEl.classList.remove('btn-outline-dark');
            btnEl.classList.add('btn-dark');
        } else if (filter === 'active') {
            btnEl.classList.remove('btn-outline-success');
            btnEl.classList.add('btn-success');
        } else if (filter === 'inactive') {
            btnEl.classList.remove('btn-outline-secondary');
            btnEl.classList.add('btn-secondary');
        }
    }

    initProjectPagination();
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initProjectPagination);
} else {
    initProjectPagination();
}

window.generateAutoProjectCode = function () {
    const year = new Date().getFullYear();
    const randNum = Math.floor(100 + Math.random() * 900);
    const autoCode = `PRJ-${year}-${randNum}`;
    const input = document.getElementById('projectCode');
    if (input) input.value = autoCode;
};

window.openAddProjectModal = function () {
    document.getElementById('projectModalTitle').innerText = 'Add New Project';
    document.getElementById('projectFormAction').value = 'add_project';
    document.getElementById('projectId').value = '';

    // Auto-generate a suggested Project ID
    generateAutoProjectCode();

    document.getElementById('projectName').value = '';
    const addrInput = document.getElementById('projectAddress');
    if (addrInput) addrInput.value = '';
    document.getElementById('projectDesc').value = '';
    document.getElementById('projectStatus').value = 'active';
};

window.openEditProjectModal = function (id, code, name, address, desc, status) {
    document.getElementById('projectModalTitle').innerText = 'Edit Project';
    document.getElementById('projectFormAction').value = 'edit_project';
    document.getElementById('projectId').value = id;
    document.getElementById('projectCode').value = code;
    document.getElementById('projectName').value = name;
    const addrInput = document.getElementById('projectAddress');
    if (addrInput) addrInput.value = address || '';
    document.getElementById('projectDesc').value = desc;
    document.getElementById('projectStatus').value = status;
    new bootstrap.Modal(document.getElementById('projectModal')).show();
};

window.openProjectDetailsModal = async function (projectId) {
    const modalEl = document.getElementById('projectDetailsModal');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    const loadingEl = document.getElementById('projDetailsLoading');
    const contentEl = document.getElementById('projDetailsContent');

    if (loadingEl) {
        loadingEl.style.display = 'block';
        loadingEl.innerHTML = `
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
            <div class="fw-bold text-muted">Loading project materials and transaction records...</div>
        `;
    }
    if (contentEl) contentEl.style.display = 'none';

    // Reset active tab to Requisitions
    const rsTabBtn = document.getElementById('proj-tab-rs');
    if (rsTabBtn && window.bootstrap) {
        const tabTrigger = new bootstrap.Tab(rsTabBtn);
        tabTrigger.show();
    }

    try {
        const formData = new FormData();
        formData.append('action', 'fetch_project_details');
        formData.append('project_id', projectId);

        const response = await fetch('process/process.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await response.json();

        if (!data || data.status !== 'success' || !data.project) {
            if (loadingEl) {
                loadingEl.innerHTML = `<div class="alert alert-warning mb-0">${escapeHtml(data?.message || 'Failed to load project details.')}</div>`;
            }
            return;
        }

        const p = data.project;
        const reqs = data.requisitions || [];
        const wds = data.withdrawals || [];
        const summary = data.consumption_summary || [];

        currentProjectData = {
            project: p,
            requisitions: reqs,
            withdrawals: wds,
            summary: summary
        };

        // Fill Overview Info
        document.getElementById('projDetailsTitle').innerText = p.project_name || 'Project Details';
        document.getElementById('projDetailsName').innerText = p.project_name || 'Unnamed Project';
        document.getElementById('projDetailsCode').innerText = p.project_code || '#' + p.id;
        document.getElementById('projDetailsAddress').innerHTML = p.address
            ? `<i class="bi bi-geo-alt text-danger me-1"></i>${escapeHtml(p.address)}`
            : '<span class="text-muted fst-italic">No specific location registered</span>';
        document.getElementById('projDetailsDesc').innerText = p.description || 'No description provided.';

        const statusBadge = document.getElementById('projDetailsStatus');
        if (statusBadge) {
            if (p.status === 'active') {
                statusBadge.className = 'badge bg-success px-3 py-2 fs-6';
                statusBadge.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Active Site';
            } else {
                statusBadge.className = 'badge bg-secondary px-3 py-2 fs-6';
                statusBadge.innerHTML = '<i class="bi bi-pause-circle me-1"></i>Inactive / Completed';
            }
        }

        // Stats
        document.getElementById('projStatRs').innerText = reqs.length;
        document.getElementById('projStatWs').innerText = wds.length;
        document.getElementById('projStatMaterials').innerText = summary.length;
        document.getElementById('projTabRsCount').innerText = reqs.length;
        document.getElementById('projTabWsCount').innerText = wds.length;

        // 1. Populate Requisitions Table
        const rsTbody = document.getElementById('projRsTableBody');
        if (rsTbody) {
            if (reqs.length > 0) {
                rsTbody.innerHTML = reqs.map((rs, index) => {
                    const urgencyClass = (rs.urgency === 'High' || rs.urgency === 'Emergency') ? 'bg-danger' : (rs.urgency === 'Medium' ? 'bg-warning text-dark' : 'bg-secondary');

                    let statusClass = 'bg-secondary';
                    if (rs.status === 'Approved' || rs.status === 'Released') statusClass = 'bg-success';
                    else if (rs.status === 'Pending Approval' || rs.status === 'Partially Approved') statusClass = 'bg-warning text-dark';
                    else if (rs.status === 'Rejected') statusClass = 'bg-danger';
                    else if (rs.status === 'Staged (Ready for Pickup)') statusClass = 'bg-info text-dark';
                    else if (rs.status === 'PO Created') statusClass = 'bg-primary';

                    const itemsList = (rs.items || []).map(it =>
                        `<span class="badge bg-light text-dark border me-1 mb-1 font-monospace"><b>${it.quantity} ${escapeHtml(it.unit)}</b> ${escapeHtml(it.item_name)}</span>`
                    ).join('') || '<span class="text-muted small">No items listed</span>';

                    return `
                        <tr>
                            <td class="fw-bold font-monospace text-primary">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold font-monospace shadow-sm" onclick="openProjectRsDoc(${index})" title="Click to view Requisition Document popup">
                                    <i class="bi bi-file-earmark-text me-1"></i>${escapeHtml(rs.rs_no)}
                                </button>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">${escapeHtml(rs.requestor_name || rs.user_name || 'Requestor')}</div>
                                <small class="text-muted">${escapeHtml(rs.remarks || rs.purpose || '')}</small>
                            </td>
                            <td class="small text-muted">${rs.formatted_date}</td>
                            <td><span class="badge ${urgencyClass} px-2 py-1">${escapeHtml(rs.urgency || 'Normal')}</span></td>
                            <td><span class="badge ${statusClass} px-2 py-1 shadow-sm">${escapeHtml(rs.status)}</span></td>
                            <td>${itemsList}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                rsTbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-file-earmark-x fs-4 d-block mb-1 opacity-50"></i>No material requisitions submitted for this project yet.</td></tr>`;
            }
        }

        // 2. Populate Withdrawals Table
        const wsTbody = document.getElementById('projWsTableBody');
        if (wsTbody) {
            if (wds.length > 0) {
                wsTbody.innerHTML = wds.map((ws, index) => {
                    const itemsList = (ws.items || []).map(it =>
                        `<span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1 font-monospace"><b>${it.quantity} ${escapeHtml(it.unit)}</b> ${escapeHtml(it.item_name)}</span>`
                    ).join('') || '<span class="text-muted small">No items recorded</span>';

                    let proofHtml = '';
                    if (ws.signature_path || ws.photo_proof_path) {
                        proofHtml = `<button type="button" class="btn btn-sm btn-outline-info" onclick="openProjectWdDoc(${index})"><i class="bi bi-check-circle me-1"></i>View Proof</button>`;
                    } else {
                        proofHtml = `<span class="text-muted small fst-italic">${escapeHtml(ws.remarks || 'Standard withdrawal')}</span>`;
                    }

                    return `
                        <tr>
                            <td class="fw-bold font-monospace text-success">
                                <button type="button" class="btn btn-sm btn-outline-success fw-bold font-monospace shadow-sm" onclick="openProjectWdDoc(${index})" title="Click to view Withdrawal Slip popup">
                                    <i class="bi bi-box-arrow-right me-1"></i>${escapeHtml(ws.withdrawal_no)}
                                </button>
                            </td>
                            <td><span class="fw-bold text-dark">${escapeHtml(ws.received_by || 'Site Receiver')}</span></td>
                            <td class="small text-muted">${ws.formatted_date}</td>
                            <td class="small text-muted">${escapeHtml(ws.released_by_name || 'Warehouse Officer')}</td>
                            <td>${itemsList}</td>
                            <td>${proofHtml}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                wsTbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><i class="bi bi-box-seam fs-4 d-block mb-1 opacity-50"></i>No materials have been withdrawn or dispatched to this site yet.</td></tr>`;
            }
        }

        // 3. Populate Consumption Summary Table
        const sumTbody = document.getElementById('projSummaryTableBody');
        if (sumTbody) {
            if (summary.length > 0) {
                sumTbody.innerHTML = summary.map(item => `
                    <tr>
                        <td class="font-monospace text-muted fw-bold">${escapeHtml(item.item_code)}</td>
                        <td class="fw-bold text-dark">${escapeHtml(item.item_name)}</td>
                        <td class="text-center">
                            <span class="badge bg-primary px-3 py-2 fs-6 shadow-sm">
                                <b>${item.total_quantity}</b> ${escapeHtml(item.unit)}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-muted border px-2 py-1">${item.withdrawal_count} dispatch(es)</span>
                        </td>
                    </tr>
                `).join('');
            } else {
                sumTbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-bar-chart fs-4 d-block mb-1 opacity-50"></i>No material consumption data available yet.</td></tr>`;
            }
        }

        // Display Content
        if (loadingEl) loadingEl.style.display = 'none';
        if (contentEl) contentEl.style.display = 'block';

    } catch (err) {
        console.error('Error fetching project details:', err);
        if (loadingEl) loadingEl.innerHTML = `<div class="alert alert-danger mb-0">Failed to connect to server: ${escapeHtml(err.message)}</div>`;
    }
};

window.openProjectRsDoc = function (index) {
    const rs = currentProjectData.requisitions[index];
    if (!rs) return;

    document.getElementById('projRsDocNo').innerText = rs.rs_no || 'RS-0000';
    document.getElementById('projRsDocProject').innerText = currentProjectData.project.project_name || 'Project';
    document.getElementById('projRsDocRequestor').innerText = rs.requestor_name || rs.user_name || 'Requestor';
    document.getElementById('projRsDocDate').innerText = rs.formatted_date || '';

    const urgencyEl = document.getElementById('projRsDocUrgency');
    if (urgencyEl) {
        urgencyEl.innerText = rs.urgency || 'Normal';
        urgencyEl.className = 'badge ' + ((rs.urgency === 'High' || rs.urgency === 'Emergency') ? 'bg-danger' : (rs.urgency === 'Medium' ? 'bg-warning text-dark' : 'bg-secondary'));
    }

    const statusEl = document.getElementById('projRsDocStatus');
    if (statusEl) {
        statusEl.innerText = rs.status;
        let statusClass = 'bg-secondary';
        if (rs.status === 'Approved' || rs.status === 'Released') statusClass = 'bg-success';
        else if (rs.status === 'Pending Approval' || rs.status === 'Partially Approved') statusClass = 'bg-warning text-dark';
        else if (rs.status === 'Rejected') statusClass = 'bg-danger';
        else if (rs.status === 'Staged (Ready for Pickup)') statusClass = 'bg-info text-dark';
        else if (rs.status === 'PO Created') statusClass = 'bg-primary';
        statusEl.className = 'badge ' + statusClass;
    }

    const remarksEl = document.getElementById('projRsDocRemarks');
    if (remarksEl) remarksEl.innerText = rs.remarks || 'No remarks provided.';

    // QR Code
    const qrContainer = document.getElementById('projRsDocQrContainer');
    if (qrContainer) {
        if (rs.status === 'Approved' || rs.status === 'PO Created' || rs.status === 'Staged (Ready for Pickup)') {
            const qrData = encodeURIComponent(`REQ-DATA:${rs.rs_no}`);
            document.getElementById('projRsDocQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${qrData}`;
            qrContainer.classList.remove('d-none');
        } else {
            qrContainer.classList.add('d-none');
        }
    }

    // Items table
    const tbody = document.getElementById('projRsDocItemsBody');
    if (tbody) {
        if (rs.items && rs.items.length > 0) {
            tbody.innerHTML = rs.items.map(it => `
                <tr>
                    <td class="font-monospace fw-bold text-muted small">${escapeHtml(it.item_code)}</td>
                    <td class="fw-semibold text-dark">${escapeHtml(it.item_name)}</td>
                    <td class="text-center font-monospace fw-bold">${it.quantity} ${escapeHtml(it.unit)}</td>
                    <td class="text-center"><span class="badge bg-light text-dark border">${escapeHtml(it.item_status || 'Pending')}</span></td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">No items listed.</td></tr>`;
        }
    }

    // Full page link
    const linkEl = document.getElementById('projRsDocFullPageLink');
    if (linkEl) linkEl.href = `requisitions?search=${encodeURIComponent(rs.rs_no)}`;

    new bootstrap.Modal(document.getElementById('viewProjectRsModal')).show();
};

window.openProjectWdDoc = function (index) {
    const ws = currentProjectData.withdrawals[index];
    if (!ws) return;

    document.getElementById('projWdDocNo').innerText = ws.withdrawal_no || 'WD-0000';
    document.getElementById('projWdDocProject').innerText = currentProjectData.project.project_name || 'Project';
    document.getElementById('projWdDocReceiver').innerText = ws.received_by || 'Receiver';
    document.getElementById('projWdDocReleaser').innerText = ws.released_by_name || 'Warehouse Staff';
    document.getElementById('projWdDocDate').innerText = ws.formatted_date || '';

    const remarksEl = document.getElementById('projWdDocRemarks');
    if (remarksEl) remarksEl.innerText = ws.remarks || 'No remarks provided.';

    // Helper function to format authenticated proxy image URL
    function getSecureImageUrl(path, type) {
        if (!path || path.trim() === '') return '';
        const filename = path.split('/').pop();
        return `secure_image?type=${type}&file=${encodeURIComponent(filename)}`;
    }

    // Signature & Photo
    const sigWrapper = document.getElementById('projWdDocSigWrapper');
    const photoWrapper = document.getElementById('projWdDocPhotoWrapper');
    const proofCard = document.getElementById('projWdDocProofCard');

    if (ws.signature_path && ws.signature_path.trim() !== '') {
        document.getElementById('projWdDocSigImg').src = getSecureImageUrl(ws.signature_path, 'signatures');
        if (sigWrapper) sigWrapper.classList.remove('d-none');
    } else {
        if (sigWrapper) sigWrapper.classList.add('d-none');
    }

    if (ws.photo_proof_path && ws.photo_proof_path.trim() !== '') {
        const photoUrl = getSecureImageUrl(ws.photo_proof_path, 'proofs');
        document.getElementById('projWdDocPhotoImg').src = photoUrl;
        document.getElementById('projWdDocPhotoLink').href = photoUrl;
        if (photoWrapper) photoWrapper.classList.remove('d-none');
    } else {
        if (photoWrapper) photoWrapper.classList.add('d-none');
    }

    if ((!ws.signature_path || ws.signature_path.trim() === '') && (!ws.photo_proof_path || ws.photo_proof_path.trim() === '')) {
        if (proofCard) proofCard.classList.add('d-none');
    } else {
        if (proofCard) proofCard.classList.remove('d-none');
    }

    // Items table
    const tbody = document.getElementById('projWdDocItemsBody');
    if (tbody) {
        if (ws.items && ws.items.length > 0) {
            tbody.innerHTML = ws.items.map(it => `
                <tr>
                    <td class="font-monospace fw-bold text-muted small">${escapeHtml(it.item_code)}</td>
                    <td class="fw-semibold text-dark">${escapeHtml(it.item_name)}</td>
                    <td class="text-center font-monospace fw-bold text-success">${it.quantity} ${escapeHtml(it.unit)}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">No items recorded.</td></tr>`;
        }
    }

    // Full page link
    const linkEl = document.getElementById('projWdDocFullPageLink');
    if (linkEl) linkEl.href = `withdrawals?search=${encodeURIComponent(ws.withdrawal_no)}`;

    new bootstrap.Modal(document.getElementById('viewProjectWdModal')).show();
};

window.printProjectConsumptionReport = function () {
    if (!currentProjectData || !currentProjectData.project) {
        alert("No project data available to print.");
        return;
    }

    const p = currentProjectData.project;
    const summary = currentProjectData.summary || [];
    const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    let rowsHtml = '';
    if (summary.length > 0) {
        rowsHtml = summary.map((item, idx) => `
            <tr>
                <td style="text-align: center; border: 1px solid #dee2e6; padding: 8px;">${idx + 1}</td>
                <td style="font-family: monospace; border: 1px solid #dee2e6; padding: 8px;">${escapeHtml(item.item_code)}</td>
                <td style="font-weight: 600; border: 1px solid #dee2e6; padding: 8px;">${escapeHtml(item.item_name)}</td>
                <td style="text-align: right; font-weight: bold; border: 1px solid #dee2e6; padding: 8px;">${item.total_quantity}</td>
                <td style="text-align: center; border: 1px solid #dee2e6; padding: 8px;">${escapeHtml(item.unit)}</td>
                <td style="text-align: center; border: 1px solid #dee2e6; padding: 8px;">${item.withdrawal_count} dispatch(es)</td>
            </tr>
        `).join('');
    } else {
        rowsHtml = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6c757d;">No materials have been withdrawn or dispatched to this project site.</td></tr>`;
    }

    const printWindow = window.open('', '_blank', 'width=900,height=900');
    if (!printWindow) {
        alert("Pop-up blocked. Please allow pop-ups for this site to print.");
        return;
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Jobsite Material Consumption Report - ${escapeHtml(p.project_name)}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @page { size: portrait; margin: 15mm; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #212529; background: #fff; }
                .report-header { border-bottom: 2px solid #212529; padding-bottom: 15px; margin-bottom: 20px; }
                .report-meta-box { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
                .table-bordered th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .signature-line { border-top: 1px solid #000; width: 80%; margin: 40px auto 5px auto; }
            </style>
        </head>
        <body class="p-4">
            <div class="report-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 0.5px;">GB Construction & Enterprises</h3>
                    <h5 class="text-muted mb-0 fw-normal">Jobsite Material Consumption & Allocation Report</h5>
                </div>
                <div class="text-end">
                    <span class="badge bg-dark fs-6 font-monospace">${escapeHtml(p.project_code || '#' + p.id)}</span>
                    <div class="small text-muted mt-1">Status: <b>${escapeHtml(p.status ? p.status.toUpperCase() : 'ACTIVE')}</b></div>
                </div>
            </div>

            <div class="report-meta-box">
                <div class="row g-2">
                    <div class="col-7">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Project Name & Location</small>
                        <h5 class="fw-bold mb-1 text-primary">${escapeHtml(p.project_name)}</h5>
                        <div class="small text-dark"><i class="bi bi-geo-alt"></i> ${escapeHtml(p.address || 'No specific location registered')}</div>
                    </div>
                    <div class="col-5 text-end">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Report Generated</small>
                        <div class="fw-bold text-dark small">${dateStr}</div>
                        <div class="small text-muted mt-1">Total Requisitions: <b>${(currentProjectData.requisitions || []).length}</b> | Dispatches: <b>${(currentProjectData.withdrawals || []).length}</b></div>
                    </div>
                </div>
            </div>

            <h6 class="fw-bold text-uppercase mb-2 text-dark" style="letter-spacing: 0.5px;">Aggregated Material Consumption Summary:</h6>
            <table class="table table-bordered table-sm mb-4" style="border-color: #dee2e6;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <th style="width: 120px;">Item Code</th>
                        <th>Material Description</th>
                        <th style="width: 120px; text-align: right;">Total Qty</th>
                        <th style="width: 80px; text-align: center;">Unit</th>
                        <th style="width: 120px; text-align: center;">Frequency</th>
                    </tr>
                </thead>
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>

            <div class="row mt-5 pt-3 text-center">
                <div class="col-4">
                    <div class="signature-line"></div>
                    <small class="fw-bold text-dark d-block">Warehouse Custodian</small>
                    <small class="text-muted">Prepared By</small>
                </div>
                <div class="col-4">
                    <div class="signature-line"></div>
                    <small class="fw-bold text-dark d-block">Site Engineer / Foreman</small>
                    <small class="text-muted">Verified & Received</small>
                </div>
                <div class="col-4">
                    <div class="signature-line"></div>
                    <small class="fw-bold text-dark d-block">Project Manager</small>
                    <small class="text-muted">Approved & Noted</small>
                </div>
            </div>

            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                };
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
};

window.exportProjectConsumptionCSV = function () {
    if (!currentProjectData || !currentProjectData.project) {
        alert("No project data available to export.");
        return;
    }

    const p = currentProjectData.project;
    const summary = currentProjectData.summary || [];

    if (summary.length === 0) {
        alert("No material consumption records found for this project.");
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += `Project Code,${p.project_code || '#' + p.id}\n`;
    csvContent += `Project Name,"${(p.project_name || '').replace(/"/g, '""')}"\n`;
    csvContent += `Location,"${(p.address || '').replace(/"/g, '""')}"\n`;
    csvContent += `Status,${p.status || 'active'}\n\n`;

    csvContent += "Item Code,Material Name,Total Quantity Delivered,Unit,Dispatches Count\n";

    summary.forEach(item => {
        const code = `"${(item.item_code || '').replace(/"/g, '""')}"`;
        const name = `"${(item.item_name || '').replace(/"/g, '""')}"`;
        const qty = item.total_quantity;
        const unit = `"${(item.unit || '').replace(/"/g, '""')}"`;
        const count = item.withdrawal_count;
        csvContent += `${code},${name},${qty},${unit},${count}\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    const fileName = `Material_Consumption_${(p.project_name || 'Project').replace(/[^a-zA-Z0-9_-]/g, '_')}_${new Date().toISOString().slice(0, 10)}.csv`;
    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();
    link.remove();
};

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


