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
        
        if (document.getElementById('pwa-install-banner')) return;

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
                        <small class="text-muted" style="font-size: 0.8rem;">Install app for fast, offline-ready access</small>
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
        if (banner) banner.remove();
    });

    // Run initial offline UI check on DOM load
    updateOfflineUI();

    // Warm offline routes in background when online
    setTimeout(warmOfflineRoutes, 2500);
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

// Background Route Warmer: pre-caches navigation routes based on user's authorized sidebar links
function warmOfflineRoutes() {
    if (!navigator.onLine) return;

    const sidebarLinks = document.querySelectorAll('#sidebar a[href]');
    if (!sidebarLinks || sidebarLinks.length === 0) return;

    const routesToWarm = new Set();
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (
            href &&
            href !== '#' &&
            !href.startsWith('http') &&
            !href.includes('logout') &&
            !href.startsWith('javascript:')
        ) {
            routesToWarm.add(href);
        }
    });

    let delay = 1000;
    routesToWarm.forEach(route => {
        setTimeout(() => {
            if (navigator.onLine) {
                fetch(route, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).catch(() => {
                    // Silently ignore prefetch errors
                });
            }
        }, delay);
        delay += 600;
    });
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