/* ==========================================================
 * GB INVENTORY - SCANNER & SYNC ENGINE
 * Handles QR scanning, Label Printing, AJAX, and Live Sync
 * ========================================================== */

// --- MULTI-DEVICE LIVE SYNC (BACKGROUND POLLING) ---
document.addEventListener("DOMContentLoaded", () => {
    setInterval(async () => {
        if (!document.getElementById('startReceiveScannerBtn')) return; // Only run on inventory page
        try {
            let formData = new FormData();
            formData.append('action', 'live_sync');
            
            const response = await fetch('process/process.php', { method: 'POST', body: formData });
            const liveData = await response.json();
            
            liveData.forEach(item => {
                let qtyEl = document.getElementById('qty_' + item.item_code);
                let statusEl = document.getElementById('status_' + item.item_code);
                
                if (qtyEl && parseInt(qtyEl.innerText) !== parseInt(item.quantity)) {
                    qtyEl.innerText = item.quantity;
                    qtyEl.className = 'fw-bold fs-5 text-primary'; 
                    setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                    
                    if (statusEl) {
                        statusEl.innerText = item.status;
                        if(item.status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                        else if(item.status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                        else statusEl.className = 'badge bg-success';
                    }
                }
            });
        } catch (e) {
            // Silently ignore network errors
        }
    }, 3000); 
});

// --- QR SCANNER & PRINTING LOGIC ---
let receiveScanner;

window.resetReceiveScanner = function() {
    document.getElementById('receive-qr-reader').style.display = 'block';
    document.getElementById('receiveFormContainer').style.display = 'none';
    if (receiveScanner) receiveScanner.clear();
    
    if (typeof Html5QrcodeScanner !== 'undefined') {
        receiveScanner = new Html5QrcodeScanner("receive-qr-reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
        receiveScanner.render(window.onReceiveScanSuccess);
    }
}

window.stopReceiveScanner = function() {
    if (receiveScanner) receiveScanner.clear();
}

window.onReceiveScanSuccess = function(decodedText) {
    let scanAudio = new Audio('assets/sounds/scan.mp3');
    scanAudio.play().catch(e => console.log("Audio play blocked"));
    
    let foundItem = typeof inventoryData !== 'undefined' ? inventoryData.find(item => item.item_code === decodedText) : null;
    
    if(foundItem) {
        document.getElementById('scannedItemDisplayName').innerText = foundItem.item_name + " (" + foundItem.item_code + ")";
        document.getElementById('scannedInputCode').value = decodedText;
        
        receiveScanner.clear();
        document.getElementById('receive-qr-reader').style.display = 'none';
        document.getElementById('receiveFormContainer').style.display = 'block';
        setTimeout(() => document.querySelector('input[name="added_qty"]').focus(), 500);
    } else {
        alert("Scanned item (" + decodedText + ") not found in inventory!");
    }
}

window.showItemQR = function(itemCode, itemName) {
    document.getElementById('qrItemCode').innerText = itemCode;
    document.getElementById('qrItemName').innerText = itemName;
    document.getElementById('qrItemImg').src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${itemCode}`;
    new bootstrap.Modal(document.getElementById('itemQrModal')).show();
}

window.printItemLabel = function() {
    const printContent = document.getElementById('qrPrintArea').innerHTML;
    const originalContent = document.body.innerHTML;
    document.body.innerHTML = `<div style="display:flex; justify-content:center; align-items:center; height:100vh;">${printContent}</div>`;
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload(); 
}

// --- AJAX QR STOCK-IN LOGIC ---
window.submitReceiveAjax = async function(e) {
    e.preventDefault(); 
    const form = e.target;
    const btn = document.getElementById('receiveSubmitBtn');
    const successMsg = document.getElementById('ajaxSuccessMsg');
    const originalBtnText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving to Database...';
    
    const formData = new FormData(form);
    const itemCode = formData.get('item_code');
    const addedQty = formData.get('added_qty');
    
    try {
        const response = await fetch('process/process.php', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.status === 'success') {
            const qtyEl = document.getElementById('qty_' + itemCode);
            const statusEl = document.getElementById('status_' + itemCode);
            
            if (qtyEl && statusEl) {
                qtyEl.innerText = data.new_qty;
                qtyEl.className = 'fw-bold fs-5 text-success'; 
                setTimeout(() => { qtyEl.className = 'fw-bold fs-6'; }, 2000);
                
                statusEl.innerText = data.new_status;
                if(data.new_status === 'Out of Stock') statusEl.className = 'badge bg-danger';
                else if(data.new_status === 'Low Stock') statusEl.className = 'badge bg-warning text-dark';
                else statusEl.className = 'badge bg-success';
            }
            
            let foundItem = typeof inventoryData !== 'undefined' ? inventoryData.find(item => item.item_code === itemCode) : null;
            if(foundItem) foundItem.quantity = data.new_qty;
            
            let successAudio = new Audio('assets/sounds/success.mp3');
            successAudio.play().catch(e => console.log("Audio play blocked."));
            successMsg.innerHTML = `<i class="bi bi-check-circle-fill me-2 fs-5"></i> Successfully added +${addedQty} units!`;
            successMsg.classList.remove('d-none');
            
            setTimeout(() => {
                successMsg.classList.add('d-none');
                form.reset();
                window.resetReceiveScanner();
            }, 1500);

        } else {
            alert("❌ Server Error: " + data.message);
        }
    } catch (error) {
        alert("❌ Network Error. The database could not be reached.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalBtnText;
    }
}