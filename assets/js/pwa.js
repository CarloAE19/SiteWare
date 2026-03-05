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
});