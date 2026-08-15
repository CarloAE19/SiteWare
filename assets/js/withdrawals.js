// Global Popup Window Helper for Secure Media Viewing
window.openPhotoWindow = function (url) {
    if (!url || url === '#' || url === 'javascript:void(0);') return;
    const width = 850;
    const height = 700;
    const left = (window.screen.width / 2) - (width / 2);
    const top = (window.screen.height / 2) - (height / 2);
    window.open(url, 'SecurePhotoViewer', `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes,status=yes`);
};

// 1. VIEW WITHDRAWAL DETAILS MODAL
window.viewWdDetails = function (wdNo, project, remarks, itemsJson, releaser = '', requestor = '', receivedBy = '', signaturePath = '', photoProofPath = '', releaserSignaturePath = '') {
    window.currentWdData = {
        releaserSignaturePath: releaserSignaturePath || '',
        signaturePath: signaturePath || '',
        releaser: releaser || '',
        receivedBy: receivedBy || ''
    };

    document.getElementById('viewWdNo').innerText = wdNo;
    document.getElementById('viewWdProject').innerText = project;
    document.getElementById('viewWdRemarks').innerText = remarks ? remarks : 'No remarks.';
    if (document.getElementById('viewWdReleaser')) document.getElementById('viewWdReleaser').innerText = releaser || 'Warehouse Staff';
    if (document.getElementById('viewWdRequestor')) document.getElementById('viewWdRequestor').innerText = requestor || 'N/A';
    if (document.getElementById('viewWdReceivedBy')) document.getElementById('viewWdReceivedBy').innerText = receivedBy || 'N/A';

    // Helper function to format authenticated proxy image URL (clean URL without .php)
    function getSecureImageUrl(path, type) {
        if (!path || path.trim() === '') return '';
        const filename = path.split('/').pop();
        return `secure_image.php?type=${type}&file=${encodeURIComponent(filename)}`;
    }

    // Signature Image
    const sigImg = document.getElementById('viewWdSignatureImg');
    const sigWrapper = document.getElementById('viewWdSigWrapper');
    if (sigImg && sigWrapper) {
        if (signaturePath && signaturePath.trim() !== '') {
            sigImg.src = getSecureImageUrl(signaturePath, 'signatures');
            sigWrapper.style.display = '';
        } else {
            sigWrapper.style.display = 'none';
        }
    }

    // Photo Proof Image
    const photoImg = document.getElementById('viewWdPhotoImg');
    const photoLink = document.getElementById('viewWdPhotoLink');
    const photoWrapper = document.getElementById('viewWdPhotoWrapper');
    if (photoImg && photoWrapper) {
        if (photoProofPath && photoProofPath.trim() !== '') {
            const secureUrl = getSecureImageUrl(photoProofPath, 'proofs');
            photoImg.src = secureUrl;
            if (photoLink) {
                photoLink.href = secureUrl;
                photoLink.onclick = function (e) {
                    e.preventDefault();
                    window.openPhotoWindow(secureUrl);
                };
            }
            photoWrapper.style.display = '';
        } else {
            photoWrapper.style.display = 'none';
        }
    }

    // Hide proof card if both are empty
    const proofCard = document.getElementById('viewWdProofCard');
    if (proofCard) {
        if ((!signaturePath || signaturePath.trim() === '') && (!photoProofPath || photoProofPath.trim() === '')) {
            proofCard.style.display = 'none';
        } else {
            proofCard.style.display = '';
        }
    }

    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
    const verifyUrl = `${window.location.origin}${basePath}/verify?type=wd&ref=${encodeURIComponent(wdNo)}`;
    document.getElementById('viewWdQrCode').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(verifyUrl)}`;

    const tbody = document.getElementById('viewWdItemsBody');
    tbody.innerHTML = '';
    try {
        const items = JSON.parse(itemsJson);
        items.forEach(item => {
            tbody.innerHTML += `
                <tr>
                    <td class="text-muted small align-middle">${item.item_code}</td>
                    <td class="fw-bold align-middle">${item.item_name}</td>
                    <td class="text-danger fw-bold text-end align-middle">-${item.quantity} ${item.unit}</td>
                </tr>`;
        });
    } catch (e) { tbody.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-3">Error loading items.</td></tr>`; }

    new bootstrap.Modal(document.getElementById('viewWdModal')).show();
};

// 1.5 PRINT WITHDRAWAL SLIP VOUCHER (Compact 1-Page Layout for A4 Portrait / Half-Fold)
window.triggerWdPrint = function () {
    const wdNo = document.getElementById('viewWdNo')?.innerText || 'WD-0000';
    const project = document.getElementById('viewWdProject')?.innerText || 'Project Name';
    const requestor = document.getElementById('viewWdRequestor')?.innerText || 'N/A';
    const receivedBy = document.getElementById('viewWdReceivedBy')?.innerText || 'N/A';
    const releaser = document.getElementById('viewWdReleaser')?.innerText || 'Warehouse Officer';
    const remarks = document.getElementById('viewWdRemarks')?.innerText || 'No remarks.';
    const qrSrc = document.getElementById('viewWdQrCode')?.src || '';

    let releaserSigSrc = '';
    const releaserSigImg = document.getElementById('viewWdReleaserSigImg');
    if (releaserSigImg && releaserSigImg.src && releaserSigImg.src.includes('secure')) {
        releaserSigSrc = releaserSigImg.src;
    } else if (window.currentWdData && window.currentWdData.releaserSignaturePath) {
        const fn = window.currentWdData.releaserSignaturePath.split('/').pop();
        releaserSigSrc = `secure_image.php?type=signatures&file=${encodeURIComponent(fn)}`;
    }

    let receiverSigSrc = '';
    const receiverSigImg = document.getElementById('viewWdReceiverSigImg') || document.getElementById('viewWdSignatureImg');
    if (receiverSigImg && receiverSigImg.src && receiverSigImg.src.includes('secure')) {
        receiverSigSrc = receiverSigImg.src;
    } else if (window.currentWdData && window.currentWdData.signaturePath) {
        const fn = window.currentWdData.signaturePath.split('/').pop();
        receiverSigSrc = `secure_image.php?type=signatures&file=${encodeURIComponent(fn)}`;
    }

    const photoImg = document.getElementById('viewWdPhotoImg');
    const photoSrc = (photoImg && photoImg.src && photoImg.src.includes('secure')) ? photoImg.src : '';

    // Copy rows from viewWdItemsBody
    const tbodySource = document.getElementById('viewWdItemsBody');
    let itemsRowsHtml = '';
    if (tbodySource) {
        const rows = tbodySource.querySelectorAll('tr');
        rows.forEach(tr => {
            const cols = tr.querySelectorAll('td');
            if (cols.length >= 3) {
                itemsRowsHtml += `
                    <tr>
                        <td style="padding: 4px 6px; border: 1px solid #000; font-family: monospace;">${cols[0].innerText}</td>
                        <td style="padding: 4px 6px; border: 1px solid #000; font-weight: bold;">${cols[1].innerText}</td>
                        <td style="padding: 4px 6px; border: 1px solid #000; text-align: right; font-weight: bold; color: #cc0000;">${cols[2].innerText}</td>
                    </tr>
                `;
            }
        });
    }

    let photoBlockHtml = '';
    if (photoSrc) {
        photoBlockHtml = `
            <div style="margin-bottom: 6px;">
                <div style="font-size: 9.5px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; color: #444;">Handed-Over Photo Proof</div>
                <div style="border: 1px solid #ccc; padding: 4px; border-radius: 4px; background: #fff; display: inline-block;">
                    <img src="${photoSrc}" style="max-height: 52px; max-width: 200px; object-fit: cover; border-radius: 3px;">
                </div>
            </div>
        `;
    }

    const printContent = `
        <div id="printWdVoucher" style="font-family: Arial, sans-serif; color: #000; width: 100%; max-width: 100%; margin: 0 auto; padding: 2px 4px;">
            <!-- HEADER -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px;">
                <div style="display: flex; align-items: center;">
                    <img src="assets/LogoGB.png" alt="GB Logo" style="height: 38px; width: auto; object-fit: contain; margin-right: 10px;">
                    <div>
                        <h4 style="margin: 0; font-size: 13px; font-weight: bold; color: #0033CC; text-transform: uppercase; letter-spacing: 0.5px;">GENETIAN BUILDERS & ENTERPRISES INC.</h4>
                        <div style="font-size: 9.5px; color: #555; font-weight: bold;">Official Construction Inventory Management System</div>
                        <h5 style="margin: 2px 0 0 0; font-size: 11.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #111;">MATERIAL WITHDRAWAL SLIP</h5>
                    </div>
                </div>
                ${qrSrc ? `<img src="${qrSrc}" style="width: 52px; height: 52px; border: 1px solid #ccc; padding: 2px; border-radius: 4px;">` : ''}
            </div>

            <!-- META INFO GRID -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3px 8px; background: #f8f9fa; border: 1px solid #ccc; padding: 5px 8px; border-radius: 4px; margin-bottom: 6px;">
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #666; font-weight: bold; display: block;">Withdrawal Slip No:</span>
                    <strong style="font-size: 13px; color: #0033CC;">${wdNo}</strong>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 9px; text-transform: uppercase; color: #666; font-weight: bold; display: block;">Project Name:</span>
                    <strong style="font-size: 12px; color: #111;">${project}</strong>
                </div>
                <div style="margin-top: 1px;">
                    <span style="font-size: 9px; text-transform: uppercase; color: #666; font-weight: bold; display: block;">Requested By:</span>
                    <strong style="font-size: 11.5px;">${requestor}</strong>
                </div>
                <div style="margin-top: 1px; text-align: right;">
                    <span style="font-size: 9px; text-transform: uppercase; color: #666; font-weight: bold; display: block;">Received By (Pickup):</span>
                    <strong style="font-size: 11.5px; color: #008800;">${receivedBy}</strong>
                </div>
            </div>

            <!-- ITEMS TABLE -->
            <div style="margin-bottom: 6px;">
                <div style="font-size: 9.5px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; color: #444;">Released Material Items</div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #212529; color: #ffffff;">
                            <th style="padding: 3.5px 6px; border: 1px solid #000; text-align: left; width: 25%;">Item Code</th>
                            <th style="padding: 3.5px 6px; border: 1px solid #000; text-align: left;">Item Description / Name</th>
                            <th style="padding: 3.5px 6px; border: 1px solid #000; text-align: right; width: 25%;">Qty Released</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsRowsHtml}
                    </tbody>
                </table>
            </div>

            <!-- PHOTO PROOF BLOCK (IF AVAILABLE) -->
            ${photoBlockHtml}

            <!-- REMARKS -->
            <div style="margin-bottom: 6px;">
                <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; color: #444;">Remarks / Notes:</div>
                <div style="padding: 3px 6px; border: 1px solid #ccc; background: #fff; font-size: 10px; border-radius: 4px; line-height: 1.2;">${remarks}</div>
            </div>

            <!-- SIGNATURE FORM FOOTER -->
            <div style="display: flex; justify-content: space-between; margin-top: 10px; padding-top: 6px; border-top: 1px solid #888; page-break-inside: avoid;">
                <div style="text-align: center; width: 45%; position: relative;">
                    <div style="min-height: 32px; display: flex; align-items: flex-end; justify-content: center;">
                        ${releaserSigSrc ? `<img src="${releaserSigSrc}" style="max-height: 48px; max-width: 180px; object-fit: contain; background: transparent; margin-bottom: -15px; position: relative; z-index: 2;">` : '<div style="height: 32px;"></div>'}
                    </div>
                    <div style="font-weight: bold; border-top: 1px solid #000; padding-top: 2px; font-size: 11px; text-transform: uppercase; position: relative; z-index: 1;">${releaser}</div>
                    <div style="font-size: 8.5px; color: #555; text-transform: uppercase; font-weight: bold; margin-top: 1px;">Released By (Warehouse Officer)</div>
                </div>
                <div style="text-align: center; width: 45%; position: relative;">
                    <div style="min-height: 32px; display: flex; align-items: flex-end; justify-content: center;">
                        ${receiverSigSrc ? `<img src="${receiverSigSrc}" style="max-height: 48px; max-width: 180px; object-fit: contain; background: transparent; margin-bottom: -15px; position: relative; z-index: 2;">` : '<div style="height: 32px;"></div>'}
                    </div>
                    <div style="font-weight: bold; border-top: 1px solid #000; padding-top: 2px; font-size: 11px; text-transform: uppercase; position: relative; z-index: 1;">${receivedBy}</div>
                    <div style="font-size: 8.5px; color: #555; text-transform: uppercase; font-weight: bold; margin-top: 1px;">Received By (Authorized Recipient)</div>
                </div>
            </div>

            <!-- CRYPTOGRAPHIC SEAL & VERIFICATION QR (Clean Minimalist PKI Style) -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; min-width: 28px; border-radius: 50%; background-color: #d1e7dd; color: #0f5132; font-size: 13px; font-weight: bold;">
                        ✓
                    </div>
                    <div>
                        <div style="font-weight: bold; color: #0f172a; font-size: 9px; letter-spacing: -0.2px;">
                            Certified Document
                        </div>
                        <div style="color: #64748b; font-size: 7.5px; margin-top: 2px;">
                            <em>Scan QR code for tamper-evident audit trail</em>
                        </div>
                    </div>
                </div>
                <div style="text-align: right; padding-left: 8px;">
                    <img src="${qrSrc}" style="width: 48px; height: 48px; border: 1px solid #cbd5e1; padding: 1px; background: #fff; border-radius: 4px; object-fit: contain;">
                </div>
            </div>
        </div>
    `;

    const rowCount = tbodySource ? tbodySource.querySelectorAll('tr').length : 1;
    const isDense = rowCount >= 6 && rowCount <= 12;
    const isMultiPage = rowCount > 12;

    const printWindow = window.open('', '_blank', 'width=850,height=900');
    if (!printWindow) {
        alert("Pop-up blocked. Please allow pop-ups for this site to print.");
        return;
    }
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Material Withdrawal Slip - ${wdNo}</title>
            <style>
                @page { size: 210mm 148.5mm; margin: 4mm 6mm; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box; }
                thead { display: table-header-group; }
                tr { page-break-inside: avoid; }
                body {
                    font-family: Arial, sans-serif;
                    background: #ffffff;
                    color: #000;
                    margin: 0;
                    padding: 0;
                    font-size: ${isDense ? '9.5px' : '11px'};
                }
                #printWdVoucher {
                    width: 100% !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    ${isMultiPage ? '' : 'page-break-inside: avoid;'}
                }
                td, th {
                    padding: ${isDense ? '2px 4px' : '3.5px 6px'} !important;
                }
            </style>
        </head>
        <body>
            <div id="printWdWrapper">
                ${printContent}
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.focus();
                        window.print();
                    }, 350);
                };
                window.onafterprint = function() {
                    setTimeout(function() {
                        window.close();
                    }, 200);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
};

// 2. DYNAMIC ROWS FOR MANUAL WITHDRAWALS
if (!window.wdGlobalListenersAttached) {
    window.wdGlobalListenersAttached = true;

    document.body.addEventListener('click', function (e) {
        const container = document.getElementById('wdMaterialsContainer');
        if (!container) return;

        // Add Row
        if (e.target.closest('#addWdMaterialBtn')) {
            const firstRow = container.querySelector('.wd-material-row');
            if (firstRow) {
                const newRow = firstRow.cloneNode(true);
                newRow.querySelector('select').value = '';
                newRow.querySelector('input[type="number"]').value = '';
                newRow.querySelector('.remove-wd-row').disabled = false;
                container.appendChild(newRow);
                window.updateWdDeleteButtons(container);
            }
        }

        // Remove Row
        if (e.target.closest('.remove-wd-row')) {
            const rowToRemove = e.target.closest('.wd-material-row');
            if (container.querySelectorAll('.wd-material-row').length > 1) {
                rowToRemove.remove();
                window.updateWdDeleteButtons(container);
            }
        }
    });
}

window.updateWdDeleteButtons = function (container) {
    const rows = container.querySelectorAll('.wd-material-row');
    if (rows.length === 1) {
        rows[0].querySelector('.remove-wd-row').disabled = true;
    } else {
        rows.forEach(row => row.querySelector('.remove-wd-row').disabled = false);
    }
};

// 3. THE MAGIC RS SCANNER ENGINE
window.html5RsScanner = window.html5RsScanner || null;

window.startRsScanner = function () {
    const scannerEl = document.getElementById('rsScannerModal');
    if (scannerEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(scannerEl);
        modal.show();
    }

    document.getElementById('rsReader').style.display = 'block';
    document.getElementById('rsScannerResult').innerHTML = "Point your camera at the Approved RS Document QR Code...";

    if (!window.html5RsScanner) {
        window.html5RsScanner = new Html5QrcodeScanner("rsReader", { fps: 10, qrbox: { width: 250, height: 250 } }, false);
    }

    window.html5RsScanner.render((decodedText) => {
        // Only accept valid RS QR Codes
        if (decodedText.startsWith("REQ-DATA:")) {
            new Audio('assets/sounds/scan.mp3').play().catch(e => { });

            // Stop Camera
            window.html5RsScanner.clear().then(() => { window.html5RsScanner = null; }).catch(e => { });
            document.getElementById('rsReader').style.display = 'none';
            document.getElementById('rsScannerResult').innerHTML = `<span class="spinner-border spinner-border-sm me-2 text-primary"></span>Fetching RS Data from Server...`;

            // Fetch data from backend via AJAX
            window.loadRsDataToWithdrawalForm(decodedText, function (success, msg) {
                if (success) {
                    if (scannerEl) {
                        const scannerInstance = bootstrap.Modal.getInstance(scannerEl);
                        if (scannerInstance) scannerInstance.hide();
                    }
                    setTimeout(() => {
                        const withdrawModalEl = document.getElementById('withdrawModal');
                        if (withdrawModalEl) {
                            bootstrap.Modal.getOrCreateInstance(withdrawModalEl).show();
                        }
                    }, 400);
                } else {
                    document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-x-circle me-1"></i> ${msg}</span>`;
                }
            });
        } else {
            document.getElementById('rsScannerResult').innerHTML = `<span class="text-danger fw-bold">Invalid QR Code format. Scan an RS Document.</span>`;
        }
    }, (error) => { });
};

window.stopRsScanner = function () {
    if (window.html5RsScanner) {
        window.html5RsScanner.clear().then(() => { window.html5RsScanner = null; }).catch(e => { });
    }
};

// Reset form and container on modal hidden (SPA Router Safe)
function setupWithdrawalModalListeners() {
    const withdrawModalEl = document.getElementById('withdrawModal');
    if (withdrawModalEl) {
        if (withdrawModalEl.dataset.listenerAttached) return; // avoid duplicate binding
        withdrawModalEl.dataset.listenerAttached = "true";

        const container = document.getElementById('wdMaterialsContainer');
        let wdRowTemplate = null;
        if (container) {
            const firstRow = container.querySelector('.wd-material-row');
            if (firstRow) {
                wdRowTemplate = firstRow.cloneNode(true);
                wdRowTemplate.querySelector('select').value = '';
                wdRowTemplate.querySelector('input[type="number"]').value = '';
                wdRowTemplate.querySelector('.remove-wd-row').disabled = true;
            }
        }

        withdrawModalEl.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('withdrawalForm');
            if (form) form.reset();
            const rsNoField = document.getElementById('wdRsNo');
            if (rsNoField) rsNoField.value = '';

            // Restore default template row in materials container
            if (container && wdRowTemplate) {
                container.innerHTML = '';
                container.appendChild(wdRowTemplate.cloneNode(true));
                window.updateWdDeleteButtons(container);
            }
        });
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupWithdrawalModalListeners);
} else {
    setupWithdrawalModalListeners();
}

// 4. INITIALIZE PAGE
function initWithdrawalsPage() {
    // Real-time Search
    document.getElementById('searchWithdrawals')?.addEventListener('keyup', function (e) {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('.withdrawal-row');
        rows.forEach(row => {
            const slip = row.querySelector('.wd-slip').textContent.toLowerCase();
            const proj = row.querySelector('.wd-project').textContent.toLowerCase();
            row.style.display = (slip.includes(term) || proj.includes(term)) ? '' : 'none';
        });
    });

    // Pagination
    const table = document.getElementById('withdrawalsTable');
    if (!table) return;
    if (table.parentElement.querySelector('.pagination-wrapper')) return;

    const rowsPerPage = 10;
    const rows = Array.from(table.querySelectorAll('tbody .withdrawal-row'));
    if (rows.length <= rowsPerPage) return;

    let currentPage = 1;
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex justify-content-between align-items-center p-3 bg-white border-top pagination-wrapper';

    const info = document.createElement('span'); info.className = 'text-muted small fw-bold';
    const btnGroup = document.createElement('div'); btnGroup.className = 'btn-group shadow-sm';

    const prev = document.createElement('button'); prev.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; prev.innerHTML = 'Prev';
    const indicator = document.createElement('button'); indicator.className = 'btn btn-sm btn-brand fw-bold px-3 pe-none';
    const next = document.createElement('button'); next.className = 'btn btn-sm btn-outline-primary fw-bold px-3'; next.innerHTML = 'Next';

    btnGroup.append(prev, indicator, next);
    wrapper.append(info, btnGroup);
    table.parentElement.appendChild(wrapper);

    function showPage(page) {
        currentPage = page;
        const start = (page - 1) * rowsPerPage; const end = start + rowsPerPage;
        rows.forEach((row, index) => { row.style.display = (index >= start && index < end) ? '' : 'none'; });
        info.innerHTML = `Showing <b>${start + 1}</b> to <b>${Math.min(end, rows.length)}</b>`;
        indicator.innerText = `Page ${page} / ${totalPages}`;
        prev.disabled = page === 1; next.disabled = page === totalPages;
    }

    prev.addEventListener('click', () => { if (currentPage > 1) showPage(currentPage - 1); });
    next.addEventListener('click', () => { if (currentPage < totalPages) showPage(currentPage + 1); });
    showPage(1);

    // Check URL parameters for shortcut auto-open modals
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'new') {
        const withdrawModalEl = document.getElementById('withdrawModal');
        if (withdrawModalEl) {
            new bootstrap.Modal(withdrawModalEl).show();
        }
    }
}

// Global Manual RS Lookup Helper
window.loadRsDataToWithdrawalForm = function (rsNo, callback) {
    const feedbackEl = document.getElementById('manualRsStatusFeedback');
    if (feedbackEl) {
        feedbackEl.classList.remove('d-none', 'text-danger', 'text-success');
        feedbackEl.classList.add('text-muted');
        feedbackEl.innerHTML = `<span class="spinner-border spinner-border-sm me-2 text-primary"></span>Fetching RS ${rsNo}...`;
    }

    let formData = new FormData();
    formData.append('action', 'fetch_rs_data');
    formData.append('rs_no', rsNo);

    fetch('process/process.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('wdProjectName').value = data.project_name;
                if (data.rs_status === 'Staged (Ready for Pickup)') {
                    document.getElementById('wdRemarks').value = "Pre-picked & Staged Express Pickup for " + data.rs_no;
                } else {
                    document.getElementById('wdRemarks').value = "Auto-filled via RS Lookup for " + data.rs_no;
                }

                let rsNoField = document.getElementById('wdRsNo');
                if (!rsNoField) {
                    rsNoField = document.createElement('input');
                    rsNoField.type = 'hidden';
                    rsNoField.name = 'rs_no';
                    rsNoField.id = 'wdRsNo';
                    const form = document.getElementById('withdrawalForm');
                    if (form) form.appendChild(rsNoField);
                }
                if (rsNoField) rsNoField.value = data.rs_no;

                const reqDisplay = document.getElementById('wdRequestorDisplay');
                if (reqDisplay) reqDisplay.value = data.requestor_name || 'N/A';
                const projInput = document.getElementById('wdProjectName');
                if (projInput) projInput.value = data.project_name;
                const projDisplay = document.getElementById('wdProjectNameDisplay');
                if (projDisplay) projDisplay.value = data.project_name;

                const container = document.getElementById('wdMaterialsContainer');
                container.innerHTML = '';

                data.items.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'row g-2 wd-material-row mb-2 align-items-center bg-white p-2 rounded border shadow-sm mx-0';
                    row.innerHTML = `
                    <div class="col-md-8">
                        <input type="text" class="form-control fw-bold text-dark bg-white" value="[${item.item_code}] ${item.item_name}" readonly>
                        <input type="hidden" name="items[]" value="${item.item_code}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" class="form-control fw-bold text-center text-danger bg-white" name="quantities[]" value="${item.quantity}" readonly>
                    </div>
                `;
                    container.appendChild(row);
                });

                const addBtn = document.getElementById('addWdMaterialBtn');
                if (addBtn) addBtn.style.display = 'none';

                if (feedbackEl) {
                    feedbackEl.classList.remove('text-muted', 'text-danger');
                    feedbackEl.classList.add('text-success');
                    feedbackEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Loaded ${data.rs_no} (${data.rs_status}) successfully! ${data.items.length} item(s) auto-filled.`;
                }

                if (callback) callback(true, 'Success');
            } else {
                if (feedbackEl) {
                    feedbackEl.classList.remove('text-muted', 'text-success');
                    feedbackEl.classList.add('text-danger');
                    feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'RS not found or invalid.'}`;
                }

                if (callback) callback(false, data.message || 'RS not found or invalid.');
            }
        })
        .catch(err => {
            if (feedbackEl) {
                feedbackEl.classList.remove('text-muted', 'text-success');
                feedbackEl.classList.add('text-danger');
                feedbackEl.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> Server error fetching RS data.`;
            }
            if (callback) callback(false, 'Server error fetching RS data.');
        });
};

window.lookupManualRsInput = function () {
    const inputVal = document.getElementById('manualRsInputText')?.value.trim();
    if (!inputVal) {
        alert("Please enter or select an RS Number.");
        return;
    }
    window.loadRsDataToWithdrawalForm(inputVal);
};

// Signature Pad & Photo Proof Initialization
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;
        const placeholder = document.getElementById('sigPlaceholder');
        const sigDataInput = document.getElementById('signatureData');
        const clearBtn = document.getElementById('clearSignatureBtn');

        function ensureCanvasSize() {
            const rect = canvas.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0 && (canvas.width === 0 || canvas.height === 0 || canvas.width !== Math.round(rect.width))) {
                canvas.width = Math.round(rect.width);
                canvas.height = Math.round(rect.height);
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#000000';
            }
        }

        const withdrawModalEl = document.getElementById('withdrawModal');
        if (withdrawModalEl) {
            withdrawModalEl.addEventListener('shown.bs.modal', function () {
                setTimeout(ensureCanvasSize, 100);
            });
        }

        function getPos(e) {
            ensureCanvasSize();
            const rect = canvas.getBoundingClientRect();
            const clientX = (e.touches && e.touches.length > 0) ? e.touches[0].clientX : e.clientX;
            const clientY = (e.touches && e.touches.length > 0) ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * (canvas.width / (rect.width || 1)),
                y: (clientY - rect.top) * (canvas.height / (rect.height || 1))
            };
        }

        function startDrawing(e) {
            isDrawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            if (placeholder) placeholder.style.display = 'none';
            hasSignature = true;
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getPos(e);
            ctx.lineWidth = 2.5;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#000000';
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }

        function stopDrawing() {
            if (isDrawing) {
                isDrawing = false;
                if (hasSignature && sigDataInput) {
                    sigDataInput.value = canvas.toDataURL('image/png');
                }
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.beginPath();
                hasSignature = false;
                if (sigDataInput) sigDataInput.value = '';
                if (placeholder) placeholder.style.display = '';
            });
        }
    }

    // Fullscreen Signature Pad logic
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#openFullSigBtn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            const fullModalEl = document.getElementById('fullSigModal');
            if (fullModalEl) {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(fullModalEl);
                modalInstance.show();
            }
        }
    });

    const fullModalEl = document.getElementById('fullSigModal');
    const fullCanvas = document.getElementById('fullSigCanvas');

    if (fullModalEl && fullCanvas) {
        const fullCtx = fullCanvas.getContext('2d');
        let fullIsDrawing = false;
        let fullHasSignature = false;
        const fullPlaceholder = document.getElementById('fullSigPlaceholder');
        const clearFullBtn = document.getElementById('clearFullSigBtn');
        const saveFullBtn = document.getElementById('saveFullSigBtn');

        fullModalEl.addEventListener('shown.bs.modal', function () {
            const botContainer = document.getElementById('cims-chatbot-container');
            if (botContainer) botContainer.style.setProperty('display', 'none', 'important');

            const rect = fullCanvas.getBoundingClientRect();
            const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;

            if (isRotated) {
                fullCanvas.width = rect.height;
                fullCanvas.height = rect.width;
            } else {
                fullCanvas.width = rect.width;
                fullCanvas.height = rect.height;
            }

            fullCtx.clearRect(0, 0, fullCanvas.width, fullCanvas.height);
            fullCtx.lineWidth = 3;
            fullCtx.lineCap = 'round';
            fullCtx.lineJoin = 'round';
            fullCtx.strokeStyle = '#000000';

            fullHasSignature = false;
            if (fullPlaceholder) fullPlaceholder.style.display = '';

            // Attempt screen orientation lock to landscape on mobile devices
            if (screen.orientation && screen.orientation.lock) {
                screen.orientation.lock('landscape').catch(() => { });
            } else if (screen.lockOrientation) {
                screen.lockOrientation('landscape');
            }
        });

        fullModalEl.addEventListener('hidden.bs.modal', function () {
            const botContainer = document.getElementById('cims-chatbot-container');
            if (botContainer) botContainer.style.removeProperty('display');

            if (screen.orientation && screen.orientation.unlock) {
                try { screen.orientation.unlock(); } catch (e) { }
            } else if (screen.unlockOrientation) {
                try { screen.unlockOrientation(); } catch (e) { }
            }

            // Restore modal-open state so withdrawModal stays active and scrollable
            const withdrawModalEl = document.getElementById('withdrawModal');
            if (withdrawModalEl && (withdrawModalEl.classList.contains('show') || withdrawModalEl.style.display === 'block')) {
                document.body.classList.add('modal-open');
                if (!document.querySelector('.modal-backdrop')) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
            }
        });

        function getFullPos(e) {
            const rect = fullCanvas.getBoundingClientRect();
            if ((fullCanvas.width === 0 || fullCanvas.height === 0) && rect.width > 0 && rect.height > 0) {
                const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;
                if (isRotated) {
                    fullCanvas.width = Math.round(rect.height);
                    fullCanvas.height = Math.round(rect.width);
                } else {
                    fullCanvas.width = Math.round(rect.width);
                    fullCanvas.height = Math.round(rect.height);
                }
                fullCtx.lineWidth = 3;
                fullCtx.lineCap = 'round';
                fullCtx.lineJoin = 'round';
                fullCtx.strokeStyle = '#000000';
            }
            const clientX = (e.touches && e.touches.length > 0) ? e.touches[0].clientX : e.clientX;
            const clientY = (e.touches && e.touches.length > 0) ? e.touches[0].clientY : e.clientY;

            const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;

            if (isRotated) {
                const x = (clientY - rect.top) * (fullCanvas.width / (rect.height || 1));
                const y = (rect.right - clientX) * (fullCanvas.height / (rect.width || 1));
                return { x: x, y: y };
            } else {
                return {
                    x: (clientX - rect.left) * (fullCanvas.width / (rect.width || 1)),
                    y: (clientY - rect.top) * (fullCanvas.height / (rect.height || 1))
                };
            }
        }

        function startFullDrawing(e) {
            fullIsDrawing = true;
            const pos = getFullPos(e);
            fullCtx.beginPath();
            fullCtx.moveTo(pos.x, pos.y);
            if (fullPlaceholder) fullPlaceholder.style.display = 'none';
            fullHasSignature = true;
        }

        function drawFull(e) {
            if (!fullIsDrawing) return;
            e.preventDefault();
            const pos = getFullPos(e);
            fullCtx.lineWidth = 3;
            fullCtx.lineCap = 'round';
            fullCtx.lineJoin = 'round';
            fullCtx.strokeStyle = '#000000';
            fullCtx.lineTo(pos.x, pos.y);
            fullCtx.stroke();
        }

        function stopFullDrawing() {
            if (fullIsDrawing) {
                fullIsDrawing = false;
                fullCtx.beginPath();
            }
        }

        fullCanvas.addEventListener('mousedown', startFullDrawing);
        fullCanvas.addEventListener('mousemove', drawFull);
        fullCanvas.addEventListener('mouseup', stopFullDrawing);
        fullCanvas.addEventListener('mouseleave', stopFullDrawing);

        fullCanvas.addEventListener('touchstart', startFullDrawing, { passive: false });
        fullCanvas.addEventListener('touchmove', drawFull, { passive: false });
        fullCanvas.addEventListener('touchend', stopFullDrawing);

        if (clearFullBtn) {
            clearFullBtn.addEventListener('click', function () {
                fullCtx.clearRect(0, 0, fullCanvas.width, fullCanvas.height);
                fullCtx.beginPath();
                fullHasSignature = false;
                if (fullPlaceholder) fullPlaceholder.style.display = '';
            });
        }

        const cancelFullBtn = document.getElementById('cancelFullSigBtn');
        if (cancelFullBtn) {
            cancelFullBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const modalInstance = bootstrap.Modal.getInstance(fullModalEl);
                if (modalInstance) modalInstance.hide();
            });
        }

        if (saveFullBtn) {
            saveFullBtn.addEventListener('click', function () {
                if (!fullHasSignature) {
                    alert("Please sign on the canvas first.");
                    return;
                }
                const dataUrl = fullCanvas.toDataURL('image/png');

                const sigDataInput = document.getElementById('signatureData');
                const drawnPreviewWrap = document.getElementById('wdSigDrawnPreview');
                const previewImg = document.getElementById('wdSigPreviewImg');

                if (sigDataInput) sigDataInput.value = dataUrl;
                if (previewImg) previewImg.src = dataUrl;
                if (drawnPreviewWrap) drawnPreviewWrap.classList.remove('d-none');

                // Hide modal
                const modalInstance = bootstrap.Modal.getInstance(fullModalEl);
                if (modalInstance) modalInstance.hide();
            });
        }

        const clearWdDrawnBtn = document.getElementById('clearWdDrawnSigBtn');
        if (clearWdDrawnBtn) {
            clearWdDrawnBtn.addEventListener('click', function () {
                const sigDataInput = document.getElementById('signatureData');
                const drawnPreviewWrap = document.getElementById('wdSigDrawnPreview');
                const previewImg = document.getElementById('wdSigPreviewImg');

                if (sigDataInput) sigDataInput.value = '';
                if (previewImg) previewImg.src = '';
                if (drawnPreviewWrap) drawnPreviewWrap.classList.add('d-none');
                fullHasSignature = false;
            });
        }
    }

    // Photo proof preview logic
    const photoInput = document.getElementById('photoProofInput');
    const photoPreview = document.getElementById('photoProofPreview');
    const photoContainer = document.getElementById('photoProofPreviewContainer');
    if (photoInput && photoPreview && photoContainer) {
        photoInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    photoPreview.src = e.target.result;
                    photoContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                photoContainer.classList.add('d-none');
            }
        });
    }

    // Form Submit Signature Check
    const form = document.getElementById('withdrawalForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const sigInput = document.getElementById('signatureData');
            if (sigInput && (!sigInput.value || sigInput.value.trim() === '')) {
                e.preventDefault();
                alert('Please ask the receiver to sign in the Digital Signature pad before confirming release.');
                return false;
            }
        });
    }

    // Modal Reset Handler on Close
    const withdrawModalEl = document.getElementById('withdrawModal');
    if (withdrawModalEl) {
        withdrawModalEl.addEventListener('hidden.bs.modal', function () {
            const recInput = document.getElementById('wdReceivedBy');
            if (recInput) recInput.value = '';
            const reqDisplay = document.getElementById('wdRequestorDisplay');
            if (reqDisplay) reqDisplay.value = '';
            const projInput = document.getElementById('wdProjectName');
            if (projInput) projInput.value = '';
            const projDisplay = document.getElementById('wdProjectNameDisplay');
            if (projDisplay) projDisplay.value = '';
            const container = document.getElementById('wdMaterialsContainer');
            if (container) {
                container.innerHTML = `
                    <div class="text-center text-muted py-3 fw-bold" id="emptyWdPrompt">
                        <i class="bi bi-info-circle me-1"></i> Type an approved RS Number above to load release items.
                    </div>
                `;
            }
            const feedbackEl = document.getElementById('manualRsStatusFeedback');
            if (feedbackEl) feedbackEl.classList.add('d-none');
            const rsInput = document.getElementById('manualRsInputText');
            if (rsInput) rsInput.value = '';

            // Clear signature canvas
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const placeholder = document.getElementById('sigPlaceholder');
                if (placeholder) placeholder.style.display = '';
                const sigDataInput = document.getElementById('signatureData');
                if (sigDataInput) sigDataInput.value = '';
            }

            // Clear photo proof preview
            if (photoInput) photoInput.value = '';
            if (photoContainer) photoContainer.classList.add('d-none');
        });
    }
});

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWithdrawalsPage);
} else {
    initWithdrawalsPage();
}