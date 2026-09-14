/**
 * CIMS Inactivity & Two-Tier Idle Management System
 * Conforms to ISO/IEC 25010, HCI & Enterprise Security Standards
 * 
 * Tier 1: Soft Screen Lock (Protects in-progress forms and confidential inventory data)
 * Tier 2: Hard Server Expiry / Auto-Logout (60s countdown warning + PHP session termination)
 * Anti-Tamper Guard: MutationObserver + Content Inerting + Server-Side Lock Enforcement
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        const config = window.cimsIdleConfig;
        if (!config || !config.enabled || !config.userId) {
            return; // Inactivity lock disabled or not in an authenticated session
        }

        const lockMinutesVal = parseFloat(config.lockMinutes) || 15;
        const logoutMinutesVal = parseFloat(config.logoutMinutes);
        const isLogoutEnabled = !isNaN(logoutMinutesVal) && logoutMinutesVal > 0;

        const lockThresholdMs = Math.max(5000, Math.round(lockMinutesVal * 60 * 1000));
        const logoutThresholdMs = isLogoutEnabled ? Math.max(10000, Math.round(logoutMinutesVal * 60 * 1000)) : 0;
        const warningCountdownSeconds = isLogoutEnabled ? Math.min(60, Math.max(5, Math.floor(logoutThresholdMs / 2000))) : 0;

        const STORAGE_KEY_ACTIVE = 'cims_last_active_' + config.userId;
        const STORAGE_KEY_LOCKED = 'cims_screen_locked_' + config.userId;

        const lockModalEl = document.getElementById('cimsIdleLockModal');
        const unlockForm = document.getElementById('cimsIdleUnlockForm');
        const unlockPasswordInput = document.getElementById('cimsUnlockPassword');
        const unlockSubmitBtn = document.getElementById('cimsUnlockSubmitBtn');
        const unlockErrorAlert = document.getElementById('cimsUnlockErrorAlert');
        const togglePwdBtn = document.getElementById('cimsToggleUnlockPwd');
        const countdownBanner = document.getElementById('cimsIdleCountdownBanner');
        const countdownSecondsEl = document.getElementById('cimsCountdownSeconds');

        let bsLockModal = null;
        let isLocked = false;
        let isLegitimateUnlocking = false;
        let lastPingTime = Date.now();

        // Initialize Bootstrap Modal with static backdrop
        if (lockModalEl && typeof bootstrap !== 'undefined') {
            bsLockModal = bootstrap.Modal.getOrCreateInstance(lockModalEl, {
                backdrop: 'static',
                keyboard: false
            });

            lockModalEl.addEventListener('shown.bs.modal', () => {
                if (unlockPasswordInput) {
                    unlockPasswordInput.value = '';
                    unlockPasswordInput.focus();
                }
                if (unlockErrorAlert) {
                    unlockErrorAlert.classList.add('d-none');
                    unlockErrorAlert.textContent = '';
                }
            });
        }

        const now = Date.now();
        const storedActiveStr = localStorage.getItem(STORAGE_KEY_ACTIVE);
        const storedActiveTime = storedActiveStr ? parseInt(storedActiveStr, 10) : 0;
        const storedIsLocked = localStorage.getItem(STORAGE_KEY_LOCKED) === '1';

        // Check if this is a fresh login OR if stored active time is stale (older than logout threshold)
        const isStale = isLogoutEnabled && (!storedActiveTime || ((now - storedActiveTime) > logoutThresholdMs));

        if (config.freshLogin || isStale) {
            // Fresh login or stale previous session from hours/days ago:
            // Reset active timestamp to right now and reset lock state
            localStorage.setItem(STORAGE_KEY_ACTIVE, now.toString());
            localStorage.setItem(STORAGE_KEY_LOCKED, '0');
            isLocked = false;
        } else if (config.isLocked || storedIsLocked) {
            triggerLockScreen();
        }

        // Initialize Anti-Tampering Watchdog
        initAntiTamperGuard();

        /**
         * Global Test Hook (allows testing lock screen instantly via UI button or console)
         */
        window.cimsLockScreenNow = function () {
            triggerLockScreen();
        };

        /**
         * Record user activity across all open browser tabs
         */
        function recordActivity() {
            if (isLocked) return; // Do not reset timer while locked

            const now = Date.now();
            localStorage.setItem(STORAGE_KEY_ACTIVE, now.toString());

            // Periodically ping the server every 5 minutes to keep PHP session aligned
            if (now - lastPingTime > 5 * 60 * 1000) {
                lastPingTime = now;
                pingSession();
            }
        }

        // Throttled activity event listeners
        let activityThrottleTimeout = null;
        function throttledActivityHandler() {
            if (activityThrottleTimeout || isLocked) return;
            activityThrottleTimeout = setTimeout(() => {
                activityThrottleTimeout = null;
                recordActivity();
            }, 1000);
        }

        const activityEvents = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click'];
        activityEvents.forEach(evt => {
            window.addEventListener(evt, throttledActivityHandler, { passive: true });
        });

        /**
         * Multi-Tab Synchronization via localStorage events
         */
        window.addEventListener('storage', (e) => {
            if (e.key === STORAGE_KEY_LOCKED) {
                if (e.newValue === '1' && !isLocked) {
                    triggerLockScreen();
                } else if (e.newValue === '0' && isLocked) {
                    unlockScreenSuccess();
                }
            }
        });

        /**
         * Periodic Heartbeat Timer (runs every 1 second)
         */
        setInterval(() => {
            const now = Date.now();
            const lastActive = parseInt(localStorage.getItem(STORAGE_KEY_ACTIVE) || now.toString(), 10);
            const idleElapsed = now - lastActive;

            // Tier 1: Check if Soft Lock Threshold reached
            if (idleElapsed >= lockThresholdMs && !isLocked) {
                triggerLockScreen();
            }

            // Tier 2: Check if Hard Logout Threshold is enabled and approaching/reached
            if (isLogoutEnabled) {
                const timeUntilLogout = logoutThresholdMs - idleElapsed;

                if (isLocked && timeUntilLogout <= (warningCountdownSeconds * 1000)) {
                    const remainingSecs = Math.max(0, Math.ceil(timeUntilLogout / 1000));
                    showCountdownWarning(remainingSecs);

                    if (remainingSecs <= 0) {
                        performAutoLogout();
                    }
                } else if (!isLocked) {
                    hideCountdownWarning();
                }
            } else {
                hideCountdownWarning();
                // If Tier 2 is disabled and screen is locked, periodically keep session alive
                if (isLocked && (now - lastPingTime > 10 * 60 * 1000)) {
                    lastPingTime = now;
                    pingSession();
                }
            }
        }, 1000);

        /**
         * Tier 1: Soft Lock Screen Trigger
         */
        function triggerLockScreen() {
            if (isLocked) return;
            isLocked = true;
            localStorage.setItem(STORAGE_KEY_LOCKED, '1');

            // 1. Shield underlying content: Heavy blur + inerting
            document.body.classList.add('cims-body-locked');
            const contentEl = document.getElementById('content');
            if (contentEl) contentEl.setAttribute('inert', '');
            const sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) sidebarEl.setAttribute('inert', '');
            const navbarEl = document.querySelector('.top-navbar');
            if (navbarEl) navbarEl.setAttribute('inert', '');

            // 2. Notify backend server to mark session locked
            const basePath = window.cimsBasePath || '';
            fetch(basePath + '/process/process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ action: 'lock_screen' })
            }).catch(() => {});

            // 3. Render modal
            if (bsLockModal) {
                bsLockModal.show();
            } else if (lockModalEl) {
                lockModalEl.classList.add('show');
                lockModalEl.style.display = 'block';
                document.body.classList.add('modal-open');
            }
        }

        /**
         * Anti-Tamper Watchdog (MutationObserver & DevTools Guard)
         * Detects if an attacker deletes modal elements, strips backdrop,
         * or removes the inert attribute via Inspect Element.
         */
        function initAntiTamperGuard() {
            if (!lockModalEl) return;

            // Detect if modal is closed via DOM methods or Bootstrap without unlocking
            lockModalEl.addEventListener('hidden.bs.modal', () => {
                if (isLocked && !isLegitimateUnlocking) {
                    triggerTamperAlert('Lock screen modal was dismissed without unlocking.');
                }
            });

            const observer = new MutationObserver(() => {
                if (!isLocked || isLegitimateUnlocking) return;

                // Check 1: Was modal element deleted from the DOM?
                if (!document.body.contains(lockModalEl)) {
                    triggerTamperAlert('Lock screen modal element was deleted from DOM.');
                    return;
                }

                // Check 2: Were security shielding classes removed from body?
                if (!document.body.classList.contains('cims-body-locked')) {
                    triggerTamperAlert('Body shielding protection was removed.');
                    return;
                }

                // Check 3: Was inert stripped from content wrapper?
                const contentEl = document.getElementById('content');
                if (contentEl && !contentEl.hasAttribute('inert')) {
                    triggerTamperAlert('Content inert protection attribute was stripped.');
                    return;
                }
            });

            observer.observe(document.body, {
                childList: true,
                attributes: true,
                attributeFilter: ['class', 'inert']
            });
        }

        function triggerTamperAlert(reason) {
            console.warn('Security Alert: Anti-tamper violation detected.', reason);

            // Wipe out view immediately so zero data can be inspected or extracted
            document.body.innerHTML = `
                <div style="height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0f172a;color:#ffffff;font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:24px;">
                    <div style="font-size:3.5rem;margin-bottom:16px;color:#ef4444;">
                        <i class="bi bi-shield-slash-fill"></i>
                    </div>
                    <h2 style="font-weight:700;margin-bottom:12px;">Security Tampering Detected</h2>
                    <p style="color:#94a3b8;max-width:480px;line-height:1.6;margin-bottom:24px;">An unauthorized DOM modification was detected while your workstation was locked. To protect confidential company records, your session has been terminated.</p>
                    <a href="${(window.cimsBasePath || '')}/logout?timeout=1" class="btn btn-primary px-4 py-2 fw-bold">Return to Login</a>
                </div>
            `;

            localStorage.removeItem(STORAGE_KEY_LOCKED);
            localStorage.removeItem(STORAGE_KEY_ACTIVE);

            // Force hard session destroy and redirect
            const basePath = window.cimsBasePath || '';
            window.location.href = basePath + '/logout?timeout=1';
        }

        /**
         * Tier 2: Warning Countdown Display
         */
        function showCountdownWarning(seconds) {
            if (!countdownBanner || !countdownSecondsEl) return;
            countdownSecondsEl.textContent = seconds;
            countdownBanner.classList.remove('d-none');
        }

        function hideCountdownWarning() {
            if (!countdownBanner) return;
            countdownBanner.classList.add('d-none');
        }

        /**
         * Tier 2: Hard Server Expiry / Auto-Logout Action
         */
        function performAutoLogout() {
            localStorage.removeItem(STORAGE_KEY_LOCKED);
            localStorage.removeItem(STORAGE_KEY_ACTIVE);

            const basePath = window.cimsBasePath || '';
            window.location.href = basePath + '/logout?timeout=1';
        }

        /**
         * Ping server endpoint to maintain PHP session
         */
        async function pingSession() {
            try {
                const basePath = window.cimsBasePath || '';
                await fetch(basePath + '/process/process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({ action: 'ping_session' })
                });
            } catch (err) {
                // Silent failover on offline/network blip
            }
        }

        /**
         * Password Visibility Toggle
         */
        if (togglePwdBtn && unlockPasswordInput) {
            togglePwdBtn.addEventListener('click', () => {
                const isPassword = unlockPasswordInput.type === 'password';
                unlockPasswordInput.type = isPassword ? 'text' : 'password';
                togglePwdBtn.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
            });
        }

        /**
         * Modal AJAX Submission Pattern (Adhering to CIMS Skill Standard)
         */
        if (unlockForm) {
            unlockForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const passwordVal = unlockPasswordInput ? unlockPasswordInput.value.trim() : '';
                if (!passwordVal) {
                    showUnlockError('Please enter your password to unlock the screen.');
                    if (unlockPasswordInput) unlockPasswordInput.focus();
                    return;
                }

                const originalBtnText = unlockSubmitBtn ? unlockSubmitBtn.innerHTML : 'Unlock';

                // Prevent double submissions & show loading spinner
                if (unlockSubmitBtn) {
                    unlockSubmitBtn.disabled = true;
                    unlockSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Verifying...';
                }
                if (unlockErrorAlert) {
                    unlockErrorAlert.classList.add('d-none');
                }

                try {
                    const basePath = window.cimsBasePath || '';
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    const formData = new URLSearchParams();
                    formData.append('action', 'unlock_screen');
                    formData.append('password', passwordVal);
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch(basePath + '/process/process.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-Token': csrfToken
                        }
                    });

                    const result = await response.json();
                    const isSuccess = (result.success === true || result.status === 'success');

                    if (isSuccess) {
                        unlockScreenSuccess();
                    } else {
                        showUnlockError(result.message || 'Incorrect password. Please try again.');
                        if (unlockPasswordInput) {
                            unlockPasswordInput.value = '';
                            unlockPasswordInput.focus();
                        }
                    }
                } catch (err) {
                    console.error('Unlock AJAX Error:', err);
                    showUnlockError('Verification error: ' + (err.message || 'Please check password and try again.'));
                } finally {
                    if (unlockSubmitBtn) {
                        unlockSubmitBtn.disabled = false;
                        unlockSubmitBtn.classList.remove('disabled');
                        unlockSubmitBtn.innerHTML = originalBtnText;
                    }
                    if (unlockForm) {
                        delete unlockForm.dataset.submitting;
                    }
                }
            });
        }

        function showUnlockError(msg) {
            if (!unlockErrorAlert) return;
            unlockErrorAlert.textContent = msg;
            unlockErrorAlert.classList.remove('d-none');

            // Quick subtle shake animation
            if (unlockPasswordInput) {
                unlockPasswordInput.classList.add('is-invalid');
                setTimeout(() => {
                    unlockPasswordInput.classList.remove('is-invalid');
                }, 1500);
            }
        }

        /**
         * Successful Screen Unlock Handler
         */
        function unlockScreenSuccess() {
            isLegitimateUnlocking = true;
            isLocked = false;
            localStorage.setItem(STORAGE_KEY_LOCKED, '0');
            localStorage.setItem(STORAGE_KEY_ACTIVE, Date.now().toString());

            hideCountdownWarning();

            // 1. Remove background shielding & inerting
            document.body.classList.remove('cims-body-locked');
            const contentEl = document.getElementById('content');
            if (contentEl) contentEl.removeAttribute('inert');
            const sidebarEl = document.getElementById('sidebar');
            if (sidebarEl) sidebarEl.removeAttribute('inert');
            const navbarEl = document.querySelector('.top-navbar');
            if (navbarEl) navbarEl.removeAttribute('inert');

            // 2. Hide modal
            if (bsLockModal) {
                bsLockModal.hide();
            } else if (lockModalEl) {
                lockModalEl.classList.remove('show');
                lockModalEl.style.display = 'none';
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            }

            if (unlockForm) {
                unlockForm.reset();
            }
            if (unlockErrorAlert) {
                unlockErrorAlert.classList.add('d-none');
            }

            setTimeout(() => {
                isLegitimateUnlocking = false;
            }, 500);

            // 3. Quick success toast if SweetAlert2 is present
            if (typeof Swal !== 'undefined') {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true
                });
                Toast.fire({
                    icon: 'success',
                    title: 'Welcome back! Screen unlocked.'
                });
            }
        }
    });
})();
