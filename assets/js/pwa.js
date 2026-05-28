/* ==========================================================
 * GB INVENTORY - PROGRESSIVE WEB APP
 * Handles the custom "Install App" mobile banner
 * ========================================================== */

document.addEventListener("DOMContentLoaded", () => {
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        if(document.getElementById('pwa-install-banner')) return;

        const installBanner = document.createElement('div');
        installBanner.id = 'pwa-install-banner';
        installBanner.className = 'position-fixed bottom-0 start-50 translate-middle-x w-100 p-3 shadow-lg bg-white border-top';
        installBanner.style.maxWidth = '600px';
        installBanner.style.borderTopLeftRadius = '20px';
        installBanner.style.borderTopRightRadius = '20px';
        installBanner.style.zIndex = '99999';
        installBanner.style.transition = 'transform 0.3s ease-out';
        
        installBanner.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center text-start">
                    <img src="assets/LogoGB.png" alt="Logo" width="45" height="45" class="me-3 rounded shadow-sm border">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark" style="font-size: 1rem;">GB Inventory</h6>
                        <small class="text-muted" style="font-size: 0.8rem;">Install app for quick access</small>
                    </div>
                </div>
                <div>
                    <button class="btn btn-sm btn-light text-muted me-1 fw-bold" id="pwa-dismiss">Later</button>
                    <button class="btn btn-sm btn-brand fw-bold px-3 shadow-sm" id="pwa-install-btn">Install</button>
                </div>
            </div>
        `;
        document.body.appendChild(installBanner);

        document.getElementById('pwa-install-btn').addEventListener('click', async () => {
            installBanner.remove();
            if (deferredPrompt) {
                deferredPrompt.prompt(); 
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
            }
        });

        document.getElementById('pwa-dismiss').addEventListener('click', () => {
            installBanner.style.transform = 'translateY(100%)'; 
            setTimeout(() => installBanner.remove(), 300);
        });
    });

    window.addEventListener('appinstalled', () => {
        const banner = document.getElementById('pwa-install-banner');
        if(banner) banner.remove();
    });

    // Run initial offline UI check on DOM load
    updateOfflineUI();
});

// Dynamic Offline UI update
function updateOfflineUI() {
    const isOnline = navigator.onLine;
    let networkBanner = document.getElementById('network-offline-banner');

    if (!isOnline) {
        if (!networkBanner) {
            networkBanner = document.createElement('div');
            networkBanner.id = 'network-offline-banner';
            networkBanner.className = 'alert alert-danger border-0 rounded-0 m-0 d-flex align-items-center justify-content-between px-4 py-3 shadow-sm';
            networkBanner.style.cssText = 'background-color: #fef2f2; border-bottom: 1px solid #fca5a5 !important; z-index: 9999; position: relative;';
            networkBanner.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-wifi-off text-danger fs-5 animate-pulse-offline"></i>
                    <div>
                        <strong class="text-danger">Can't Connect, You're Offline</strong>
                        <span class="text-danger-emphasis ms-2 d-none d-md-inline" style="color: #991b1b;">No internet connection detected. Showing cached page layout.</span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-danger fw-bold border-2" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Retry
                </button>
            `;

            if (!document.getElementById('pulse-offline-style')) {
                const style = document.createElement('style');
                style.id = 'pulse-offline-style';
                style.textContent = `
                    @keyframes pulseOffline { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
                    .animate-pulse-offline { animation: pulseOffline 2s infinite; }
                `;
                document.head.appendChild(style);
            }

            const topNavbar = document.querySelector('.top-navbar');
            if (topNavbar) {
                topNavbar.parentNode.insertBefore(networkBanner, topNavbar.nextSibling);
            } else {
                document.body.insertBefore(networkBanner, document.body.firstChild);
            }
        }

        // Mask all dynamic tables
        document.querySelectorAll('table').forEach(table => {
            if (!table.querySelector('.offline-placeholder-row')) {
                const tbody = table.querySelector('tbody');
                if (tbody) {
                    if (!tbody.dataset.originalHtml) {
                        tbody.dataset.originalHtml = tbody.innerHTML;
                    }
                    const colCount = table.querySelectorAll('thead th').length || 6;
                    tbody.innerHTML = `
                        <tr class="offline-placeholder-row">
                            <td colspan="${colCount}" class="text-center py-5 text-muted">
                                <i class="bi bi-wifi-off fs-1 d-block mb-2 text-danger"></i>
                                <span class="fw-bold">Database records unavailable while offline.</span>
                            </td>
                        </tr>
                    `;
                }
            }
        });

        // Disable standard actions
        document.querySelectorAll('button[data-bs-toggle="modal"], button[type="submit"], input[type="submit"]').forEach(btn => {
            // Keep the retry button, close buttons, and modal-footer buttons (like Close) enabled
            if (btn.id === 'pwa-install-btn' || btn.id === 'pwa-dismiss' || btn.classList.contains('btn-close') || btn.closest('.modal-footer') || btn.closest('.alert')) {
                return;
            }
            btn.disabled = true;
            btn.classList.add('disabled');
        });

        // Mask stats count/value
        document.querySelectorAll('.stat-card h3, .stat-card .fw-bold').forEach(stat => {
            if (stat.closest('.alert')) return; // skip warning text in alerts
            if (!stat.dataset.originalText) {
                stat.dataset.originalText = stat.textContent;
            }
            stat.textContent = '—';
        });
    } else {
        // Restore if we went back online
        if (networkBanner) {
            networkBanner.remove();
        }

        document.querySelectorAll('table').forEach(table => {
            const tbody = table.querySelector('tbody');
            if (tbody && tbody.dataset.originalHtml) {
                tbody.innerHTML = tbody.dataset.originalHtml;
                delete tbody.dataset.originalHtml;
            }
        });

        document.querySelectorAll('button[data-bs-toggle="modal"], button[type="submit"], input[type="submit"]').forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('disabled');
        });

        document.querySelectorAll('.stat-card h3, .stat-card .fw-bold').forEach(stat => {
            if (stat.dataset.originalText) {
                stat.textContent = stat.dataset.originalText;
                delete stat.dataset.originalText;
            }
        });
    }
}

// Window Event Listeners for real-time online/offline toggle
window.addEventListener('online', updateOfflineUI);
window.addEventListener('offline', updateOfflineUI);