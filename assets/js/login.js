// =====================================================================
//  LOGIN PAGE SCRIPTS — GB Construction & Enterprise Smart Inventory
// =====================================================================

// 1. PASSWORD TOGGLE
function togglePass() {
    const input = document.getElementById('passwordField');
    const icon  = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    }
}

// 2. PWA INSTALL PROMPT
let deferredPrompt;
const installBtn = document.getElementById('installAppBtn');

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    installBtn.classList.add('show');
});

installBtn.addEventListener('click', async () => {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') installBtn.classList.remove('show');
        deferredPrompt = null;
    }
});

window.addEventListener('appinstalled', () => installBtn.classList.remove('show'));
