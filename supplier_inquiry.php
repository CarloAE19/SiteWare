<?php
// ============================================================================
// SUPPLIER INQUIRY & AVAILABILITY PORTAL
// Public-facing, secure tokenized portal for vendor stock & pricing response
// Accessible on smartphones via Viber, SMS, or Email link
// ============================================================================

require_once __DIR__ . '/Connection/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
if (empty($token)) {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('/(?:supplier_inquiry|inquiry)\/([a-f0-9]{48})/i', $reqPath, $matches)) {
        $token = $matches[1];
    }
}
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
    || (isset($_POST['is_ajax']) && $_POST['is_ajax'] === '1');

$inquiry = null;
$items = [];
$errorMsg = null;
$isExpired = false;
$submittedSuccess = false;

// 1. Token Validation
if (empty($token) || !preg_match('/^[a-f0-9]{48}$/i', $token)) {
    $errorMsg = "Invalid or missing inquiry authorization token. Please access this portal using the link sent to your Viber or phone.";
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                si.*, 
                s.company_name, 
                s.contact_person, 
                s.contact_number, 
                s.email AS supplier_email,
                r.rs_no,
                r.project_name,
                u.name AS purchasing_officer_name
            FROM supplier_inquiries si
            LEFT JOIN suppliers s ON si.supplier_id = s.id
            LEFT JOIN requisitions r ON si.rs_id = r.id
            LEFT JOIN users u ON si.created_by = u.id
            WHERE si.token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inquiry) {
            $errorMsg = "Inquiry not found or link has expired. Please contact purchasing for an updated link.";
        } else {
            $now = new DateTime();
            $exp = new DateTime($inquiry['expires_at']);
            if ($now > $exp) {
                $isExpired = true;
            }

            // Fetch items
            $itemStmt = $pdo->prepare("
                SELECT * FROM supplier_inquiry_items 
                WHERE inquiry_id = ? 
                ORDER BY id ASC
            ");
            $itemStmt->execute([$inquiry['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            $isLocked = (($inquiry['status'] ?? '') === 'Responded');
        }
    } catch (PDOException $e) {
        error_log("Supplier inquiry error: " . $e->getMessage());
        $errorMsg = "A database error occurred while verifying your request.";
    }
}

// 2. Handle POST Submission (Supplier Submits Stock & Price Response)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$errorMsg && $inquiry) {
    if (!empty($isLocked)) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'This inquiry response has already been submitted and is locked.']);
            exit;
        }
        $errorMsg = "This inquiry has already been submitted and is locked for editing. Please contact purchasing if changes are required.";
    } elseif ($isExpired) {
        if ($isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'This inquiry has expired. Please contact purchasing.']);
            exit;
        }
        $errorMsg = "This inquiry has expired. Please contact purchasing for an updated link.";
    } else {
        $itemIds = $_POST['item_ids'] ?? [];
        $availStatuses = $_POST['availability_status'] ?? [];
        $availQtys = $_POST['available_qty'] ?? [];
        $offeredPrices = $_POST['offered_price'] ?? [];
        $itemRemarks = $_POST['item_remarks'] ?? [];
        $supplierGeneralNotes = trim($_POST['supplier_notes'] ?? '');

        try {
            $pdo->beginTransaction();

            $updateItemStmt = $pdo->prepare("
                UPDATE supplier_inquiry_items 
                SET 
                    availability_status = ?,
                    available_qty = ?,
                    offered_price = ?,
                    item_remarks = ?
                WHERE id = ? AND inquiry_id = ?
            ");

            $availCount = 0;
            foreach ($itemIds as $index => $itemId) {
                $itemId = (int)$itemId;
                $status = $availStatuses[$index] ?? '';
                if (!in_array($status, ['Available', 'Partial', 'Unavailable'])) {
                    throw new Exception("Please select an availability decision for all items before submitting.");
                }

                if ($status === 'Available') {
                    $availCount++;
                }

                $qty = isset($availQtys[$index]) ? (int)$availQtys[$index] : 0;
                if ($status === 'Unavailable') {
                    $qty = 0;
                }

                $price = !empty($offeredPrices[$index]) ? (float)$offeredPrices[$index] : null;
                $remark = trim($itemRemarks[$index] ?? '');

                $updateItemStmt->execute([
                    $status,
                    $qty,
                    $price,
                    $remark,
                    $itemId,
                    $inquiry['id']
                ]);
            }

            // Update Inquiry Header
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $updateInqStmt = $pdo->prepare("
                UPDATE supplier_inquiries 
                SET 
                    status = 'Responded',
                    responded_at = NOW(),
                    supplier_ip = ?,
                    supplier_notes = ?
                WHERE id = ?
            ");
            $updateInqStmt->execute([$clientIp, $supplierGeneralNotes, $inquiry['id']]);

            // Dispatch In-App Notification (Database)
            $supplierName = !empty($inquiry['company_name']) ? $inquiry['company_name'] : 'Supplier';
            $inqNo = $inquiry['inquiry_no'];
            $totalCount = count($itemIds);
            $creatorId = !empty($inquiry['created_by']) ? (int)$inquiry['created_by'] : null;

            $notifTitle = "Supplier Responded: " . $supplierName;
            $notifBody = "{$supplierName} has submitted stock & pricing for Inquiry #{$inqNo} ({$availCount}/{$totalCount} items available). Ready for PO generation.";

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (target_user_id, target_role, title, message, is_read, created_at)
                VALUES (?, 'purchasing', ?, ?, 0, NOW())
            ");
            $notifStmt->execute([$creatorId, $notifTitle, $notifBody]);

            $pdo->commit();

            // Dispatch Real-Time Web Push Notification (FCM / Smartphone & Desktop)
            if (file_exists(__DIR__ . '/Connection/fcm_helper.php')) {
                require_once __DIR__ . '/Connection/fcm_helper.php';
                if (function_exists('sendPushNotification')) {
                    try {
                        // Push to all officers with role 'purchasing'
                        sendPushNotification($pdo, $notifTitle, $notifBody, 'purchasing', null, 'supplier_inquiries');

                        // If creator has a different role (e.g. admin), also push to them
                        if ($creatorId) {
                            $cRoleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                            $cRoleStmt->execute([$creatorId]);
                            $cRole = $cRoleStmt->fetchColumn();
                            if ($cRole && $cRole !== 'purchasing') {
                                sendPushNotification($pdo, $notifTitle, $notifBody, null, $creatorId, 'supplier_inquiries');
                            }
                        }
                    } catch (Exception $pushEx) {
                        error_log("FCM Push notification error on inquiry response: " . $pushEx->getMessage());
                    }
                }
            }

            if ($isAjax) {
                echo json_encode(['status' => 'success', 'message' => 'Thank you! Your availability response has been recorded.']);
                exit;
            }

            $submittedSuccess = true;
            $isLocked = true;

            // Re-fetch updated inquiry & items
            $stmt->execute([$token]);
            $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

            $itemStmt->execute([$inquiry['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($isAjax) {
                echo json_encode(['status' => 'error', 'message' => 'Submission failed: ' . $e->getMessage()]);
                exit;
            }
            $errorMsg = "Unable to save your response. Please try again or contact purchasing.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($inquiry['inquiry_no'] ?? 'Supplier Portal') ?> - Stock Availability Inquiry</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #002B49;
            --brand-secondary: #004B87;
            --brand-accent: #f59e0b;
            --brand-success: #10b981;
            --brand-danger: #ef4444;
            --brand-surface: #f8fafc;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            padding-bottom: 60px;
        }
        .portal-header {
            background: linear-gradient(135deg, #002B49 0%, #004B87 100%);
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 43, 73, 0.15);
        }
        .brand-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
        }
        .item-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .item-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .status-pill-btn {
            font-size: 0.90rem;
            font-weight: 600;
            padding: 10px 14px;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .form-control, .form-select {
            min-height: 44px;
            font-size: 0.95rem;
            border-radius: 8px;
        }
        .form-control:focus {
            border-color: #004B87;
            box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.15);
        }
        .touch-btn {
            min-height: 48px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 10px;
        }
        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .item-card {
                padding: 12px !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Header -->
    <header class="portal-header py-3 px-3 mb-4">
        <div class="container max-w-lg">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="bg-white text-dark rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px;">
                        <i class="bi bi-buildings-fill text-primary fs-5"></i>
                    </div>
                    <div>
                        <h1 class="h6 mb-0 fw-bold text-white tracking-wide">GB Construction & Enterprise Inc.</h1>
                        <span class="small text-white-50">Official Vendor Material Inquiry Portal</span>
                    </div>
                </div>
                <div>
                    <span class="badge brand-badge px-3 py-1.5 rounded-pill font-monospace" style="font-size: 0.78rem;">
                        <i class="bi bi-shield-check me-1 text-success"></i>Secured Session
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="max-width: 820px;">

        <?php if ($errorMsg): ?>
            <!-- Error Alert Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center my-4 bg-white">
                <div class="rounded-circle bg-danger-subtle text-danger mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
                <h3 class="h5 fw-bold text-dark">Access Authorization Notice</h3>
                <p class="text-muted mb-4"><?= htmlspecialchars($errorMsg) ?></p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="tel:+639000000000" class="btn btn-outline-primary touch-btn px-4">
                        <i class="bi bi-telephone me-1"></i> Contact Purchasing
                    </a>
                </div>
            </div>

        <?php elseif ($isExpired): ?>
            <!-- Expired Notice -->
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center my-4 bg-white">
                <div class="rounded-circle bg-warning-subtle text-warning mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                    <i class="bi bi-clock-history fs-2"></i>
                </div>
                <h3 class="h5 fw-bold text-dark">Inquiry Link Expired</h3>
                <p class="text-muted mb-3">
                    This inquiry (<strong><?= htmlspecialchars($inquiry['inquiry_no']) ?></strong>) expired on 
                    <strong><?= date('M d, Y h:i A', strtotime($inquiry['expires_at'])) ?></strong>.
                </p>
                <p class="small text-secondary mb-4">
                    To maintain strict quote freshness and pricing accuracy, supplier links are automatically deactivated after 48 hours. Please request a refreshed link from our purchasing officer.
                </p>
            </div>

        <?php else: ?>

            <?php if ($submittedSuccess): ?>
                <!-- Submission Success Banner -->
                <div class="alert alert-success border-0 shadow-sm rounded-4 p-3.5 mb-4 d-flex align-items-start gap-3 bg-success text-white">
                    <i class="bi bi-check-circle-fill fs-3 mt-0.5"></i>
                    <div>
                        <h4 class="h6 fw-bold mb-1">Availability Confirmation Received!</h4>
                        <p class="mb-0 small text-white-50">
                            Thank you! Your stock availability and pricing confirmation has been transmitted directly to our purchasing team. We will review and proceed with PO generation shortly.
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Inquiry Header Summary Card -->
            <div class="card border-0 shadow-sm rounded-4 p-3.5 p-md-4 mb-4 bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 pb-3 border-bottom">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-primary text-white font-monospace px-2.5 py-1">
                                <?= htmlspecialchars($inquiry['inquiry_no']) ?>
                            </span>
                            <?php if ($isLocked): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                    <i class="bi bi-lock-fill me-1"></i>Response Recorded & Locked
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2.5 py-1">
                                    <i class="bi bi-hourglass-split me-1"></i>Awaiting Your Confirmation
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h5 fw-bold text-dark mb-0">
                            Attention: <?= htmlspecialchars($inquiry['company_name'] ?: 'Valued Supplier Partner') ?>
                        </h2>
                        <?php if (!empty($inquiry['contact_person'])): ?>
                            <small class="text-muted d-block mt-0.5">
                                <i class="bi bi-person me-1"></i>Attn: <?= htmlspecialchars($inquiry['contact_person']) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm-end">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.70rem;">Expires In</small>
                        <span class="text-danger fw-bold small">
                            <i class="bi bi-stopwatch me-1"></i><?= date('M d, Y h:i A', strtotime($inquiry['expires_at'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Logistics Parameters -->
                <div class="row g-2 mt-2 pt-1 text-secondary" style="font-size: 0.84rem;">
                    <div class="col-12 col-md-6">
                        <strong><i class="bi bi-geo-alt text-primary me-1"></i>Target Drop Destination:</strong>
                        <div class="text-dark fw-medium ps-3 mt-0.5">
                            <?= htmlspecialchars($inquiry['delivery_destination'] ?: 'Central Warehouse (Main Storage)') ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <strong><i class="bi bi-calendar-event text-primary me-1"></i>Target Delivery Date:</strong>
                        <div class="text-dark fw-medium ps-3 mt-0.5">
                            <?= !empty($inquiry['expected_delivery_date']) ? date('F d, Y', strtotime($inquiry['expected_delivery_date'])) : 'As soon as available' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inquiry Interactive Form -->
            <form id="supplierInquiryForm" method="POST" action="" onsubmit="return validateInquiryForm(event)">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="is_ajax" value="0">

                <div class="d-flex align-items-center justify-content-between mb-3 px-1">
                    <h3 class="h6 fw-bold text-uppercase text-secondary mb-0" style="letter-spacing: 0.5px;">
                        <i class="bi bi-card-checklist text-primary me-1.5"></i>Requested Materials & Specifications (<?= count($items) ?> Items)
                    </h3>
                    <?php if ($isLocked): ?>
                        <span class="badge bg-light text-secondary border px-2 py-1"><i class="bi bi-lock-fill me-1 text-muted"></i>Read-Only View</span>
                    <?php else: ?>
                        <small class="text-muted">Tap stock status for each</small>
                    <?php endif; ?>
                </div>

                <!-- Item Cards -->
                <div class="d-flex flex-column gap-3 mb-4">
                    <?php foreach ($items as $idx => $item): 
                        $curStatus = $item['availability_status'] ?? 'Pending';
                        $hasDecision = in_array($curStatus, ['Available', 'Partial', 'Unavailable']);
                        $curQty = ($item['available_qty'] !== null) ? (int)$item['available_qty'] : (int)$item['requested_qty'];
                        $curPrice = ($item['offered_price'] !== null) ? number_format((float)$item['offered_price'], 2, '.', '') : ($item['estimated_price'] > 0 ? number_format((float)$item['estimated_price'], 2, '.', '') : '');
                    ?>
                        <div class="item-card p-3.5 p-md-4" id="itemCard_<?= $item['id'] ?>">
                            <input type="hidden" name="item_ids[]" value="<?= $item['id'] ?>">

                            <!-- Item Header: Bold List Item Style (No Pill / No Item Code) -->
                            <div class="mb-3 pb-2.5 border-bottom border-light">
                                <div class="d-flex align-items-baseline justify-content-between flex-wrap gap-2">
                                    <h4 class="fw-bold text-dark mb-1" style="font-size: 1.15rem; letter-spacing: -0.2px;">
                                        <?= ($idx + 1) ?>. <?= htmlspecialchars($item['item_name']) ?>
                                    </h4>
                                    <div class="text-secondary" style="font-size: 0.95rem;">
                                        Requested: <strong class="text-dark fw-bold"><?= number_format((int)$item['requested_qty']) ?> <?= htmlspecialchars($item['unit'] ?: 'pcs') ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Segmented Buttons (Horizontal Button Group) -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1.5" style="font-size: 0.72rem;">
                                    Stock Availability Status <span class="text-danger">*</span>
                                </label>
                                <div class="btn-group w-100 shadow-sm" role="group" aria-label="Stock Availability">
                                    <input type="radio" class="btn-check status-radio" name="availability_status[<?= $idx ?>]" 
                                        id="avail_full_<?= $item['id'] ?>" value="Available" <?= $curStatus === 'Available' ? 'checked' : '' ?>
                                        <?= $isLocked ? 'disabled' : '' ?>
                                        data-item-id="<?= $item['id'] ?>" data-requested="<?= (int)$item['requested_qty'] ?>"
                                        onchange="onStatusRadioChange(this)">
                                    <label class="btn btn-outline-success status-pill-btn py-2 <?= $isLocked && $curStatus !== 'Available' ? 'opacity-50' : '' ?>" 
                                        style="<?= $isLocked ? 'pointer-events: none;' : '' ?>"
                                        for="avail_full_<?= $item['id'] ?>">
                                        <i class="bi bi-check-circle-fill me-1"></i> Available (Full)
                                    </label>

                                    <input type="radio" class="btn-check status-radio" name="availability_status[<?= $idx ?>]" 
                                        id="avail_part_<?= $item['id'] ?>" value="Partial" <?= $curStatus === 'Partial' ? 'checked' : '' ?>
                                        <?= $isLocked ? 'disabled' : '' ?>
                                        data-item-id="<?= $item['id'] ?>" data-requested="<?= (int)$item['requested_qty'] ?>"
                                        onchange="onStatusRadioChange(this)">
                                    <label class="btn btn-outline-warning status-pill-btn py-2 <?= $isLocked && $curStatus !== 'Partial' ? 'opacity-50' : '' ?>" 
                                        style="<?= $isLocked ? 'pointer-events: none;' : '' ?>"
                                        for="avail_part_<?= $item['id'] ?>">
                                        <i class="bi bi-pie-chart-fill me-1"></i> Partial Stock
                                    </label>

                                    <input type="radio" class="btn-check status-radio" name="availability_status[<?= $idx ?>]" 
                                        id="avail_out_<?= $item['id'] ?>" value="Unavailable" <?= $curStatus === 'Unavailable' ? 'checked' : '' ?>
                                        <?= $isLocked ? 'disabled' : '' ?>
                                        data-item-id="<?= $item['id'] ?>" data-requested="<?= (int)$item['requested_qty'] ?>"
                                        onchange="onStatusRadioChange(this)">
                                    <label class="btn btn-outline-danger status-pill-btn py-2 <?= $isLocked && $curStatus !== 'Unavailable' ? 'opacity-50' : '' ?>" 
                                        style="<?= $isLocked ? 'pointer-events: none;' : '' ?>"
                                        for="avail_out_<?= $item['id'] ?>">
                                        <i class="bi bi-x-circle-fill me-1"></i> Out of Stock
                                    </label>
                                </div>
                            </div>

                            <!-- Guidance Notice when no decision is made yet -->
                            <div id="decisionPrompt_<?= $item['id'] ?>" class="p-2.5 text-center text-muted small bg-light rounded-3 border border-dashed mb-2" style="<?= $hasDecision ? 'display: none;' : '' ?>">
                                <i class="bi bi-hand-index me-1 text-primary"></i> Please choose stock availability above to enter quantity and price
                            </div>

                            <!-- Dynamic Inputs (Quantity & Unit Price) -->
                            <div class="row g-2" id="detailsRow_<?= $item['id'] ?>" style="<?= !$hasDecision || $curStatus === 'Unavailable' ? 'display:none;' : '' ?>">
                                <div class="col-12 col-sm-6" id="qtyCol_<?= $item['id'] ?>">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                                        Confirmed Available Qty (<?= htmlspecialchars($item['unit'] ?: 'pcs') ?>)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-box-seam"></i></span>
                                        <input type="number" name="available_qty[]" id="qtyInput_<?= $item['id'] ?>" 
                                            class="form-control fw-bold text-center" 
                                            value="<?= $hasDecision ? $curQty : '' ?>" min="0" max="<?= (int)$item['requested_qty'] * 2 ?>"
                                            inputmode="numeric" <?= ($isLocked || $curStatus === 'Available') ? 'readonly disabled' : '' ?>>
                                        <span class="input-group-text bg-light text-muted small"><?= htmlspecialchars($item['unit'] ?: 'pcs') ?></span>
                                    </div>
                                    <small class="text-muted d-block mt-0.5" id="qtyHelp_<?= $item['id'] ?>" style="font-size: 0.70rem;">
                                        <?= $curStatus === 'Available' ? 'Auto-filled to requested amount' : 'Enter amount you can supply' ?>
                                    </small>
                                </div>

                                <div class="col-12 col-sm-6" id="priceCol_<?= $item['id'] ?>">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                                        Confirmed Unit Price (₱)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-primary fw-bold">₱</span>
                                        <input type="number" step="0.01" name="offered_price[]" id="priceInput_<?= $item['id'] ?>"
                                            class="form-control fw-bold text-primary" 
                                            placeholder="0.00" value="<?= htmlspecialchars($curPrice) ?>" min="0" inputmode="decimal"
                                            <?= $isLocked ? 'readonly disabled' : '' ?>>
                                    </div>
                                    <small class="text-muted d-block mt-0.5" style="font-size: 0.70rem;">
                                        Net unit price for this order
                                    </small>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1" style="font-size: 0.72rem;">
                                        Item Remarks / Brand / Lead Time <span class="text-muted fw-normal">(Optional)</span>
                                    </label>
                                    <input type="text" name="item_remarks[]" class="form-control form-control-sm" 
                                        placeholder="e.g. Brand: Holcim, Ready in 2 days, etc." 
                                        value="<?= htmlspecialchars($item['item_remarks'] ?? '') ?>" maxlength="255"
                                        <?= $isLocked ? 'readonly disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Supplier General Notes Card -->
                <div class="card border-0 shadow-sm rounded-4 p-3.5 p-md-4 mb-4 bg-white">
                    <label class="form-label fw-bold text-dark mb-1">
                        <i class="bi bi-chat-left-text me-1 text-primary"></i> General Supplier Remarks & Delivery Notes
                    </label>
                    <small class="text-muted d-block mb-2">
                        Specify any preferred payment terms, minimum order quantities, delivery schedule notes, or alternative contact numbers.
                    </small>
                    <textarea name="supplier_notes" class="form-control" rows="3" 
                        placeholder="Write any additional remarks for GB Construction Purchasing here..."
                        <?= $isLocked ? 'readonly disabled' : '' ?>><?= htmlspecialchars($inquiry['supplier_notes'] ?? '') ?></textarea>
                </div>

                <!-- Submit Action Footer or Locked Read-Only Notice -->
                <?php if ($isLocked): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white text-center">
                        <div class="rounded-circle bg-success-subtle text-success mx-auto mb-2.5 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="bi bi-shield-check fs-3"></i>
                        </div>
                        <h4 class="h6 fw-bold text-dark mb-1">Inquiry Response Finalized & Locked</h4>
                        <p class="text-muted small mb-0">
                            Thank you! Your stock availability and pricing confirmation has been recorded.
                            <?php if (!empty($inquiry['responded_at'])): ?>
                                <br><span class="text-secondary">Submitted on: <strong><?= date('F d, Y \a\t h:i A', strtotime($inquiry['responded_at'])) ?></strong></span>
                            <?php endif; ?>
                        </p>
                        <div class="mt-2.5">
                            <span class="badge bg-light text-secondary border px-3 py-2 fw-normal" style="font-size: 0.78rem;">
                                <i class="bi bi-lock-fill text-muted me-1"></i> Editing is disabled. For revisions or clarifications, please reach out to GB Construction purchasing.
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-lg rounded-4 p-3 bg-white text-center">
                        <button type="submit" id="btnSubmitInquiry" class="btn btn-primary touch-btn w-100 shadow-sm">
                            <i class="bi bi-send-check-fill me-1.5"></i> Submit Availability & Price Confirmation
                        </button>
                        <small class="text-muted d-block mt-2" style="font-size: 0.75rem;">
                            <i class="bi bi-lock-fill me-1 text-success"></i>Direct transmission to GB Construction & Enterprise Inc. procurement team.
                        </small>
                    </div>
                <?php endif; ?>
            </form>

        <?php endif; ?>

        <!-- Footer -->
        <footer class="mt-5 text-center text-muted small pb-4">
            <div>GB Construction & Enterprise Inc. &copy; <?= date('Y') ?> | Materials Management & Inventory System</div>
            <div class="mt-1" style="font-size: 0.72rem;">Automated Procurement & Vendor Link Verification</div>
        </footer>
    </main>

    <!-- Interactive Client-side Script -->
    <script>
        function onStatusRadioChange(radio) {
            <?php if ($isLocked): ?>
            return;
            <?php endif; ?>
            const itemId = radio.getAttribute('data-item-id');
            const reqQty = parseInt(radio.getAttribute('data-requested') || 1);
            const status = radio.value;

            // Clear error border on user selection
            const itemCard = document.getElementById('itemCard_' + itemId);
            if (itemCard) {
                itemCard.style.border = '';
                itemCard.style.boxShadow = '';
            }

            const promptBox = document.getElementById('decisionPrompt_' + itemId);
            const detailsRow = document.getElementById('detailsRow_' + itemId);
            const qtyCol = document.getElementById('qtyCol_' + itemId);
            const priceCol = document.getElementById('priceCol_' + itemId);
            const qtyInput = document.getElementById('qtyInput_' + itemId);
            const qtyHelp = document.getElementById('qtyHelp_' + itemId);
            const priceInput = document.getElementById('priceInput_' + itemId);

            if (promptBox) promptBox.style.display = 'none';

            if (status === 'Available') {
                if (detailsRow) detailsRow.style.display = '';
                if (qtyCol) qtyCol.style.display = '';
                if (priceCol) priceCol.style.display = '';
                if (qtyInput) {
                    qtyInput.value = reqQty;
                    qtyInput.readOnly = true;
                }
                if (qtyHelp) qtyHelp.innerText = 'Full requested quantity confirmed';
                if (priceInput && !priceInput.value) {
                    priceInput.focus();
                }
            } else if (status === 'Partial') {
                if (detailsRow) detailsRow.style.display = '';
                if (qtyCol) qtyCol.style.display = '';
                if (priceCol) priceCol.style.display = '';
                if (qtyInput) {
                    qtyInput.readOnly = false;
                    if (parseInt(qtyInput.value) >= reqQty || !qtyInput.value || parseInt(qtyInput.value) <= 0) {
                        qtyInput.value = Math.max(1, Math.floor(reqQty / 2));
                    }
                    qtyInput.focus();
                    qtyInput.select();
                }
                if (qtyHelp) qtyHelp.innerText = 'Enter confirmed amount you have in stock';
            } else if (status === 'Unavailable') {
                if (detailsRow) detailsRow.style.display = '';
                if (qtyCol) qtyCol.style.display = 'none';
                if (priceCol) priceCol.style.display = 'none';
                if (qtyInput) {
                    qtyInput.value = 0;
                    qtyInput.readOnly = true;
                }
                if (priceInput) {
                    priceInput.value = '';
                }
            }
        }

        function validateInquiryForm(e) {
            const form = document.getElementById('supplierInquiryForm');
            const itemCards = form.querySelectorAll('.item-card');
            
            for (let i = 0; i < itemCards.length; i++) {
                const card = itemCards[i];
                const checkedRadio = card.querySelector('input.status-radio:checked');
                if (!checkedRadio) {
                    if (e && e.preventDefault) e.preventDefault();
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.style.border = '2px solid #ef4444';
                    card.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.15)';
                    alert('Please select an availability decision for all items before submitting.');
                    return false;
                }
            }

            const btn = document.getElementById('btnSubmitInquiry');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting Response...';
            }
            return true;
        }
    </script>
</body>
</html>
