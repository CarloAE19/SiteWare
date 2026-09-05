/* ==========================================================
 * GB INVENTORY - SMART NOTIFICATIONS LOGIC
 * Handles marking notifications as read and routing
 * ========================================================== */

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function readNotifAndNavigate(notifId, url) {
    const csrfToken = getCsrfToken();
    let formData = new FormData();
    formData.append('action', 'read_notif');
    formData.append('notif_id', notifId);
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    try {
        await fetch('process/process_notif.php', { 
            method: 'POST', 
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        });
    } catch(e) { 
        console.error(e); 
    } 
    
    // Instantly redirect the user to the correct page
    window.location.href = url;
}

async function markAllNotifsRead() {
    const csrfToken = getCsrfToken();
    let formData = new FormData();
    formData.append('action', 'read_all_notifs');
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    try {
        const response = await fetch('process/process_notif.php', { 
            method: 'POST', 
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        });
        const res = await response.json();
        if (res.status === 'success') {
            window.location.reload();
        } else {
            throw new Error(res.message || 'Failed to mark notifications as read.');
        }
    } catch(e) {
        alert(e.message || "Network Error: Could not connect to server.");
    }
}