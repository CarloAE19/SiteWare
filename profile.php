<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
require_once 'Connection/db.php';

$userId = $_SESSION['user_id'];

// Fetch current user's data including MFA configuration
$stmt = $pdo->prepare("SELECT name, username, role, created_at, signature_path, mfa_enabled, mfa_secret FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch registered Passkeys / Biometric Devices
$passkeyStmt = $pdo->prepare("SELECT id, device_name, sign_count, created_at FROM user_passkeys WHERE user_id = ? ORDER BY created_at DESC");
$passkeyStmt->execute([$userId]);
$userPasskeys = $passkeyStmt->fetchAll(PDO::FETCH_ASSOC);

// Ensure current session is recorded in active sessions
if (function_exists('record_user_active_session')) {
    record_user_active_session($pdo, (int)$userId);
}

// Fetch active sessions & recognized devices
$currentSessionId = session_id();
$sessionsStmt = $pdo->prepare("
    SELECT uas.id, uas.session_id, uas.device_name, uas.ip_address, uas.is_remembered, 
           uas.created_at, uas.last_activity, uas.status,
           utd.expires_at,
           (uas.session_id = ?) AS is_current_device
    FROM user_active_sessions uas
    LEFT JOIN user_trusted_devices utd ON (uas.trusted_device_id = utd.id OR uas.trusted_token_hash = utd.device_token_hash)
    WHERE uas.user_id = ? AND uas.status = 'active'
    ORDER BY is_current_device DESC, uas.last_activity DESC
");
$sessionsStmt->execute([$currentSessionId, $userId]);
$userActiveSessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Count remote sessions
$otherSessionsCount = 0;
foreach ($userActiveSessions as $sess) {
    if (!$sess['is_current_device']) {
        $otherSessionsCount++;
    }
}

// Role Aesthetics
$roleDisplay = [
    'admin' => ['label' => 'System Admin', 'class' => 'bg-danger'],
    'warehouse' => ['label' => 'Warehouse In-Charge', 'class' => 'bg-success'],
    'management' => ['label' => 'Management', 'class' => 'bg-warning text-dark'],
    'purchasing' => ['label' => 'Purchasing Officer', 'class' => 'bg-info text-dark'],
    'requestor' => ['label' => 'Project Engineer', 'class' => 'bg-secondary']
];

$roleClass = $roleDisplay[$user['role']]['class'] ?? 'bg-secondary';
$roleLabel = $roleDisplay[$user['role']]['label'] ?? 'Unknown Role';

include 'layout/header.php';
?>

<div class="container-fluid px-3 px-md-4 py-4"> <!-- FIXED: Reduced padding on mobile -->
    <!-- Flash Messages -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['msg_type'] ?> alert-dismissible fade show shadow-sm" role="alert">
            <?= $_SESSION['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-person-circle me-2 text-primary"></i>My Profile</h4>
    </div>

    <!-- FIXED: Added g-4 for consistent gap spacing when stacked on mobile -->
    <div class="row g-4">
        <!-- LEFT COLUMN: Profile Overview -->
        <!-- FIXED: Added col-12 so it takes full width on phones -->
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-5">
                    <div class="mb-3">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light text-primary rounded-circle shadow-sm"
                            style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="text-muted mb-3">@<?= htmlspecialchars($user['username']) ?></p>

                    <hr class="my-4 mx-3">

                    <div class="text-start px-3">
                        <small class="text-muted fw-bold text-uppercase d-block mb-1">Account Details</small>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Role Access:</span>
                            <span class="fw-bold"><?= ucwords($user['role']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Member Since:</span>
                            <span class="fw-bold"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Edit Settings -->
        <!-- FIXED: Added col-12 so it takes full width on phones -->
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold py-3">
                    <i class="bi bi-gear-fill text-primary me-2"></i>Account Settings
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="process/process.php">
                        <input type="hidden" name="action" value="update_profile">

                        <h6 class="fw-bold mb-3 border-bottom pb-2">Basic Information</h6>
                        <div class="row mb-4">
                            <!-- FIXED: Added col-12 for mobile stacking -->
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control" name="name"
                                    value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" name="username"
                                    value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 border-bottom pb-2">Security</h6>
                        <div class="mb-4">
                            <button type="button" class="btn btn-outline-primary fw-bold px-3" data-bs-toggle="modal"
                                data-bs-target="#changePasswordModal" onclick="resetPasswordModal()">
                                <i class="bi bi-key-fill me-1"></i> Change Password
                            </button>
                        </div>

                        <div class="text-end mt-4">
                            <!-- FIXED: Button is 100% width on phone, auto-width on PC -->
                            <button type="submit" class="btn btn-brand px-4 w-100 w-md-auto fw-bold py-2 py-md-1">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- CARD: MULTI-FACTOR & BIOMETRIC SECURITY                  -->
            <!-- ======================================================== -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-shield-lock-fill text-primary"></i>
                        <span>Multi-Factor Authentication &amp; Biometrics</span>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="bi bi-shield-check me-1"></i>ISO/IEC 27001 &amp; 25010
                    </span>
                </div>
                <div class="card-body p-4">
                    <p class="text-secondary small mb-4" style="line-height: 1.6;">
                        Protect your SiteWare account against credential theft, unauthorized access, and session hijacking by enabling two-factor verification or linking a hardware biometric device (Windows Hello, Touch ID, Face ID).
                    </p>

                    <div class="row g-4">
                        <!-- SECTION 1: TOTP AUTHENTICATOR APP -->
                        <div class="col-12 col-lg-6">
                            <div class="p-3 border rounded-3 h-100 bg-light-subtle d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-phone"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0 text-dark">Authenticator App (TOTP)</h6>
                                        </div>
                                        <?php if (!empty($user['mfa_enabled'])): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                <i class="bi bi-dash-circle me-1"></i>Disabled
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-3" style="line-height: 1.5;">
                                        Generates a dynamic 6-digit code via Google Authenticator, Microsoft Authenticator, or Authy. Works 100% offline.
                                    </p>
                                </div>

                                <div class="pt-2 border-top d-flex gap-2 flex-wrap">
                                    <?php if (!empty($user['mfa_enabled'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" id="openRegenBackupCodesBtn">
                                            <i class="bi bi-arrow-repeat me-1"></i>New Backup Codes
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold ms-auto" data-bs-toggle="modal" data-bs-target="#mfaDisableModal">
                                            <i class="bi bi-shield-x me-1"></i>Disable 2FA
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-primary fw-bold w-100" id="startTotpSetupBtn">
                                            <i class="bi bi-qr-code-scan me-1"></i>Setup Authenticator App
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 2: PASSKEYS & BIOMETRIC LOGIN -->
                        <div class="col-12 col-lg-6">
                            <div class="p-3 border rounded-3 h-100 bg-light-subtle d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px;">
                                                <i class="bi bi-fingerprint"></i>
                                            </div>
                                            <h6 class="fw-bold mb-0 text-dark">Biometric Passkeys</h6>
                                        </div>
                                        <?php if (!empty($userPasskeys)): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle-fill me-1"></i><?= count($userPasskeys) ?> Linked
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                <i class="bi bi-dash-circle me-1"></i>Not Linked
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2" style="line-height: 1.5;">
                                        FIDO2 / WebAuthn cryptographic keys. Log in in 1 second using Windows Hello, Face ID, Touch ID, or your phone's fingerprint sensor.
                                    </p>
                                    <?php if (!empty($userPasskeys)): ?>
                                        <div class="d-flex align-items-center gap-2 text-success small mb-2 p-2 rounded-2" style="background-color: rgba(22, 163, 74, 0.08); border-left: 3px solid #16a34a;">
                                            <i class="bi bi-shield-fill-check fs-6 text-success"></i>
                                            <span class="fw-semibold" style="font-size: 0.8rem; line-height: 1.3;">Biometric passwordless login is active and ready to use.</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="pt-2 border-top">
                                    <?php if (!empty($userPasskeys)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success fw-bold w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="openRegisterPasskeyBtn">
                                            <i class="bi bi-plus-circle"></i>
                                            <span>Add Another Device / Key</span>
                                        </button>
                                        <small class="text-muted d-block text-center mt-1" style="font-size: 0.72rem;">
                                            Link an additional PC, laptop, or backup device
                                        </small>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-success fw-bold w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="openRegisterPasskeyBtn">
                                            <i class="bi bi-fingerprint"></i>
                                            <span>Register This Device / Biometric</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- REGISTERED PASSKEYS LIST -->
                    <?php if (!empty($userPasskeys)): ?>
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bold small text-uppercase text-secondary mb-0">
                                    <i class="bi bi-laptop me-1 text-primary"></i>Registered Biometric Devices (<?= count($userPasskeys) ?>)
                                </h6>
                                <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                    Passwordless Active
                                </span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Device Name</th>
                                            <th>Date Registered</th>
                                            <th>Logins</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userPasskeys as $pk): ?>
                                            <tr id="passkey_row_<?= (int)$pk['id'] ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; font-size: 0.85rem;">
                                                            <i class="bi bi-shield-check"></i>
                                                        </div>
                                                        <div>
                                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($pk['device_name']) ?></span>
                                                            <small class="text-success d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                                                <span class="badge rounded-pill bg-success" style="width: 6px; height: 6px; padding: 0;"></span> Ready for biometric sign-in
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-muted small text-nowrap"><?= date('M d, Y h:i A', strtotime($pk['created_at'])) ?></td>
                                                <td><span class="badge bg-light text-dark border"><?= (int)$pk['sign_count'] ?> logins</span></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-passkey-btn shadow-none" 
                                                        style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;"
                                                        data-id="<?= (int)$pk['id'] ?>" data-name="<?= htmlspecialchars($pk['device_name']) ?>" title="Remove this Passkey" aria-label="Remove this device">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- ACTIVE SESSIONS & RECOGNIZED DEVICES (REMOTE LOGOUT & 30-DAY MFA) -->
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold small text-uppercase text-secondary mb-1">
                                    <i class="bi bi-devices me-1 text-primary"></i>Active Sessions & Recognized Devices (<?= count($userActiveSessions) ?>)
                                </h6>
                                <p class="text-muted small mb-0" style="font-size: 0.78rem;">
                                    Devices currently logged into your account. Signing out a device terminates its session immediately and revokes its 30-day 2FA bypass.
                                </p>
                            </div>
                            <?php if ($otherSessionsCount > 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger signout-all-others-btn" style="min-height: 38px;">
                                    <i class="bi bi-box-arrow-right me-1"></i>Sign Out All Other Devices (<?= $otherSessionsCount ?>)
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($userActiveSessions)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Browser / Device</th>
                                            <th>Session Status</th>
                                            <th>2FA Bypass</th>
                                            <th>IP & Last Active</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userActiveSessions as $sess): 
                                            $isCurr = (bool)$sess['is_current_device'];
                                            $isRem = (bool)$sess['is_remembered'];
                                            $daysLeft = !empty($sess['expires_at']) ? max(0, ceil((strtotime($sess['expires_at']) - time()) / 86400)) : 0;
                                            
                                            // Icon selection
                                            $devIcon = 'bi-display';
                                            if (stripos($sess['device_name'], 'phone') !== false || stripos($sess['device_name'], 'android') !== false || stripos($sess['device_name'], 'iphone') !== false) {
                                                $devIcon = 'bi-phone';
                                            } elseif (stripos($sess['device_name'], 'windows') !== false || stripos($sess['device_name'], 'mac') !== false) {
                                                $devIcon = 'bi-laptop';
                                            }
                                        ?>
                                            <tr id="session_row_<?= (int)$sess['id'] ?>" class="<?= $isCurr ? 'table-light-subtle' : '' ?>">
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="<?= $isCurr ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.95rem;">
                                                            <i class="bi <?= $devIcon ?>"></i>
                                                        </div>
                                                        <div>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="fw-bold text-dark"><?= htmlspecialchars($sess['device_name']) ?></span>
                                                                <?php if ($isCurr): ?>
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">
                                                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.45rem;"></i>This Device
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted" style="font-size: 0.72rem;">
                                                                Signed in: <?= date('M d, Y h:i A', strtotime($sess['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($isCurr): ?>
                                                        <span class="badge bg-success text-white fw-semibold">
                                                            <i class="bi bi-broadcast me-1"></i>Active Now
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-muted border">
                                                            <i class="bi bi-wifi text-secondary me-1"></i>Online
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($isRem): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle d-inline-flex align-items-center gap-1">
                                                            <i class="bi bi-shield-check"></i>
                                                            <span>Trusted (<?= $daysLeft ?>d left)</span>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                            <i class="bi bi-shield me-1"></i>Standard
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="font-monospace small text-dark"><?= htmlspecialchars($sess['ip_address']) ?></div>
                                                    <small class="text-muted" style="font-size: 0.72rem;">
                                                        <?= $isCurr ? 'Active Now' : time_elapsed_string($sess['last_activity']) ?>
                                                    </small>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($isCurr): ?>
                                                        <span class="badge bg-light text-muted border py-2 px-2" style="font-size: 0.75rem;">
                                                            <i class="bi bi-lock-fill text-success me-1"></i>Current Session
                                                        </span>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger revoke-session-btn shadow-none" 
                                                            style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;"
                                                            data-id="<?= (int)$sess['id'] ?>" data-name="<?= htmlspecialchars($sess['device_name']) ?>" title="Sign Out & Revoke Device" aria-label="Sign Out & Revoke Device">
                                                            <i class="bi bi-box-arrow-right"></i>
                                                            <span class="d-none d-md-inline">Log Out & Revoke</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <i class="bi bi-laptop text-muted fs-4 d-block mb-1"></i>
                                <span class="text-muted small">No other active sessions detected.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- E-SIGNATURE CARD -->
            <div class="card border-0 shadow-sm mt-4">
                <div
                    class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <i class="bi bi-pen-fill text-primary me-2"></i>Official Digital E-Signature
                    </div>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Your registered E-Signature will automatically be attached above your printed name on official
                        <strong>Purchase Orders</strong> and <strong>Withdrawal Slips</strong> when creating or
                        approving transactions.
                    </p>

                    <div class="row align-items-center mb-2">
                        <div class="col-12 col-md-5 text-center mb-3 mb-md-0 border-end pe-md-4">
                            <label class="form-label fw-bold d-block text-secondary small text-uppercase mb-2">Current
                                Registered Signature</label>
                            <?php if (!empty($user['signature_path']) && file_exists(__DIR__ . '/' . $user['signature_path'])): ?>
                                <div class="p-2 border rounded sig-preview-box d-inline-block shadow-sm w-100 mb-2"
                                    style="background-color: #ffffff !important;">
                                    <img src="secure_image.php?type=signatures&file=<?= urlencode(basename($user['signature_path'])) ?>&v=<?= time() ?>"
                                        alt="User Signature" class="img-fluid"
                                        style="max-height: 90px; object-fit: contain;">
                                </div>
                                <div>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-circle-fill me-1"></i>Active Signature Registered
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="p-3 border rounded bg-light text-muted d-block text-center w-100 mb-2">
                                    <i class="bi bi-file-earmark-x display-6 d-block mb-1 text-secondary"></i>
                                    <span class="small fw-bold">No Signature Registered</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12 col-md-7 ps-md-4">
                            <!-- Nav tabs for Draw vs Upload -->
                            <ul class="nav nav-pills nav-fill mb-3 bg-light p-1 rounded border" id="sigTypeTabs"
                                role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active fw-bold btn-sm py-1" id="draw-tab"
                                        data-bs-toggle="tab" data-bs-target="#draw-sig-pane" type="button" role="tab">
                                        <i class="bi bi-pencil-square me-1"></i> Draw Pad
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link fw-bold btn-sm py-1" id="upload-tab" data-bs-toggle="tab"
                                        data-bs-target="#upload-sig-pane" type="button" role="tab">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload Image
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="sigTypeTabContent">
                                <!-- TAB 1: DRAW CANVAS -->
                                <div class="tab-pane fade show active" id="draw-sig-pane" role="tabpanel">
                                    <form method="POST" action="process/update_signature.php" id="drawSigForm">
                                        <input type="hidden" name="signature_data" id="profileSigData">

                                        <div class="mb-3 text-center">
                                            <!-- Drawn Signature Preview Box (shown after drawing in modal) -->
                                            <div id="profileSigDrawnPreview"
                                                class="d-none border rounded p-3 mb-3 sig-preview-box shadow-sm position-relative"
                                                style="background-color: #ffffff !important;">
                                                <small class="d-block fw-bold text-uppercase mb-2"
                                                    style="font-size: 0.7rem; color: #475569 !important;">Newly Drawn
                                                    Signature Preview</small>
                                                <img id="profileSigPreviewImg" src="" alt="Drawn Signature"
                                                    style="max-height: 90px; object-fit: contain;">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 py-0 px-2"
                                                    id="clearProfileDrawnSigBtn" title="Clear drawn signature">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>

                                            <!-- Open Fullscreen Pad Button -->
                                            <button type="button"
                                                class="btn btn-outline-primary fw-bold w-100 py-3 shadow-sm border-2"
                                                id="openProfileFullSigBtn" data-bs-toggle="modal"
                                                data-bs-target="#profileFullSigModal">
                                                <i
                                                    class="bi bi-arrows-fullscreen display-6 d-block mb-2 text-primary"></i>
                                                <span class="fs-6">Open Fullscreen Signature Pad</span>
                                                <small class="d-block text-muted fw-normal mt-1"
                                                    style="font-size: 0.75rem;">Touch or click to open full-screen
                                                    pad</small>
                                            </button>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-secondary fw-bold me-auto d-none"
                                                id="clearProfileSigBtn">
                                                <i class="bi bi-eraser me-1"></i> Reset
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold px-4 ms-auto"
                                                id="saveDrawnSigSubmitBtn" disabled>
                                                <i class="bi bi-check-lg me-1"></i> Save Drawn Signature
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- TAB 2: UPLOAD IMAGE -->
                                <div class="tab-pane fade" id="upload-sig-pane" role="tabpanel">
                                    <form method="POST" action="process/update_signature.php"
                                        enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Select Signature Image (PNG /
                                                JPEG)</label>
                                            <input type="file" name="signature_file"
                                                class="form-control form-control-sm"
                                                accept="image/png, image/jpeg, image/jpg" required>
                                            <div class="form-text text-muted" style="font-size: 0.75rem;">Transparent
                                                PNG with dark signature stroke is recommended.</div>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                                                <i class="bi bi-upload me-1"></i> Upload Signature File
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================== -->
            <!-- MODAL: PROFILE FULLSCREEN SIGNATURE PAD                  -->
            <!-- ======================================================== -->
            <style>
                /* Forced Landscape Mode for Mobile Signature Modal */
                @media screen and (max-width: 991px) and (orientation: portrait) {
                    #profileFullSigModal .modal-dialog {
                        margin: 0 !important;
                        max-width: 100vw !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        overflow: hidden !important;
                    }

                    #profileFullSigModal .modal-content {
                        position: fixed !important;
                        top: 50% !important;
                        left: 50% !important;
                        width: 100vh !important;
                        height: 100vw !important;
                        transform: translate(-50%, -50%) rotate(90deg) !important;
                        transform-origin: center center !important;
                        border-radius: 0 !important;
                    }
                }
            </style>
            <div class="modal fade" id="profileFullSigModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content border-0">
                        <div class="modal-header bg-dark text-white py-2">
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn-close btn-close-white me-2" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                                <h6 class="modal-title fw-bold text-uppercase mb-0">
                                    <i class="bi bi-pen text-warning me-2"></i> Official E-Signature - Fullscreen Pad
                                </h6>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-light fw-bold me-2"
                                    id="clearProfileFullSigBtn">
                                    <i class="bi bi-eraser me-1"></i> Clear
                                </button>
                                <button type="button" class="btn btn-sm btn-success fw-bold px-3"
                                    id="saveProfileFullSigBtn">
                                    <i class="bi bi-check-lg me-1"></i> Use Signature
                                </button>
                            </div>
                        </div>
                        <div class="modal-body p-2 bg-light d-flex flex-column justify-content-center align-items-center position-relative"
                            style="overflow: hidden;">
                            <canvas id="profileFullSigCanvas" class="border rounded shadow-sm sig-white-bg"
                                style="width: 100%; height: 100%; touch-action: none; cursor: crosshair; background-color: #ffffff !important;"></canvas>
                            <div id="profileFullSigPlaceholder"
                                class="position-absolute top-50 start-50 translate-middle fw-bold pe-none fs-5 text-center"
                                style="pointer-events: none; opacity: 0.6; color: #6c757d !important;">
                                <i class="bi bi-phone-landscape me-2 fs-3 d-block mb-1"></i> Sign here with finger...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (in_array($_SESSION['user_role'], ['admin', 'management'])): ?>
                <?php
                // Fetch current login background + blur settings
                $bg_path = 'assets/img/default_login_bg.png';
                $cur_blur = 12;
                if (isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('login_background','login_blur')");
                        $stmt->execute();
                        $lc_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        if (!empty($lc_settings['login_background']))
                            $bg_path = $lc_settings['login_background'];
                        if (isset($lc_settings['login_blur']) && $lc_settings['login_blur'] !== '')
                            $cur_blur = (int) $lc_settings['login_blur'];
                    } catch (Exception $e) {
                    }
                }
                $bg_version = file_exists($bg_path) ? filemtime($bg_path) : time();
                // Build a root-relative URL to the background image
                $profile_app_base = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
                $bg_preview_url = $profile_app_base . '/' . ltrim($bg_path, '/') . '?v=' . $bg_version;
                $cur_blur = max(0, min(30, $cur_blur));
                $cur_scale = round(1 + ($cur_blur * 0.006), 3);
                ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white fw-bold py-3">
                        <i class="bi bi-image text-primary me-2"></i>Login Page Customization
                    </div>
                    <div class="card-body p-4">

                        <!-- ===== LIVE PREVIEW ===== -->
                        <h6 class="fw-bold mb-3 border-bottom pb-2">
                            <i class="bi bi-eye me-1 text-primary"></i>Live Preview
                        </h6>
                        <div id="loginPreviewWrap" style="
                            position: relative;
                            width: 100%;
                            height: 320px;
                            border-radius: 12px;
                            overflow: hidden;
                            border: 1.5px solid #e2e8f0;
                            background: #0f172a;
                            margin-bottom: 1.5rem;
                            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                        ">
                            <!-- Blurred background layer -->
                            <div id="previewBgLayer" style="
                                position: absolute; inset: 0;
                                background-image: url('<?= htmlspecialchars($bg_preview_url) ?>');
                                background-size: cover;
                                background-position: center;
                                filter: blur(<?= $cur_blur ?>px);
                                transform: scale(<?= $cur_scale ?>);
                                transform-origin: center;
                                transition: filter 0.25s ease, transform 0.25s ease;
                            "></div>
                            <!-- Dark overlay -->
                            <div style="position: absolute; inset: 0; background: rgba(15,23,42,0.45);"></div>

                            <!-- ★ Scaled accurate login page replica ★ -->
                            <div style="
                                position: absolute; inset: 0;
                                display: flex; flex-direction: column;
                                align-items: center; justify-content: center;
                                padding-bottom: 18px;
                            ">
                                <!-- Login card — full real size, then CSS-scaled down -->
                                <div style="
                                    transform: scale(0.52);
                                    transform-origin: center center;
                                    background: #ffffff;
                                    width: 440px;
                                    border-radius: 20px;
                                    padding: 36px 40px 32px;
                                    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
                                    text-align: center;
                                    flex-shrink: 0;
                                    pointer-events: none;
                                    user-select: none;
                                ">
                                    <!-- Logo -->
                                    <img src="<?= $profile_app_base ?>/assets/LogoGB.png" alt="Logo" style="width:68px;height:68px;object-fit:contain;border-radius:14px;
                                                background:#fff;padding:5px;
                                                box-shadow:0 4px 12px rgba(0,0,0,0.08);
                                                margin-bottom:10px;display:block;margin-left:auto;margin-right:auto;">
                                    <!-- Brand name -->
                                    <div
                                        style="font-size:1.45rem;font-weight:800;color:#0f172a;letter-spacing:-0.02em;margin-bottom:2px;font-family:'Inter',sans-serif;">
                                        SiteWare</div>
                                    <!-- Heading -->
                                    <div
                                        style="font-size:1.55rem;font-weight:700;color:#0f172a;letter-spacing:-0.02em;margin-bottom:24px;font-family:'Inter',sans-serif;">
                                        Login</div>

                                    <!-- Username field -->
                                    <div style="margin-bottom:18px;text-align:left;">
                                        <div
                                            style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                            Username</div>
                                        <div style="position:relative;">
                                            <i class="bi bi-person"
                                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <div
                                                style="width:100%;padding:11px 14px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                                Enter your username
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Password field -->
                                    <div style="margin-bottom:18px;text-align:left;">
                                        <div
                                            style="font-size:0.78rem;font-weight:600;color:#475569;margin-bottom:6px;letter-spacing:0.02em;">
                                            Password</div>
                                        <div style="position:relative;">
                                            <i class="bi bi-lock"
                                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <i class="bi bi-eye-slash"
                                                style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#64748b;font-size:1.05rem;z-index:1;"></i>
                                            <div
                                                style="width:100%;padding:11px 42px 11px 42px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.92rem;color:#94a3b8;background:#f1f5f9;font-family:'Inter',sans-serif;">
                                                Enter your password
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Remember Me & Forgot Password replica -->
                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:14px;text-align:left;">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div style="width:14px;height:14px;border:1.5px solid #cbd5e1;border-radius:4px;background:#fff;display:flex;align-items:center;justify-content:center;">
                                                <i class="bi bi-check" style="font-size:0.75rem;color:#4f46e5;"></i>
                                            </div>
                                            <span style="font-size:0.72rem;color:#475569;font-weight:500;">Remember username</span>
                                        </div>
                                        <span style="font-size:0.72rem;color:#4f46e5;font-weight:600;">Forgot password?</span>
                                    </div>

                                    <!-- Login button -->
                                    <div
                                        style="background:linear-gradient(135deg,#4f46e5 0%,#3730a3 100%);color:#fff;font-size:0.95rem;font-weight:700;border-radius:10px;padding:13px;text-align:center;letter-spacing:0.02em;font-family:'Inter',sans-serif;box-shadow:0 4px 12px rgba(79,70,229,0.25);">
                                        Login
                                    </div>
                                </div>
                            </div>

                            <!-- Page footer replica -->
                            <div
                                style="position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:0.62rem;color:rgba(255,255,255,0.85);text-shadow:0 1px 3px rgba(0,0,0,0.7);letter-spacing:0.01em;pointer-events:none;">
                                &copy; 2026 Genetian Builders &amp; Enterprises Inc. &nbsp;|&nbsp; Powered by <span
                                    style="font-weight:700;text-decoration:underline;">The Medyas</span>
                            </div>

                            <!-- Blur badge -->
                            <div style="
                                position: absolute; top: 10px; right: 10px;
                                background: rgba(0,0,0,0.55); backdrop-filter: blur(6px);
                                color: #fff; font-size: 0.72rem; font-weight: 700;
                                padding: 3px 9px; border-radius: 20px; letter-spacing: 0.02em;
                            ">
                                Blur: <span id="blurBadge"><?= $cur_blur ?>px</span>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- LEFT: Background image upload -->
                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-image-fill me-1 text-primary"></i>Background Image
                                </h6>
                                <form method="POST" action="process/process.php" enctype="multipart/form-data" class="mb-3"
                                    id="bgUploadForm">
                                    <input type="hidden" name="action" value="update_login_bg">
                                    <label class="form-label fw-bold small">Upload New Background</label>
                                    <div class="input-group mb-2">
                                        <input type="file" class="form-control form-control-sm" name="login_bg"
                                            accept="image/jpeg,image/png,image/webp,image/gif" id="bgFileInput" required>
                                        <button class="btn btn-brand btn-sm fw-bold px-3" type="submit">
                                            <i class="bi bi-upload me-1"></i>Upload
                                        </button>
                                    </div>
                                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>JPG, PNG, WEBP, GIF ·
                                        Max 5MB</small>
                                </form>

                                <?php if ($bg_path !== 'assets/img/default_login_bg.png'): ?>
                                    <form method="POST" action="process/process.php">
                                        <input type="hidden" name="action" value="reset_login_bg">
                                        <button class="btn btn-outline-danger btn-sm fw-bold w-100" type="submit"
                                            onclick="return confirm('Reset the background image to default?');">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default Image
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- RIGHT: Blur intensity slider -->
                            <div class="col-12 col-md-6">
                                <h6 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="bi bi-sliders me-1 text-primary"></i>Blur Intensity
                                </h6>
                                <form method="POST" action="process/process.php" id="blurForm">
                                    <input type="hidden" name="action" value="update_login_blur">

                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="form-label fw-bold small mb-0" for="blurSlider">Blur Amount</label>
                                        <span class="badge bg-primary" id="blurValueBadge"><?= $cur_blur ?>px</span>
                                    </div>

                                    <input type="range" class="form-range" id="blurSlider" name="login_blur" min="0"
                                        max="30" step="1" value="<?= $cur_blur ?>" style="accent-color: #4f46e5;">

                                    <div class="d-flex justify-content-between text-muted"
                                        style="font-size: 0.72rem; margin-top: -2px;">
                                        <span>0px (No blur)</span>
                                        <span>30px (Max blur)</span>
                                    </div>

                                    <!-- Preset buttons -->
                                    <div class="d-flex gap-2 flex-wrap mt-3 mb-3">
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="0">None</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="6">Light</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="12">Medium</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="20">Heavy</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm blur-preset"
                                            data-val="30">Max</button>
                                    </div>

                                    <button type="submit" class="btn btn-brand fw-bold w-100" id="saveBlurBtn">
                                        <i class="bi bi-save me-1"></i>Save Blur Setting
                                    </button>
                                </form>
                            </div>
                        </div><!-- /row -->

                    </div><!-- /card-body -->
                </div><!-- /card -->

                <script>
                    (function () {
                        const slider = document.getElementById('blurSlider');
                        const valueBadge = document.getElementById('blurValueBadge');
                        const blurBadge = document.getElementById('blurBadge');
                        const bgLayer = document.getElementById('previewBgLayer');
                        const bgFileInput = document.getElementById('bgFileInput');

                        function applyBlur(val) {
                            val = Math.max(0, Math.min(30, parseInt(val)));
                            const scale = (1 + val * 0.006).toFixed(3);
                            bgLayer.style.filter = `blur(${val}px)`;
                            bgLayer.style.transform = `scale(${scale})`;
                            valueBadge.textContent = val + 'px';
                            blurBadge.textContent = val + 'px';
                        }

                        slider.addEventListener('input', () => applyBlur(slider.value));

                        // Preset buttons
                        document.querySelectorAll('.blur-preset').forEach(btn => {
                            btn.addEventListener('click', () => {
                                slider.value = btn.dataset.val;
                                applyBlur(btn.dataset.val);
                            });
                        });

                        // Image file picker → live preview
                        bgFileInput.addEventListener('change', function () {
                            const file = this.files[0];
                            if (!file) return;
                            const reader = new FileReader();
                            reader.onload = e => {
                                bgLayer.style.backgroundImage = `url('${e.target.result}')`;
                            };
                            reader.readAsDataURL(file);
                        });
                    })();
                </script>
            <?php endif; ?>


        </div>
    </div>
</div>

<!-- Change Password Multi-Step Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel"
    aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-3">
                <h5 class="modal-title fw-bold text-dark fs-6" id="changePasswordModalLabel">
                    <i class="bi bi-shield-lock-fill text-primary me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Step Navigation Visual Indicator -->
                <div class="d-flex justify-content-center align-items-center mb-4">
                    <div class="d-flex align-items-center me-3" id="step1IndicatorWrapper">
                        <span
                            class="badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center"
                            id="step1Num" style="width: 28px; height: 28px;">1</span>
                        <span class="fw-bold small text-primary" id="step1Text">Verify Current</span>
                    </div>
                    <div class="border-top me-3" style="width: 40px; border-color: #dee2e6 !important;"
                        id="stepDivider"></div>
                    <div class="d-flex align-items-center" id="step2IndicatorWrapper">
                        <span
                            class="badge rounded-circle bg-secondary text-white me-2 d-inline-flex align-items-center justify-content-center"
                            id="step2Num" style="width: 28px; height: 28px;">2</span>
                        <span class="fw-bold small text-muted" id="step2Text">New Password</span>
                    </div>
                </div>

                <!-- Alert Message -->
                <div id="modalPassAlert" class="alert d-none shadow-sm mb-3"></div>

                <!-- STEP 1 FORM: Verify Current Password -->
                <form id="step1Form" onsubmit="handleVerifyStep1(event)">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalCurrPass"
                                placeholder="Enter your current password" required>
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalCurrPass', 'eyeModalCurr')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalCurr"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted small"><i class="bi bi-info-circle me-1"></i>Please enter your
                            existing password to verify your identity.</div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light border btn-sm fw-bold px-3"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-4" id="btnVerifyStep1">
                            Verify & Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

                <!-- STEP 2 FORM: Set New Password -->
                <form id="step2Form" class="d-none" onsubmit="handleUpdateStep2(event)">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalNewPass"
                                placeholder="Enter new password" required oninput="checkPasswordStrength(this.value)">
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalNewPass', 'eyeModalNew')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalNew"></i>
                            </button>
                        </div>
                        <!-- Password Strength Meter -->
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-secondary" role="progressbar" id="strengthMeter"
                                style="width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted fw-bold" id="strengthText" style="font-size: 0.75rem;">Password
                                Strength: Empty</small>
                        </div>

                        <!-- Requirements checklist -->
                        <div class="bg-light p-2 rounded border mt-2" style="font-size: 0.75rem;">
                            <div class="fw-bold text-secondary mb-1">Must contain:</div>
                            <div class="d-flex flex-wrap gap-2">
                                <span id="reqLen" class="text-muted"><i class="bi bi-dot"></i>8+ characters</span>
                                <span id="reqUpper" class="text-muted"><i class="bi bi-dot"></i>1 Uppercase (A-Z)</span>
                                <span id="reqLower" class="text-muted"><i class="bi bi-dot"></i>1 Lowercase (a-z)</span>
                                <span id="reqNum" class="text-muted"><i class="bi bi-dot"></i>1 Number (0-9)</span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control border-end-0" id="modalConfPass"
                                placeholder="Retype new password" required>
                            <button class="btn border border-start-0 bg-white" type="button"
                                onclick="togglePass('modalConfPass', 'eyeModalConf')">
                                <i class="bi bi-eye-slash text-muted" id="eyeModalConf"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="goToStep1()">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-4" id="btnUpdateStep2">
                            <i class="bi bi-check-circle me-1"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let verifiedCurrentPassword = "";

    function checkPasswordStrength(val) {
        const meter = document.getElementById('strengthMeter');
        const text = document.getElementById('strengthText');
        const reqLen = document.getElementById('reqLen');
        const reqUpper = document.getElementById('reqUpper');
        const reqLower = document.getElementById('reqLower');
        const reqNum = document.getElementById('reqNum');

        if (!meter || !text) return;

        const hasLen = val.length >= 8;
        const hasUpper = /[A-Z]/.test(val);
        const hasLower = /[a-z]/.test(val);
        const hasNum = /[0-9]/.test(val);
        const hasSpecial = /[^A-Za-z0-9]/.test(val);

        if (reqLen) reqLen.className = hasLen ? 'text-success fw-bold' : 'text-muted';
        if (reqUpper) reqUpper.className = hasUpper ? 'text-success fw-bold' : 'text-muted';
        if (reqLower) reqLower.className = hasLower ? 'text-success fw-bold' : 'text-muted';
        if (reqNum) reqNum.className = hasNum ? 'text-success fw-bold' : 'text-muted';

        if (val.length === 0) {
            meter.style.width = '0%';
            meter.className = 'progress-bar bg-secondary';
            text.innerText = 'Password Strength: Empty';
            text.className = 'text-muted fw-bold';
            return;
        }

        let score = 0;
        if (hasLen) score++;
        if (hasUpper) score++;
        if (hasLower) score++;
        if (hasNum) score++;
        if (hasSpecial) score++;

        if (score <= 2) {
            meter.style.width = '33%';
            meter.className = 'progress-bar bg-danger';
            text.innerText = 'Password Strength: Weak';
            text.className = 'text-danger fw-bold';
        } else if (score === 3 || score === 4) {
            meter.style.width = '66%';
            meter.className = 'progress-bar bg-warning';
            text.innerText = 'Password Strength: Medium';
            text.className = 'text-warning text-dark fw-bold';
        } else {
            meter.style.width = '100%';
            meter.className = 'progress-bar bg-success';
            text.innerText = 'Password Strength: Strong';
            text.className = 'text-success fw-bold';
        }
    }

    function showPassAlert(msg, type) {
        const alert = document.getElementById('modalPassAlert');
        alert.className = `alert alert-${type} shadow-sm mb-3`;
        alert.innerHTML = msg;
        alert.classList.remove('d-none');
    }

    function hidePassAlert() {
        const alert = document.getElementById('modalPassAlert');
        alert.classList.add('d-none');
    }

    function resetPasswordModal() {
        verifiedCurrentPassword = "";
        document.getElementById('step1Form').reset();
        document.getElementById('step2Form').reset();
        checkPasswordStrength("");
        goToStep1();
        hidePassAlert();
    }

    function goToStep1() {
        document.getElementById('step1Form').classList.remove('d-none');
        document.getElementById('step2Form').classList.add('d-none');

        document.getElementById('step1Num').className = 'badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step1Num').innerHTML = '1';
        document.getElementById('step1Text').className = 'fw-bold small text-primary';

        document.getElementById('step2Num').className = 'badge rounded-circle bg-secondary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step2Text').className = 'fw-bold small text-muted';
    }

    function goToStep2() {
        document.getElementById('step1Form').classList.add('d-none');
        document.getElementById('step2Form').classList.remove('d-none');

        document.getElementById('step1Num').className = 'badge rounded-circle bg-success text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step1Num').innerHTML = '<i class="bi bi-check"></i>';
        document.getElementById('step1Text').className = 'fw-bold small text-success';

        document.getElementById('step2Num').className = 'badge rounded-circle bg-primary text-white me-2 d-inline-flex align-items-center justify-content-center';
        document.getElementById('step2Text').className = 'fw-bold small text-primary';

        hidePassAlert();
    }

    async function handleVerifyStep1(e) {
        e.preventDefault();
        hidePassAlert();

        const currentPassInput = document.getElementById('modalCurrPass').value;
        const btn = document.getElementById('btnVerifyStep1');

        if (!currentPassInput) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Please enter your current password.', 'danger');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';

        try {
            const formData = new FormData();
            formData.append('action', 'verify_current_password');
            formData.append('current_password', currentPassInput);

            const res = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.status === 'success') {
                verifiedCurrentPassword = currentPassInput;
                goToStep2();
            } else {
                showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Incorrect password.'), 'danger');
            }
        } catch (err) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Verification failed. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Verify & Next <i class="bi bi-arrow-right ms-1"></i>';
        }
    }

    async function handleUpdateStep2(e) {
        e.preventDefault();
        hidePassAlert();

        const newPass = document.getElementById('modalNewPass').value;
        const confPass = document.getElementById('modalConfPass').value;
        const btn = document.getElementById('btnUpdateStep2');

        if (newPass.length < 8) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must be at least 8 characters long.', 'danger');
            return;
        }
        if (!/[A-Z]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one uppercase letter (A-Z).', 'danger');
            return;
        }
        if (!/[a-z]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one lowercase letter (a-z).', 'danger');
            return;
        }
        if (!/[0-9]/.test(newPass)) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Password must contain at least one number (0-9).', 'danger');
            return;
        }

        if (newPass !== confPass) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Passwords do not match.', 'danger');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';

        try {
            const formData = new FormData();
            formData.append('action', 'change_password_modal');
            formData.append('current_password', verifiedCurrentPassword);
            formData.append('new_password', newPass);
            formData.append('confirm_password', confPass);

            const res = await fetch('process/process.php', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.status === 'success') {
                showPassAlert('<i class="bi bi-check-circle-fill me-1"></i> ' + data.message, 'success');
                setTimeout(() => {
                    const modalEl = document.getElementById('changePasswordModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    window.location.reload();
                }, 1200);
            } else {
                showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + (data.message || 'Failed to update password.'), 'danger');
            }
        } catch (err) {
            showPassAlert('<i class="bi bi-exclamation-triangle-fill me-1"></i> Server error. Please try again.', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Update Password';
        }
    }

    // Simple Password Toggle for Profile Page
    window.togglePass = function (inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.replace("bi-eye-slash", "bi-eye");
        } else {
            input.type = "password";
            icon.classList.replace("bi-eye", "bi-eye-slash");
        }
    };

    // E-Signature Profile Fullscreen Modal & Preview Handler
    (function initProfileSigCanvas() {
        const fullModalEl = document.getElementById('profileFullSigModal');
        const fullCanvas = document.getElementById('profileFullSigCanvas');
        if (!fullModalEl || !fullCanvas) return;

        const fullCtx = fullCanvas.getContext('2d');
        let fullIsDrawing = false;
        let fullHasSignature = false;

        const fullPlaceholder = document.getElementById('profileFullSigPlaceholder');
        const clearFullBtn = document.getElementById('clearProfileFullSigBtn');
        const saveFullBtn = document.getElementById('saveProfileFullSigBtn');
        const sigDataInput = document.getElementById('profileSigData');
        const drawnPreviewWrap = document.getElementById('profileSigDrawnPreview');
        const previewImg = document.getElementById('profileSigPreviewImg');
        const submitBtn = document.getElementById('saveDrawnSigSubmitBtn');
        const clearDrawnBtn = document.getElementById('clearProfileDrawnSigBtn');

        fullModalEl.addEventListener('shown.bs.modal', function () {
            const botContainer = document.getElementById('cims-chatbot-container');
            if (botContainer) botContainer.style.setProperty('display', 'none', 'important');

            const rect = fullCanvas.getBoundingClientRect();
            const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;

            if (isRotated) {
                fullCanvas.width = rect.height;
                fullCanvas.height = rect.width;
            } else {
                fullCanvas.width = rect.width;
                fullCanvas.height = rect.height;
            }

            fullCtx.clearRect(0, 0, fullCanvas.width, fullCanvas.height);
            fullCtx.lineWidth = 3;
            fullCtx.lineCap = 'round';
            fullCtx.lineJoin = 'round';
            fullCtx.strokeStyle = '#0f172a';

            fullHasSignature = false;
            if (fullPlaceholder) fullPlaceholder.style.display = '';

            if (screen.orientation && screen.orientation.lock) {
                screen.orientation.lock('landscape').catch(() => { });
            } else if (screen.lockOrientation) {
                screen.lockOrientation('landscape');
            }
        });

        fullModalEl.addEventListener('hidden.bs.modal', function () {
            const botContainer = document.getElementById('cims-chatbot-container');
            if (botContainer) botContainer.style.removeProperty('display');

            if (screen.orientation && screen.orientation.unlock) {
                try { screen.orientation.unlock(); } catch (e) { }
            } else if (screen.unlockOrientation) {
                try { screen.unlockOrientation(); } catch (e) { }
            }
        });

        function getFullPos(e) {
            const rect = fullCanvas.getBoundingClientRect();
            if ((fullCanvas.width === 0 || fullCanvas.height === 0) && rect.width > 0 && rect.height > 0) {
                const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;
                if (isRotated) {
                    fullCanvas.width = Math.round(rect.height);
                    fullCanvas.height = Math.round(rect.width);
                } else {
                    fullCanvas.width = Math.round(rect.width);
                    fullCanvas.height = Math.round(rect.height);
                }
                fullCtx.lineWidth = 3;
                fullCtx.lineCap = 'round';
                fullCtx.lineJoin = 'round';
                fullCtx.strokeStyle = '#0f172a';
            }
            const clientX = (e.touches && e.touches.length > 0) ? e.touches[0].clientX : e.clientX;
            const clientY = (e.touches && e.touches.length > 0) ? e.touches[0].clientY : e.clientY;

            const isRotated = window.matchMedia("(max-width: 991px) and (orientation: portrait)").matches;

            if (isRotated) {
                const x = (clientY - rect.top) * (fullCanvas.width / (rect.height || 1));
                const y = (rect.right - clientX) * (fullCanvas.height / (rect.width || 1));
                return { x: x, y: y };
            } else {
                return {
                    x: (clientX - rect.left) * (fullCanvas.width / (rect.width || 1)),
                    y: (clientY - rect.top) * (fullCanvas.height / (rect.height || 1))
                };
            }
        }

        function startFullDrawing(e) {
            fullIsDrawing = true;
            const pos = getFullPos(e);
            fullCtx.beginPath();
            fullCtx.moveTo(pos.x, pos.y);
            if (fullPlaceholder) fullPlaceholder.style.display = 'none';
            fullHasSignature = true;
        }

        function drawFull(e) {
            if (!fullIsDrawing) return;
            e.preventDefault();
            const pos = getFullPos(e);
            fullCtx.lineWidth = 3;
            fullCtx.lineCap = 'round';
            fullCtx.lineJoin = 'round';
            fullCtx.strokeStyle = '#0f172a';
            fullCtx.lineTo(pos.x, pos.y);
            fullCtx.stroke();
        }

        function stopFullDrawing() {
            if (fullIsDrawing) {
                fullIsDrawing = false;
                fullCtx.beginPath();
            }
        }

        fullCanvas.addEventListener('mousedown', startFullDrawing);
        fullCanvas.addEventListener('mousemove', drawFull);
        fullCanvas.addEventListener('mouseup', stopFullDrawing);
        fullCanvas.addEventListener('mouseleave', stopFullDrawing);

        fullCanvas.addEventListener('touchstart', startFullDrawing, { passive: false });
        fullCanvas.addEventListener('touchmove', drawFull, { passive: false });
        fullCanvas.addEventListener('touchend', stopFullDrawing);

        if (clearFullBtn) {
            clearFullBtn.addEventListener('click', function () {
                fullCtx.clearRect(0, 0, fullCanvas.width, fullCanvas.height);
                fullCtx.beginPath();
                fullHasSignature = false;
                if (fullPlaceholder) fullPlaceholder.style.display = '';
            });
        }

        function resetDrawnSignature() {
            if (sigDataInput) sigDataInput.value = '';
            if (previewImg) previewImg.src = '';
            if (drawnPreviewWrap) drawnPreviewWrap.classList.add('d-none');
            if (submitBtn) submitBtn.disabled = true;
            fullHasSignature = false;
        }

        if (clearDrawnBtn) {
            clearDrawnBtn.addEventListener('click', resetDrawnSignature);
        }

        if (saveFullBtn) {
            saveFullBtn.addEventListener('click', function () {
                if (!fullHasSignature) {
                    alert("Please sign on the canvas first.");
                    return;
                }
                const dataUrl = fullCanvas.toDataURL('image/png');
                if (sigDataInput) sigDataInput.value = dataUrl;
                if (previewImg) previewImg.src = dataUrl;
                if (drawnPreviewWrap) drawnPreviewWrap.classList.remove('d-none');
                if (submitBtn) submitBtn.disabled = false;

                const modalInstance = bootstrap.Modal.getInstance(fullModalEl);
                if (modalInstance) modalInstance.hide();
            });
        }

        const drawForm = document.getElementById('drawSigForm');
        if (drawForm) {
            drawForm.addEventListener('submit', function (e) {
                const sigVal = sigDataInput ? sigDataInput.value : '';
                if (!sigVal || sigVal.trim() === '') {
                    e.preventDefault();
                    alert("Please draw your signature using the fullscreen pad before saving.");
                    return false;
                }
            });
        }
    })();
</script>

    <!-- ======================================================== -->
    <!-- MODAL: MFA (TOTP) SETUP WIZARD                           -->
    <!-- ======================================================== -->
    <div class="modal fade" id="mfaSetupModal" tabindex="-1" aria-labelledby="mfaSetupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down" style="max-width: 520px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-3 text-primary shadow-sm"
                            style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="mfaSetupModalLabel">Set Up Two-Factor Authentication</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Authenticator App (RFC 6238)</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <!-- Step 1: Scan QR -->
                    <div class="mb-4">
                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Step 1: Scan QR Code</h6>
                        <p class="text-muted small mb-3">
                            Open <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, or your preferred authenticator app, tap <em>"+"</em>, and scan the QR code below:
                        </p>
                        <div class="d-flex flex-column align-items-center justify-content-center p-3 border rounded-3 bg-white mb-2 shadow-sm text-center">
                            <div id="totpQrCodeContainer" class="p-2 bg-white rounded" style="min-width: 180px; min-height: 180px; display: flex; align-items: center; justify-content: center;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading QR Code...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Manual Key Entry Fallback -->
                        <div class="p-2 border rounded-3 bg-light text-center">
                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">Can't scan? Enter this key manually:</small>
                            <code class="fw-bold fs-6 text-primary user-select-all" id="totpSecretKeyDisplay">---- ---- ---- ----</code>
                        </div>
                    </div>

                    <!-- Step 2: Emergency Backup Codes -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold small text-uppercase text-secondary mb-0">Step 2: Save Emergency Recovery Codes</h6>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-bold" id="copyBackupCodesBtn">
                                <i class="bi bi-clipboard me-1"></i>Copy
                            </button>
                        </div>
                        <p class="text-muted small mb-2" style="font-size: 0.8rem;">
                            If you ever lose access to your phone, these single-use codes are the only way to recover your account:
                        </p>
                        <div class="p-3 border rounded-3 bg-light font-monospace small" id="backupCodesContainer" style="font-size: 0.85rem; line-height: 1.8;">
                            <!-- Populated via AJAX -->
                        </div>
                    </div>

                    <!-- Step 3: Confirmation Form -->
                    <div>
                        <h6 class="fw-bold small text-uppercase text-secondary mb-2">Step 3: Confirm 6-Digit Code</h6>
                        <form id="mfaSetupConfirmForm" autocomplete="off">
                            <input type="hidden" name="action" value="verify_and_enable">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                            <div class="mb-3">
                                <label for="mfaConfirmCodeInput" class="form-label small fw-bold text-dark">Enter 6-digit code shown in your app:</label>
                                <input type="text" class="form-control form-control-lg text-center fw-bold shadow-none" 
                                    id="mfaConfirmCodeInput" name="code" 
                                    placeholder="000000" maxlength="6" inputmode="numeric" 
                                    style="letter-spacing: 0.25em; font-size: 1.4rem; height: 50px;" required>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-brand py-2 fw-bold shadow-sm" id="mfaSetupSubmitBtn" style="border-radius: 10px; min-height: 44px;">
                                    <i class="bi bi-shield-check me-1"></i> Activate Two-Factor Authentication
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: MFA DISABLE CONFIRMATION                          -->
    <!-- ======================================================== -->
    <div class="modal fade" id="mfaDisableModal" tabindex="-1" aria-labelledby="mfaDisableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-danger-subtle rounded-3 text-danger shadow-sm"
                            style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="mfaDisableModalLabel">Disable 2FA Protection</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Security Confirmation</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="alert alert-warning border-0 small py-2 px-3 mb-3" style="border-radius: 8px;">
                        <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                        Disabling Two-Factor Authentication will reduce your account's protection to password-only authentication.
                    </div>
                    <form id="mfaDisableForm" autocomplete="off">
                        <input type="hidden" name="action" value="disable">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                        <div class="mb-3">
                            <label for="mfaDisablePasswordInput" class="form-label small fw-bold">Enter your account password to confirm:</label>
                            <input type="password" class="form-control" id="mfaDisablePasswordInput" name="password" placeholder="Current password" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger py-2 fw-bold shadow-sm" id="mfaDisableSubmitBtn" style="border-radius: 10px; min-height: 44px;">
                                <i class="bi bi-unlock-fill me-1"></i> Confirm &amp; Disable 2FA
                            </button>
                            <button type="button" class="btn btn-light py-2 border text-secondary fw-semibold" data-bs-dismiss="modal" style="border-radius: 10px; min-height: 44px;">
                                Keep 2FA Enabled
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: REGENERATE BACKUP RECOVERY CODES                  -->
    <!-- ======================================================== -->
    <div class="modal fade" id="mfaBackupModal" tabindex="-1" aria-labelledby="mfaBackupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-primary-subtle rounded-3 text-primary shadow-sm"
                            style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="bi bi-key-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="mfaBackupModalLabel">Emergency Recovery Codes</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Single-Use Backup Codes</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div id="regenPromptSection">
                        <p class="text-muted small mb-3">
                            Generating new backup codes will immediately invalidate all previously issued recovery codes. Please enter your password to proceed:
                        </p>
                        <form id="regenBackupCodesForm" autocomplete="off">
                            <input type="hidden" name="action" value="regenerate_backup_codes">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                            <div class="mb-3">
                                <label for="regenPasswordInput" class="form-label small fw-bold">Current Account Password:</label>
                                <input type="password" class="form-control" id="regenPasswordInput" name="password" placeholder="Enter password" required>
                            </div>

                            <button type="submit" class="btn btn-brand w-100 py-2 fw-bold shadow-sm" id="regenSubmitBtn" style="border-radius: 10px; min-height: 44px;">
                                <i class="bi bi-arrow-repeat me-1"></i> Generate 8 New Codes
                            </button>
                        </form>
                    </div>

                    <div id="regenResultSection" class="d-none">
                        <p class="text-success small fw-semibold mb-2">
                            <i class="bi bi-check-circle-fill me-1"></i> 8 Fresh Backup Codes Generated:
                        </p>
                        <div class="p-3 border rounded-3 bg-light font-monospace small mb-3" id="freshBackupCodesDisplay" style="font-size: 0.9rem; line-height: 1.8;">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 py-2" id="copyFreshCodesBtn">
                                <i class="bi bi-clipboard me-1"></i> Copy Codes
                            </button>
                            <button type="button" class="btn btn-brand btn-sm fw-bold w-100 py-2" data-bs-dismiss="modal">
                                <i class="bi bi-check2 me-1"></i> Done
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL: REGISTER PASSKEY / BIOMETRIC                      -->
    <!-- ======================================================== -->
    <div class="modal fade" id="passkeyRegisterModal" tabindex="-1" aria-labelledby="passkeyRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center bg-success-subtle rounded-3 text-success shadow-sm"
                            style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="bi bi-fingerprint"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="passkeyRegisterModalLabel">Register Biometric Passkey</h5>
                            <small class="text-muted" style="font-size: 0.8rem;">Windows Hello / Touch ID / Face ID</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-muted small mb-3">
                        Link this device's biometric sensor or security key to your SiteWare account for passwordless, 1-second sign-in.
                    </p>

                    <form id="passkeyRegisterForm" autocomplete="off">
                        <div class="mb-3">
                            <label for="passkeyDeviceNameInput" class="form-label small fw-bold">Device Nickname:</label>
                            <input type="text" class="form-control" id="passkeyDeviceNameInput" placeholder="e.g. Work Laptop (Windows Hello)" required>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">A friendly name to help you recognize this device.</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success py-2 fw-bold shadow-sm" id="passkeyRegisterSubmitBtn" style="border-radius: 10px; min-height: 44px;">
                                <i class="bi bi-shield-check me-1"></i> Start Biometric Scan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include 'layout/footer.php'; ?>

<!-- Offline QR Code Generator Library with Cache Buster -->
<script src="assets/js/qrcode.min.js?v=<?= filemtime(__DIR__ . '/assets/js/qrcode.min.js') ?>"></script>

<script>
    // =========================================================================
    // PROFILE MFA (TOTP) & PASSKEY MANAGEMENT HANDLERS
    // (Standards: cims-modal-ajax-handler & quality-standards)
    // =========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        // Helpers
        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }

        function base64UrlToBuffer(base64Url) {
            let padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
            let base64 = (base64Url + padding).replace(/\-/g, '+').replace(/_/g, '/');
            let rawData = window.atob(base64);
            let outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray.buffer;
        }

        function bufferToBase64Url(buffer) {
            let binary = '';
            let bytes = new Uint8Array(buffer);
            for (let i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }

        // --- 1. TOTP SETUP WIZARD ---
        const startSetupBtn = document.getElementById('startTotpSetupBtn');
        const setupModalEl = document.getElementById('mfaSetupModal');
        const qrContainer = document.getElementById('totpQrCodeContainer');
        const secretKeyDisplay = document.getElementById('totpSecretKeyDisplay');
        const backupCodesContainer = document.getElementById('backupCodesContainer');
        const copyBackupBtn = document.getElementById('copyBackupCodesBtn');
        const setupForm = document.getElementById('mfaSetupConfirmForm');
        const setupSubmitBtn = document.getElementById('mfaSetupSubmitBtn');
        const confirmCodeInput = document.getElementById('mfaConfirmCodeInput');

        let currentBackupCodes = [];

        function getModal(el) {
            if (!el || typeof bootstrap === 'undefined') return null;
            return bootstrap.Modal.getOrCreateInstance(el);
        }

        if (startSetupBtn) {
            startSetupBtn.addEventListener('click', async function () {
                const originalHtml = startSetupBtn.innerHTML;
                startSetupBtn.disabled = true;
                startSetupBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Preparing...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'setup');
                    formData.append('csrf_token', getCsrfToken());

                    const res = await fetch('process/mfa_totp.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (!data.success) {
                        throw new Error(data.message || 'Failed to initialize 2FA setup.');
                    }

                    // Render QR Code
                    qrContainer.innerHTML = '';
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(qrContainer, {
                            text: data.otpauth_url,
                            width: 170,
                            height: 170,
                            colorDark: "#0f172a",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    } else {
                        qrContainer.innerHTML = '<div class="alert alert-warning small mb-0 py-2">Please enter the secret key manually below into your app.</div>';
                    }

                    // Display formatted secret
                    if (secretKeyDisplay) {
                        secretKeyDisplay.textContent = data.secret_formatted || data.secret;
                    }

                    // Display backup codes in 2 columns
                    currentBackupCodes = data.backup_codes || [];
                    if (backupCodesContainer) {
                        let codesHtml = '<div class="row g-2">';
                        currentBackupCodes.forEach(code => {
                            codesHtml += `<div class="col-6"><span class="badge bg-white text-dark border px-2 py-1 w-100 text-center font-monospace">${code}</span></div>`;
                        });
                        codesHtml += '</div>';
                        backupCodesContainer.innerHTML = codesHtml;
                    }

                    // Open modal
                    const modal = getModal(setupModalEl);
                    if (modal) modal.show();
                } catch (err) {
                    console.error('Setup error:', err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Setup Error', text: err.message });
                    } else {
                        alert('Setup Error: ' + err.message);
                    }
                } finally {
                    startSetupBtn.disabled = false;
                    startSetupBtn.innerHTML = originalHtml;
                }
            });
        }

        // Copy Backup Codes
        if (copyBackupBtn) {
            copyBackupBtn.addEventListener('click', function () {
                if (!currentBackupCodes.length) return;
                navigator.clipboard.writeText(currentBackupCodes.join('\n')).then(() => {
                    copyBackupBtn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Copied!';
                    setTimeout(() => copyBackupBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy', 2000);
                });
            });
        }

        // Confirm & Enable Form
        if (setupForm) {
            setupForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const code = confirmCodeInput ? confirmCodeInput.value.trim() : '';
                if (!code) {
                    if (confirmCodeInput) confirmCodeInput.focus();
                    return;
                }

                const originalBtnText = setupSubmitBtn ? setupSubmitBtn.innerHTML : 'Activate';
                if (setupSubmitBtn) {
                    setupSubmitBtn.disabled = true;
                    setupSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Activating...';
                }

                try {
                    const formData = new FormData(setupForm);
                    const res = await fetch('process/mfa_totp.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        const modal = getModal(setupModalEl);
                        if (modal) modal.hide();

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '2FA Enabled!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Activation failed.');
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Verification Failed', text: err.message });
                    } else {
                        alert(err.message);
                    }
                    if (confirmCodeInput) {
                        confirmCodeInput.select();
                        confirmCodeInput.focus();
                    }
                } finally {
                    if (setupSubmitBtn) {
                        setupSubmitBtn.disabled = false;
                        setupSubmitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }

        // --- 2. DISABLE MFA ---
        const disableForm = document.getElementById('mfaDisableForm');
        const disableSubmitBtn = document.getElementById('mfaDisableSubmitBtn');
        const disableModalEl = document.getElementById('mfaDisableModal');

        if (disableForm) {
            disableForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const originalBtnText = disableSubmitBtn ? disableSubmitBtn.innerHTML : 'Confirm';
                if (disableSubmitBtn) {
                    disableSubmitBtn.disabled = true;
                    disableSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Disabling...';
                }

                try {
                    const formData = new FormData(disableForm);
                    const res = await fetch('process/mfa_totp.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        const modal = getModal(disableModalEl);
                        if (modal) modal.hide();

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: '2FA Disabled',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Could not disable 2FA.');
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        alert(err.message);
                    }
                } finally {
                    if (disableSubmitBtn) {
                        disableSubmitBtn.disabled = false;
                        disableSubmitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }

        // --- 3. REGENERATE BACKUP CODES ---
        const openRegenBtn = document.getElementById('openRegenBackupCodesBtn');
        const backupModalEl = document.getElementById('mfaBackupModal');
        const regenForm = document.getElementById('regenBackupCodesForm');
        const regenSubmitBtn = document.getElementById('regenSubmitBtn');
        const regenPromptSec = document.getElementById('regenPromptSection');
        const regenResultSec = document.getElementById('regenResultSection');
        const freshCodesDisplay = document.getElementById('freshBackupCodesDisplay');
        const copyFreshBtn = document.getElementById('copyFreshCodesBtn');

        let freshCodesList = [];

        if (openRegenBtn) {
            openRegenBtn.addEventListener('click', function () {
                if (regenPromptSec) regenPromptSec.classList.remove('d-none');
                if (regenResultSec) regenResultSec.classList.add('d-none');
                const passInput = document.getElementById('regenPasswordInput');
                if (passInput) passInput.value = '';
                const modal = getModal(backupModalEl);
                if (modal) modal.show();
            });
        }

        if (regenForm) {
            regenForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const originalBtnText = regenSubmitBtn ? regenSubmitBtn.innerHTML : 'Generate';
                if (regenSubmitBtn) {
                    regenSubmitBtn.disabled = true;
                    regenSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
                }

                try {
                    const formData = new FormData(regenForm);
                    const res = await fetch('process/mfa_totp.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        freshCodesList = data.backup_codes || [];
                        let html = '<div class="row g-2">';
                        freshCodesList.forEach(c => {
                            html += `<div class="col-6"><span class="badge bg-white text-dark border px-2 py-1 w-100 text-center font-monospace">${c}</span></div>`;
                        });
                        html += '</div>';
                        if (freshCodesDisplay) freshCodesDisplay.innerHTML = html;

                        if (regenPromptSec) regenPromptSec.classList.add('d-none');
                        if (regenResultSec) regenResultSec.classList.remove('d-none');
                    } else {
                        throw new Error(data.message || 'Failed to regenerate codes.');
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        alert(err.message);
                    }
                } finally {
                    if (regenSubmitBtn) {
                        regenSubmitBtn.disabled = false;
                        regenSubmitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }

        if (copyFreshBtn) {
            copyFreshBtn.addEventListener('click', function () {
                if (!freshCodesList.length) return;
                navigator.clipboard.writeText(freshCodesList.join('\n')).then(() => {
                    copyFreshBtn.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Copied!';
                    setTimeout(() => copyFreshBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy Codes', 2000);
                });
            });
        }

        // --- 4. PASSKEY / BIOMETRIC REGISTRATION ---
        const openPasskeyBtn = document.getElementById('openRegisterPasskeyBtn');
        const passkeyModalEl = document.getElementById('passkeyRegisterModal');
        const passkeyForm = document.getElementById('passkeyRegisterForm');
        const passkeySubmitBtn = document.getElementById('passkeyRegisterSubmitBtn');
        const passkeyDeviceInput = document.getElementById('passkeyDeviceNameInput');

        if (openPasskeyBtn) {
            openPasskeyBtn.addEventListener('click', function () {
                if (!window.PublicKeyCredential) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Passkeys Unsupported',
                            text: 'Your current browser or device does not support WebAuthn / Passkeys.'
                        });
                    } else {
                        alert('Passkeys are not supported on this browser.');
                    }
                    return;
                }
                if (passkeyDeviceInput) {
                    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                    const isWin = navigator.platform.toUpperCase().indexOf('WIN') >= 0;
                    const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
                    let defaultName = 'Work Device';
                    if (isWin) defaultName = 'Windows PC (Hello)';
                    else if (isMac) defaultName = 'Mac (Touch ID)';
                    else if (isMobile) defaultName = 'Mobile Phone Biometric';
                    passkeyDeviceInput.value = defaultName;
                }
                const modal = getModal(passkeyModalEl);
                if (modal) modal.show();
            });
        }

        if (passkeyForm) {
            passkeyForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const deviceName = passkeyDeviceInput ? passkeyDeviceInput.value.trim() : '';
                if (!deviceName) {
                    if (passkeyDeviceInput) passkeyDeviceInput.focus();
                    return;
                }

                const originalBtnText = passkeySubmitBtn ? passkeySubmitBtn.innerHTML : 'Scan';
                if (passkeySubmitBtn) {
                    passkeySubmitBtn.disabled = true;
                    passkeySubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Scanning biometric...';
                }

                try {
                    // 1. Get creation options
                    const optRes = await fetch('process/passkey_handler.php?action=get_registration_options');
                    const optData = await optRes.json();

                    if (!optData.success || !optData.options) {
                        throw new Error(optData.message || 'Failed to get registration options.');
                    }

                    const options = optData.options;
                    const publicKeyCredentialCreationOptions = {
                        challenge: base64UrlToBuffer(options.challenge),
                        rp: options.rp,
                        user: {
                            id: base64UrlToBuffer(options.user.id),
                            name: options.user.name,
                            displayName: options.user.displayName
                        },
                        pubKeyCredParams: options.pubKeyCredParams,
                        authenticatorSelection: options.authenticatorSelection,
                        timeout: options.timeout || 60000,
                        attestation: options.attestation || 'none',
                        excludeCredentials: (options.excludeCredentials || []).map(c => ({
                            id: base64UrlToBuffer(c.id),
                            type: c.type,
                            transports: c.transports
                        }))
                    };

                    // 2. Browser native biometric prompt
                    const credential = await navigator.credentials.create({
                        publicKey: publicKeyCredentialCreationOptions
                    });

                    if (!credential) {
                        throw new Error('Biometric registration was cancelled.');
                    }

                    // 3. Submit attestation to server
                    const savePayload = new FormData();
                    savePayload.append('action', 'save_registration');
                    savePayload.append('device_name', deviceName);
                    savePayload.append('clientDataJSON', bufferToBase64Url(credential.response.clientDataJSON));
                    savePayload.append('attestationObject', bufferToBase64Url(credential.response.attestationObject));
                    savePayload.append('csrf_token', getCsrfToken());

                    const saveRes = await fetch('process/passkey_handler.php', {
                        method: 'POST',
                        body: savePayload,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const saveData = await saveRes.json();

                    if (saveData.success) {
                        const modal = getModal(passkeyModalEl);
                        if (modal) modal.hide();

                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Passkey Linked!',
                                text: saveData.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(saveData.message || 'Failed to save biometric credential.');
                    }
                } catch (err) {
                    console.error('Passkey Registration Error:', err);
                    if (err.name !== 'NotAllowedError' && err.name !== 'AbortError') {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Registration Failed', text: err.message });
                        } else {
                            alert(err.message);
                        }
                    }
                } finally {
                    if (passkeySubmitBtn) {
                        passkeySubmitBtn.disabled = false;
                        passkeySubmitBtn.innerHTML = originalBtnText;
                    }
                }
            });
        }

        // --- 5. DELETE PASSKEY ---
        document.querySelectorAll('.delete-passkey-btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const passkeyId = this.getAttribute('data-id');
                const passkeyName = this.getAttribute('data-name');

                let confirmed = false;
                if (typeof Swal !== 'undefined') {
                    const confirmRes = await Swal.fire({
                        icon: 'warning',
                        title: 'Remove Passkey?',
                        text: `Are you sure you want to remove "${passkeyName}"? You won't be able to log in with this biometric device anymore.`,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Remove It',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#dc2626'
                    });
                    confirmed = confirmRes.isConfirmed;
                } else {
                    confirmed = confirm(`Are you sure you want to remove "${passkeyName}"?`);
                }

                if (!confirmed) return;

                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_passkey');
                    formData.append('passkey_id', passkeyId);
                    formData.append('csrf_token', getCsrfToken());

                    const res = await fetch('process/passkey_handler.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        const row = document.getElementById(`passkey_row_${passkeyId}`);
                        if (row) {
                            row.style.transition = 'opacity 0.4s ease';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 400);
                        }
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Removed', text: data.message, timer: 1500, showConfirmButton: false });
                        }
                    } else {
                        throw new Error(data.message || 'Could not remove passkey.');
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        alert(err.message);
                    }
                }
            });
        });

        // --- 6. REMOTE LOGOUT & REVOKE SESSION ---
        document.querySelectorAll('.revoke-session-btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const sessionId = this.getAttribute('data-id');
                const deviceName = this.getAttribute('data-name');

                let confirmed = false;
                if (typeof Swal !== 'undefined') {
                    const confirmRes = await Swal.fire({
                        icon: 'warning',
                        title: 'Sign Out & Revoke Device?',
                        html: `<p class="mb-2">Are you sure you want to sign out <strong>"${deviceName}"</strong>?</p><small class="text-muted d-block">This device will be immediately logged out of SiteWare, and its 30-day 2FA bypass token will be erased.</small>`,
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Yes, Log Out Device',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#dc2626',
                        reverseButtons: true
                    });
                    confirmed = confirmRes.isConfirmed;
                } else {
                    confirmed = confirm(`Sign out and revoke "${deviceName}"?`);
                }

                if (!confirmed) return;

                const origHtml = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Signing out...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'revoke_session_and_device');
                    formData.append('session_id', sessionId);
                    formData.append('csrf_token', getCsrfToken());

                    const res = await fetch('process/manage_trusted_device.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        const row = document.getElementById(`session_row_${sessionId}`);
                        if (row) {
                            row.style.transition = 'opacity 0.4s ease';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                window.location.reload();
                            }, 400);
                        }
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Device Signed Out', text: data.message, timer: 1500, showConfirmButton: false });
                        }
                    } else {
                        throw new Error(data.message || 'Could not sign out device.');
                    }
                } catch (err) {
                    this.disabled = false;
                    this.innerHTML = origHtml;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        alert(err.message);
                    }
                }
            });
        });

        // --- 7. SIGN OUT ALL OTHER SESSIONS ---
        const signoutAllOthersBtn = document.querySelector('.signout-all-others-btn');
        if (signoutAllOthersBtn) {
            signoutAllOthersBtn.addEventListener('click', async function () {
                let confirmed = false;
                if (typeof Swal !== 'undefined') {
                    const confirmRes = await Swal.fire({
                        icon: 'warning',
                        title: 'Sign Out All Other Devices?',
                        html: '<p class="mb-2">This will immediately terminate all active login sessions on any other phones, laptops, or browsers, and revoke their 2FA bypass tokens.</p><strong class="text-success small"><i class="bi bi-shield-check me-1"></i>Only this current device will stay logged in.</strong>',
                        showCancelButton: true,
                        confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Yes, Sign Out Others',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#dc2626',
                        reverseButtons: true
                    });
                    confirmed = confirmRes.isConfirmed;
                } else {
                    confirmed = confirm('Are you sure you want to sign out all other devices?');
                }

                if (!confirmed) return;

                const origHtml = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'revoke_session_and_device');
                    formData.append('revoke_all_others', '1');
                    formData.append('csrf_token', getCsrfToken());

                    const res = await fetch('process/manage_trusted_device.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            await Swal.fire({ icon: 'success', title: 'Other Devices Signed Out', text: data.message, timer: 1500, showConfirmButton: false });
                        }
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Could not sign out devices.');
                    }
                } catch (err) {
                    this.disabled = false;
                    this.innerHTML = origHtml;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                    } else {
                        alert(err.message);
                    }
                }
            });
        }
    });
</script>