/* ==========================================================
 * GB INVENTORY - PROGRESSIVE WEB APP & OFFLINE MANAGER
 * Handles PWA installation, Service Worker lifecycle,
 * smart offline caching, read-only UI states, and route warming.
 * ========================================================== */

// 1. Service Worker Registration (Always active for offline caching)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/CIMS/firebase-messaging-sw.js', { scope: '/CIMS/' })
            .then((reg) => {
                console.log('[PWA] Service Worker registered with scope:', reg.scope);
            })
            .catch((err) => {
                console.warn('[PWA] Service Worker registration failed:', err);
            });
    });
}

// 2. Custom "Install App" Mobile Banner
document.addEventListener("DOMContentLoaded", () => {
    let deferredPrompt;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // 1. Check permanent suppression ("Don't show again") or active session snooze
        const isPermanentlyDismissed = localStorage.getItem('cims_pwa_dismissed') === 'true';
        const isSessionDismissed = sessionStorage.getItem('cims_pwa_session_dismissed') === 'true';
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        if (isPermanentlyDismissed || isSessionDismissed || isStandalone) {
            return;
        }

        // Avoid duplicate banners if one is already present in the DOM
        if (document.getElementById('pwa-install-banner')) return;

        const installBanner = document.createElement('div');
        installBanner.id = 'pwa-install-banner';
        installBanner.setAttribute('role', 'dialog');
        installBanner.setAttribute('aria-label', 'GB Inventory App Installation Notice');
        installBanner.className = 'position-fixed bottom-0 start-50 translate-middle-x w-100 p-3 shadow-lg bg-white border-top';
        installBanner.style.maxWidth = '580px';
        installBanner.style.borderTopLeftRadius = '20px';
        installBanner.style.borderTopRightRadius = '20px';
        installBanner.style.zIndex = '99999';
        installBanner.style.transition = 'transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease';
        
        installBanner.innerHTML = `
            <div class="d-flex flex-column gap-2">
                <!-- Top Row: App Info & Close Button -->
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center text-start">
                        <img src="assets/LogoGB.png" alt="GB Logo" width="42" height="42" class="me-3 rounded shadow-sm border flex-shrink-0" style="object-fit: cover;">
                        <div>
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.98rem; line-height: 1.25;">GB Inventory</h6>
                            <small class="text-muted d-block" style="font-size: 0.8rem; line-height: 1.3;">Install app for fast, offline-ready access</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-2 p-2 flex-shrink-0" id="pwa-close-btn" aria-label="Close" style="cursor: pointer;"></button>
                </div>

                <!-- Bottom Row: Don't show again Checkbox & Action Buttons -->
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-2 border-top">
                    <div class="form-check d-flex align-items-center mb-0" style="min-height: 38px;">
                        <input class="form-check-input me-2 mt-0" type="checkbox" id="pwa-dont-show" style="width: 1.15rem; height: 1.15rem; cursor: pointer;">
                        <label class="form-check-label text-secondary user-select-none small mb-0" for="pwa-dont-show" style="cursor: pointer; font-size: 0.82rem;">
                            Don't show again
                        </label>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" class="btn btn-sm btn-light text-muted border fw-semibold px-3" id="pwa-dismiss" style="min-height: 38px; min-width: 68px;">Later</button>
                        <button type="button" class="btn btn-sm btn-brand fw-bold px-3 shadow-sm d-inline-flex align-items-center gap-1" id="pwa-install-btn" style="min-height: 38px; min-width: 82px;">
                            <i class="bi bi-download" aria-hidden="true"></i> Install
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(installBanner);

        // Helper: Dismiss banner with animation and appropriate storage persistence
        const dismissBanner = () => {
            const banner = document.getElementById('pwa-install-banner');
            if (!banner) return;

            const dontShowChecked = document.getElementById('pwa-dont-show')?.checked;
            if (dontShowChecked) {
                // Permanently remember preference across all browser sessions and refreshes
                localStorage.setItem('cims_pwa_dismissed', 'true');
            } else {
                // Snooze for the current browser session so F5 / Ctrl+F5 won't keep prompting
                sessionStorage.setItem('cims_pwa_session_dismissed', 'true');
            }

            banner.style.transform = 'translate(-50%, 100%)';
            banner.style.opacity = '0';
            setTimeout(() => {
                if (banner && banner.parentNode) {
                    banner.remove();
                }
            }, 300);
        };

        // Install button action
        document.getElementById('pwa-install-btn')?.addEventListener('click', async () => {
            const banner = document.getElementById('pwa-install-banner');
            if (banner) {
                banner.style.transform = 'translate(-50%, 100%)';
                banner.style.opacity = '0';
                setTimeout(() => { if (banner.parentNode) banner.remove(); }, 300);
            }

            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    localStorage.setItem('cims_pwa_dismissed', 'true');
                } else {
                    sessionStorage.setItem('cims_pwa_session_dismissed', 'true');
                }
                deferredPrompt = null;
            }
        });

        // Later button and close icon actions
        document.getElementById('pwa-dismiss')?.addEventListener('click', dismissBanner);
        document.getElementById('pwa-close-btn')?.addEventListener('click', dismissBanner);
    });

    window.addEventListener('appinstalled', () => {
        localStorage.setItem('cims_pwa_dismissed', 'true');
        const banner = document.getElementById('pwa-install-banner');
        if (banner && banner.parentNode) banner.remove();
    });

    // Global reset helper for testing or settings integration
    window.resetPwaInstallPrompt = function() {
        localStorage.removeItem('cims_pwa_dismissed');
        sessionStorage.removeItem('cims_pwa_session_dismissed');
        console.log('[PWA] Install prompt dismissal preferences reset.');
    };

    // Run initial offline UI check on DOM load
    updateOfflineUI();
});

// Helper: Check if backend server is genuinely reachable
async function checkServerStatus() {
    if (!navigator.onLine) {
        return false;
    }
    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 2500); // 2.5s timeout
        
        const pathSegments = window.location.pathname.split('/');
        const basePath = pathSegments[1] ? '/' + pathSegments[1] : '';
        const pingUrl = basePath + '/manifest.json?ping=' + Date.now();
        
        const response = await fetch(pingUrl, {
            method: 'HEAD',
            signal: controller.signal,
            cache: 'no-store'
        });
        clearTimeout(timeoutId);
        return response.ok;
    } catch (e) {
        return false;
    }
}

// Intercept clicks on mutation actions when offline
function handleOfflineActionClick(e) {
    if (!navigator.onLine) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Offline Mode (Read-Only)',
                text: 'Creating, editing, or approving records requires an active server connection. Modifications are paused until you are back online.',
                confirmButtonColor: '#0d6efd'
            });
        } else {
            alert('Offline Mode (Read-Only): Creating or editing records requires an active connection.');
        }
        return false;
    }
}

// Retry button handler on offline banner
async function handleOfflineRetry(btn) {
    if (!btn) return;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Checking...';

    const isReachable = await checkServerStatus();
    if (isReachable) {
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Connected!';
        setTimeout(() => {
            window.location.reload();
        }, 500);
    } else {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Still Offline',
                text: 'Could not establish connection to the server. Please check your network or Wi-Fi.',
                confirmButtonColor: '#0d6efd',
                timer: 3000
            });
        }
    }
}

// Dynamic Offline UI update: preserves tables, marks read-only, locks write actions
async function updateOfflineUI() {
    let isOnline = navigator.onLine;
    
    // If browser claims online, double-check server reachability
    if (isOnline) {
        isOnline = await checkServerStatus();
    }
    
    let networkBanner = document.getElementById('network-offline-banner');

    if (!isOnline) {
        // 1. Render sleek top banner
        if (!networkBanner) {
            networkBanner = document.createElement('div');
            networkBanner.id = 'network-offline-banner';
            networkBanner.className = 'alert alert-danger border-0 rounded-0 m-0 d-flex align-items-center justify-content-between px-4 py-3 shadow-sm';
            networkBanner.style.cssText = 'background-color: #fef2f2; border-bottom: 1px solid #fca5a5 !important; z-index: 9999; position: relative;';
            networkBanner.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-wifi-off text-danger fs-5 animate-pulse-offline"></i>
                    <div>
                        <strong class="text-danger">Offline Mode (Read-Only)</strong>
                        <span class="text-danger-emphasis ms-2 d-none d-md-inline" style="color: #991b1b;">
                            Viewing cached data. Database modifications and new submissions are paused until reconnected.
                        </span>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-danger fw-bold border-2 d-flex align-items-center" id="btn-offline-retry" onclick="handleOfflineRetry(this)">
                    <i class="bi bi-arrow-clockwise me-1"></i> Retry
                </button>
            `;

            if (!document.getElementById('pulse-offline-style')) {
                const style = document.createElement('style');
                style.id = 'pulse-offline-style';
                style.textContent = `
                    @keyframes pulseOffline { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
                    .animate-pulse-offline { animation: pulseOffline 2s infinite; }
                    .offline-mutation-locked {
                        opacity: 0.6 !important;
                        cursor: not-allowed !important;
                    }
                    .offline-badge-tag {
                        font-size: 0.72rem !important;
                        letter-spacing: 0.02em;
                    }
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

        // 2. Add subtle "Cached Data" badges to cards/tables WITHOUT wiping rows
        document.querySelectorAll('.card-header, .table-responsive').forEach(container => {
            if (!container.querySelector('.offline-badge-tag')) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary-subtle text-secondary border offline-badge-tag ms-2 fw-normal';
                badge.innerHTML = '<i class="bi bi-cloud-slash me-1"></i> Cached';
                
                const heading = container.querySelector('h1, h2, h3, h4, h5, h6, .card-title');
                if (heading) {
                    heading.appendChild(badge);
                }
            }
        });

        // 3. Defensively guard mutation buttons (Create PO, Add Item, Delete, Edit, Submit)
        const mutationSelectors = [
            'button[type="submit"]',
            'input[type="submit"]',
            'button[data-bs-toggle="modal"]',
            'a[data-bs-toggle="modal"]',
            'button:has(.bi-plus-lg)',
            'button:has(.bi-plus-circle)',
            'button:has(.bi-plus)',
            'button:has(.bi-trash)',
            'button:has(.bi-pencil)',
            'button:has(.bi-check-lg)'
        ].join(', ');

        document.querySelectorAll(mutationSelectors).forEach(btn => {
            // Keep retry button, close buttons, modal dismiss buttons, and PWA install buttons active
            if (
                btn.id === 'pwa-install-btn' ||
                btn.id === 'pwa-dismiss' ||
                btn.id === 'btn-offline-retry' ||
                btn.classList.contains('btn-close') ||
                btn.getAttribute('data-bs-dismiss') === 'modal' ||
                btn.closest('.modal-footer .btn-secondary') ||
                btn.closest('#network-offline-banner')
            ) {
                return;
            }

            if (!btn.dataset.offlineDisabled) {
                btn.dataset.offlineDisabled = 'true';
                btn.classList.add('offline-mutation-locked');
                btn.setAttribute('title', 'Modifications paused in Offline Mode');
                btn.addEventListener('click', handleOfflineActionClick, true);
            }
        });

    } else {
        // ONLINE RESTORATION: Clean up offline banners, badges, and locks
        if (networkBanner) {
            networkBanner.remove();
        }

        // Remove cached indicators
        document.querySelectorAll('.offline-badge-tag').forEach(badge => badge.remove());

        // Restore mutation buttons
        document.querySelectorAll('.offline-mutation-locked').forEach(btn => {
            btn.classList.remove('offline-mutation-locked');
            delete btn.dataset.offlineDisabled;
            btn.removeAttribute('title');
            btn.removeEventListener('click', handleOfflineActionClick, true);
        });
    }
}

// Window Event Listeners for real-time online/offline transitions
window.addEventListener('online', () => {
    updateOfflineUI();
    warmOfflineRoutes();
});
window.addEventListener('offline', () => {
    updateOfflineUI();
});

// Periodic check every 12 seconds when the tab is visible
setInterval(() => {
    if (document.visibilityState === 'visible') {
        updateOfflineUI();
    }
}, 12000);