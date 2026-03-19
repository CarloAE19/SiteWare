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

// 3. REAL-TIME USERNAME VALIDATION
const usernameField = document.getElementById('usernameField');
const usernameFloat = document.getElementById('usernameFloat');
const jsErrorBlock = document.getElementById('jsErrorBlock');
const jsErrorMessage = document.getElementById('jsErrorMessage');
const phpErrorBlock = document.getElementById('phpErrorBlock');
const phpUsernameErrorBlock = document.getElementById('phpUsernameErrorBlock');

if (usernameField && jsErrorBlock && jsErrorMessage && usernameFloat) {
    const validateUsername = () => {
        const username = usernameField.value;
        const hasSpecialChars = /[^a-zA-Z0-9]/.test(username);
        
        if (hasSpecialChars) {
            if (phpErrorBlock) phpErrorBlock.style.display = 'none';
            if (phpUsernameErrorBlock) phpUsernameErrorBlock.style.display = 'none';
            
            jsErrorMessage.textContent = 'Special characters not allowed in username';
            jsErrorBlock.style.display = 'flex';
            usernameFloat.classList.add('has-error');
        } else {
            jsErrorBlock.style.display = 'none';
            usernameFloat.classList.remove('has-error');
            
            if (phpErrorBlock) phpErrorBlock.style.display = 'flex'; 
            if (phpUsernameErrorBlock) phpUsernameErrorBlock.style.display = 'none'; // Once corrected by JS, keep PHP fallback hidden
        }
    };

    // Validate in real-time as the user types and when they blur the field
    usernameField.addEventListener('input', validateUsername);
    usernameField.addEventListener('blur', validateUsername);
}
