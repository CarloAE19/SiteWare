// =====================================================================
//  LOGIN PAGE SCRIPTS — GB Construction & Enterprise Smart Inventory
// =====================================================================

// 1. PASSWORD TOGGLE
function togglePass() {
    const input = document.getElementById('passwordField');
    const icon = document.getElementById('toggleIcon');
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

// 3. REAL-TIME USERNAME VALIDATION & FIELD SELECTORS
const usernameField = document.getElementById('usernameField');
const passwordField = document.getElementById('passwordField');
const usernameFloat = document.getElementById('usernameFloat');
const jsErrorBlock = document.getElementById('jsErrorBlock');
const jsErrorMessage = document.getElementById('jsErrorMessage');
const phpErrorBlock = document.getElementById('phpErrorBlock');
const phpUsernameErrorBlock = document.getElementById('phpUsernameErrorBlock');
const signInBtn = document.getElementById('signInBtn');
const capsWarningBlock = document.getElementById('capsWarningBlock');

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
            if (signInBtn) signInBtn.disabled = true;
        } else {
            jsErrorBlock.style.display = 'none';
            usernameFloat.classList.remove('has-error');
            if (signInBtn) signInBtn.disabled = false;

            if (phpErrorBlock) phpErrorBlock.style.display = 'flex';
            if (phpUsernameErrorBlock) phpUsernameErrorBlock.style.display = 'none'; // Once corrected by JS, keep PHP fallback hidden
        }
    };

    // Validate in real-time as the user types and when they blur the field
    usernameField.addEventListener('input', validateUsername);
    usernameField.addEventListener('blur', validateUsername);
}

// 4. AJAX FORM SUBMISSION & SMOOTH MFA MODAL POPUP (NO PAGE REFRESH)
const loginForm = document.getElementById('loginForm') || document.querySelector('.login-card form');
if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const usernameVal = usernameField ? usernameField.value.trim() : '';
        const passwordVal = passwordField ? passwordField.value : '';
        if (!usernameVal || !passwordVal) {
            return;
        }

        const submitBtn = document.getElementById('signInBtn') || this.querySelector('button[type="submit"]');
        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Login';

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Verifying...';
        }

        // Hide any previous alerts
        if (phpErrorBlock) phpErrorBlock.style.display = 'none';
        const phpInfoBlock = document.getElementById('phpInfoBlock');
        if (phpInfoBlock) phpInfoBlock.style.display = 'none';
        if (jsErrorBlock) jsErrorBlock.style.display = 'none';

        try {
            const formData = new FormData(loginForm);
            formData.append('ajax_login', '1');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('login.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken
                }
            });

            const result = await response.json();

            if (result.success || result.status === 'success') {
                if (result.mfa_required) {
                    // Two-factor required: trigger modal instantly without page refresh!
                    const mfaModalEl = document.getElementById('mfaModal');
                    if (mfaModalEl && typeof bootstrap !== 'undefined') {
                        const mfaTargetUserName = document.getElementById('mfaTargetUserName');
                        if (mfaTargetUserName && result.user_name) {
                            mfaTargetUserName.textContent = result.user_name;
                        }

                        const mfaModalInstance = bootstrap.Modal.getOrCreateInstance(mfaModalEl, {
                            backdrop: 'static',
                            keyboard: false
                        });
                        mfaModalInstance.show();

                        const mfaCodeInput = document.getElementById('mfaCodeInput');
                        if (mfaCodeInput) {
                            mfaCodeInput.value = '';
                            setTimeout(() => mfaCodeInput.focus(), 350);
                        }
                    }

                    // Reset password field and restore sign-in button
                    if (passwordField) passwordField.value = '';
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                } else {
                    // Regular user login (no MFA): redirect directly to dashboard
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="bi bi-check2 me-1"></i> Success!';
                    }
                    window.location.href = result.redirect || 'dashboard';
                }
            } else {
                // Verification failed or account locked out
                if (result.locked) {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="bi bi-lock-fill"></i> Locked';
                    }
                    if (usernameField) usernameField.disabled = true;
                    if (passwordField) passwordField.disabled = true;
                } else {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtnHtml;
                    }
                }

                const errBlock = document.getElementById('phpErrorBlock') || phpErrorBlock;
                const errText = document.getElementById('phpErrorText');
                if (errBlock && errText) {
                    errText.textContent = result.message || 'Invalid username or password.';
                    errBlock.style.display = 'flex';
                } else if (errBlock) {
                    errBlock.innerHTML = '<i class="bi bi-exclamation-circle-fill" style="font-size:1.1rem; color:var(--gb-red); flex-shrink:0;"></i> <span id="phpErrorText">' + (result.message || 'Invalid username or password.') + '</span>';
                    errBlock.style.display = 'flex';
                }

                // Subtle shake animation on login card for tactile feedback
                const loginCard = document.querySelector('.login-card');
                if (loginCard) {
                    loginCard.classList.remove('animate-shake');
                    void loginCard.offsetWidth; // trigger reflow
                    loginCard.classList.add('animate-shake');
                }

                if (passwordField) {
                    passwordField.classList.add('is-invalid');
                    passwordField.focus();
                    passwordField.select();
                    setTimeout(() => passwordField.classList.remove('is-invalid'), 1500);
                }
            }
        } catch (err) {
            console.error('AJAX Login Error:', err);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
            const errBlock = document.getElementById('phpErrorBlock') || phpErrorBlock;
            if (errBlock) {
                const phpErrorText = document.getElementById('phpErrorText');
                if (phpErrorText) {
                    phpErrorText.textContent = 'Network or server error during sign in. Please try again.';
                }
                errBlock.style.display = 'flex';
            }
        }
    });

    // Auto-dismiss previous error block as soon as user edits credentials
    if (passwordField) {
        passwordField.addEventListener('input', () => {
            const errBlock = document.getElementById('phpErrorBlock');
            if (errBlock) errBlock.style.display = 'none';
        });
    }
}

// 5. CAPS LOCK DETECTION
if (passwordField && capsWarningBlock) {
    const checkCapsLock = (e) => {
        if (e.getModifierState && typeof e.getModifierState === 'function') {
            if (e.getModifierState('CapsLock')) {
                capsWarningBlock.style.display = 'flex';
            } else {
                capsWarningBlock.style.display = 'none';
            }
        }
    };

    passwordField.addEventListener('keyup', checkCapsLock);
    passwordField.addEventListener('keydown', checkCapsLock);
    passwordField.addEventListener('focus', checkCapsLock);
    passwordField.addEventListener('blur', () => {
        capsWarningBlock.style.display = 'none';
    });
}

