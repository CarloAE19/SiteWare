/* ==========================================================
 * GB INVENTORY - SMART NOTIFICATIONS LOGIC
 * Handles marking notifications as read and routing
 * ========================================================== */

async function readNotifAndNavigate(notifId, url) {
    let formData = new FormData();
    formData.append('action', 'read_notif');
    formData.append('notif_id', notifId);
    
    try {
        // FIXED: Sends the request to the new dedicated Notification Processor
        await fetch('process/process_notif.php', { method: 'POST', body: formData });
    } catch(e) { 
        console.error(e); 
    } 
    
    // Instantly redirect the user to the correct page
    window.location.href = url;
}

async function markAllNotifsRead() {
    let formData = new FormData();
    formData.append('action', 'read_all_notifs');
    
    try {
        // FIXED: Sends the request to the new dedicated Notification Processor
        await fetch('process/process_notif.php', { method: 'POST', body: formData });
        window.location.reload(); // Refresh to remove the red badge
    } catch(e) {
        alert("Network Error: Could not connect to server.");
    }
}