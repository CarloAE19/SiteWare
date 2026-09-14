/* ==========================================================
 * GB INVENTORY - SMART NOTIFICATIONS LOGIC
 * Handles in-place AJAX marking as read, badge sync & quick view
 * ========================================================== */

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function markSingleNotifRead(notifId, el) {
    if (!notifId) return;
    const itemEl = el ? el.closest('.system-notif-item') : document.querySelector(`.system-notif-item[data-notif-id="${notifId}"]`);
    if (itemEl && itemEl.dataset.read === '1') return; // already read

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

        if (itemEl) {
            itemEl.dataset.read = '1';
            itemEl.classList.remove('bg-light', 'bg-danger-subtle', 'bg-warning-subtle', 'bg-success-subtle', 'bg-opacity-10', 'border-start', 'border-3', 'border-4', 'border-danger', 'border-warning', 'border-success', 'border-primary', 'border-info');
            itemEl.classList.add('bg-white');
            const unreadDot = itemEl.querySelector('.notif-unread-dot');
            if (unreadDot) unreadDot.remove();
        }

        const badge = document.getElementById('systemNotifBadge');
        if (badge) {
            let count = parseInt(badge.textContent || '0', 10);
            if (count > 1) {
                badge.textContent = count - 1;
            } else {
                badge.textContent = '0';
                badge.classList.add('d-none');
                const markBtn = document.getElementById('markAllNotifsBtn');
                if (markBtn) markBtn.classList.add('d-none');
            }
        }
    } catch (e) {
        console.error('Failed to mark notification as read:', e);
    }
}

async function readNotifAndNavigate(notifId, url) {
    await markSingleNotifRead(notifId);
    if (url) {
        window.location.href = url;
    }
}

async function markAllNotifsRead() {
    const csrfToken = getCsrfToken();
    let formData = new FormData();
    formData.append('action', 'read_all_notifs');
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    }
    
    const markBtn = document.getElementById('markAllNotifsBtn');
    const origBtnHtml = markBtn ? markBtn.innerHTML : '<i class="bi bi-check2-all me-1"></i>Mark read';
    if (markBtn) {
        markBtn.disabled = true;
        markBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Updating...';
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
            // Update UI in-place without jarring full page reload
            const badge = document.getElementById('systemNotifBadge');
            if (badge) {
                badge.textContent = '0';
                badge.classList.add('d-none');
            }

            document.querySelectorAll('.system-notif-item').forEach(el => {
                el.dataset.read = '1';
                el.classList.remove('bg-light', 'bg-danger-subtle', 'bg-warning-subtle', 'bg-success-subtle', 'bg-opacity-10', 'border-start', 'border-3', 'border-4', 'border-danger', 'border-warning', 'border-success', 'border-primary', 'border-info');
                el.classList.add('bg-white');
                const unreadDot = el.querySelector('.notif-unread-dot');
                if (unreadDot) unreadDot.remove();
            });

            if (markBtn) {
                markBtn.classList.add('d-none');
            }

            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'All notifications marked as read'
                });
            }
        } else {
            throw new Error(res.message || 'Failed to mark notifications as read.');
        }
    } catch(e) {
        alert(e.message || "Network Error: Could not connect to server.");
        if (markBtn) {
            markBtn.disabled = false;
            markBtn.innerHTML = origBtnHtml;
        }
    }
}