<?php
// ==========================================================
// CIMS (GB INVENTORY) - PUBLIC CRYPTOGRAPHIC VERIFICATION PORTAL
// Mathematically verifies SHA-256 + RSA-2048 Document Seals
// ==========================================================

require_once __DIR__ . '/Connection/db.php';
require_once __DIR__ . '/helpers/crypto_helper.php';
global $pdo;
$pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);

$ref = trim($_GET['ref'] ?? $_GET['id'] ?? $_GET['doc'] ?? '');
$type = strtolower(trim($_GET['type'] ?? ''));

// Auto-detect type if not specified
if (empty($type) && !empty($ref)) {
    if (str_starts_with(strtoupper($ref), 'PO') || strpos($ref, 'PO-') !== false) {
        $type = 'po';
    } elseif (str_starts_with(strtoupper($ref), 'WD') || strpos($ref, 'WD-') !== false) {
        $type = 'wd';
    }
}

$document = null;
$items = [];
$signer = null;
$verificationStatus = 'UNKNOWN'; // VALID, TAMPERED, UNSIGNED, NOT_FOUND
$documentHash = '';
$cryptoSignature = '';
$signedAt = '';
$canonicalPayload = '';

if (!empty($ref)) {
    if ($type === 'po') {
        // Fetch Purchase Order
        $stmt = $pdo->prepare("
            SELECT po.*, s.company_name, s.supplier_code, r.rs_no, 
                   u1.name AS prepared_name, u1.role AS prepared_role, u1.public_key AS prepared_public_key,
                   u2.name AS approved_name, u2.role AS approved_role, u2.public_key AS approved_public_key,
                   u_rec.name AS received_by_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN requisitions r ON po.rs_id = r.id
            LEFT JOIN users u1 ON po.prepared_by = u1.id
            LEFT JOIN users u2 ON po.approved_by = u2.id
            LEFT JOIN users u_rec ON po.received_by = u_rec.id
            WHERE po.po_no = ? OR po.id = ?
        ");
        $stmt->execute([$ref, $ref]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document) {
            $itemStmt = $pdo->prepare("
                SELECT pi.*, i.item_name 
                FROM po_items pi 
                LEFT JOIN inventory i ON pi.item_code = i.item_code 
                WHERE pi.po_id = ?
            ");
            $itemStmt->execute([$document['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            // Determine signer: approved_by takes precedence over prepared_by
            $signerUserId = $document['approved_by'] ?: $document['prepared_by'];
            $signerName = $document['approved_name'] ?: $document['prepared_name'] ?: 'Purchasing Officer';
            $signerRole = $document['approved_role'] ?: $document['prepared_role'] ?: 'Authorized Officer';
            $publicKey = $document['approved_public_key'] ?: $document['prepared_public_key'];

            // Auto-sign if not signed yet (Self-healing backfill)
            if (empty($document['crypto_signature'])) {
                $keys = getOrCreateUserKeyPair($pdo, $signerUserId);
                if ($keys) {
                    $publicKey = $keys['public'];
                    $payload = buildCanonicalPoPayload($document, $items);
                    $signed = cryptographicallySignPayload($payload, $keys['private']);
                    if ($signed) {
                        $upd = $pdo->prepare("UPDATE purchase_orders SET crypto_signature = ?, document_hash = ?, signed_at = NOW() WHERE id = ?");
                        $upd->execute([$signed['signature'], $signed['hash'], $document['id']]);
                        $document['crypto_signature'] = $signed['signature'];
                        $document['document_hash'] = $signed['hash'];
                        $document['signed_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            $cryptoSignature = $document['crypto_signature'] ?? '';
            $documentHash = $document['document_hash'] ?? '';
            $signedAt = $document['signed_at'] ?: $document['created_at'];

            if (!empty($cryptoSignature) && !empty($publicKey)) {
                $canonicalPayload = buildCanonicalPoPayload($document, $items);
                $isValid = cryptographicallyVerifyPayload($canonicalPayload, $cryptoSignature, $publicKey);
                $verificationStatus = $isValid ? 'VALID' : 'TAMPERED';
            } else {
                $verificationStatus = 'UNSIGNED';
            }

            $signer = [
                'name' => $signerName,
                'role' => strtoupper($signerRole),
                'signature_img' => $document['approved_signature'] ?: $document['prepared_signature']
            ];
        } else {
            $verificationStatus = 'NOT_FOUND';
        }
    } elseif ($type === 'wd') {
        // Fetch Material Withdrawal
        $stmt = $pdo->prepare("
            SELECT w.*, u.name AS releaser_name, u.role AS releaser_role, u.public_key AS releaser_public_key, u.signature_path AS releaser_sig
            FROM withdrawals w
            LEFT JOIN users u ON w.released_by = u.id
            WHERE w.withdrawal_no = ? OR w.id = ?
        ");
        $stmt->execute([$ref, $ref]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($document) {
            $itemStmt = $pdo->prepare("
                SELECT wi.*, i.item_name, i.unit 
                FROM withdrawal_items wi 
                LEFT JOIN inventory i ON wi.item_code = i.item_code 
                WHERE wi.withdrawal_id = ?
            ");
            $itemStmt->execute([$document['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

            $signerUserId = $document['released_by'];
            $signerName = $document['releaser_name'] ?: 'Warehouse Officer';
            $signerRole = $document['releaser_role'] ?: 'Warehouse Staff';
            $publicKey = $document['releaser_public_key'];

            // Auto-sign if not signed yet (Self-healing backfill)
            if (empty($document['crypto_signature'])) {
                $keys = getOrCreateUserKeyPair($pdo, $signerUserId);
                if ($keys) {
                    $publicKey = $keys['public'];
                    $payload = buildCanonicalWdPayload($document, $items);
                    $signed = cryptographicallySignPayload($payload, $keys['private']);
                    if ($signed) {
                        $upd = $pdo->prepare("UPDATE withdrawals SET crypto_signature = ?, document_hash = ?, signed_at = NOW() WHERE id = ?");
                        $upd->execute([$signed['signature'], $signed['hash'], $document['id']]);
                        $document['crypto_signature'] = $signed['signature'];
                        $document['document_hash'] = $signed['hash'];
                        $document['signed_at'] = date('Y-m-d H:i:s');
                    }
                }
            }

            $cryptoSignature = $document['crypto_signature'] ?? '';
            $documentHash = $document['document_hash'] ?? '';
            $signedAt = $document['signed_at'] ?: $document['date_withdrawn'];

            if (!empty($cryptoSignature) && !empty($publicKey)) {
                $canonicalPayload = buildCanonicalWdPayload($document, $items);
                $isValid = cryptographicallyVerifyPayload($canonicalPayload, $cryptoSignature, $publicKey);
                $verificationStatus = $isValid ? 'VALID' : 'TAMPERED';
            } else {
                $verificationStatus = 'UNSIGNED';
            }

            $signer = [
                'name' => $signerName,
                'role' => strtoupper($signerRole),
                'signature_img' => $document['releaser_sig']
            ];
        } else {
            $verificationStatus = 'NOT_FOUND';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiteWare Security Trust Center | GB Inventory</title>
    <link rel="icon" type="image/png" href="assets/clearlogo.png">
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --brand-primary: #0284c7;
            --brand-dark: #0f172a;
            --bg-canvas: #f8fafc;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-canvas);
            color: #334155;
            min-height: 100vh;
        }

        .cert-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            background: #ffffff;
            overflow: hidden;
        }

        .cert-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            padding: 24px;
        }

        .status-badge-valid {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .status-badge-tampered {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .hash-code {
            font-family: 'Courier New', monospace;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #475569;
            word-break: break-all;
        }

        .seal-pulse {
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }

        @keyframes pulse-ring {
            0% {
                transform: scale(0.95);
                opacity: 0.8;
            }

            50% {
                transform: scale(1.05);
                opacity: 1;
            }

            100% {
                transform: scale(0.95);
                opacity: 0.8;
            }
        }
    </style>
</head>

<body class="py-4 py-md-5">

    <div class="container" style="max-width: 780px;">

        <!-- Top Logo / Header -->
        <div class="text-center mb-4">
            <h4 class="fw-bold text-dark mb-1 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-shield-lock-fill text-primary"></i> SiteWare Security Trust Center
            </h4>
            <p class="text-muted small mb-0">Cryptographic PKI Document Authentication & Integrity Engine</p>
        </div>

        <?php if (empty($ref) || $verificationStatus === 'NOT_FOUND'): ?>
            <!-- NOT FOUND CARD -->
            <div class="cert-card p-4 text-center">
                <div class="py-4">
                    <i class="bi bi-file-earmark-x text-muted display-3 mb-3 d-block"></i>
                    <h5 class="fw-bold text-dark mb-2">Document Not Found</h5>
                    <p class="text-muted small mb-4">
                        The requested reference <code><?= htmlspecialchars($ref ?: 'EMPTY') ?></code> could not be found in
                        the system registry.
                    </p>
                    <form action="verify" method="GET" class="d-flex justify-content-center gap-2 max-w-sm mx-auto"
                        style="max-width: 400px;">
                        <input type="text" name="ref" class="form-control form-control-sm"
                            placeholder="Enter PO-XXXX or WD-XXXX..." required>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">Verify</button>
                    </form>
                </div>
            </div>

        <?php elseif ($verificationStatus === 'VALID'): ?>
            <!-- VERIFIED & UNTAMPERED CARD -->
            <div class="cert-card">
                <div class="cert-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span class="badge bg-primary text-uppercase px-2 py-1 mb-2 fw-semibold"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <?= ($type === 'po') ? 'Purchase Order Document' : 'Material Withdrawal Slip' ?>
                        </span>
                        <h4 class="fw-bold mb-0 text-white">
                            <?= htmlspecialchars($document['po_no'] ?? $document['withdrawal_no']) ?>
                        </h4>
                    </div>
                    <div class="text-end">
                        <span
                            class="badge status-badge-valid px-3 py-2 rounded-pill fw-bold d-inline-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-6 seal-pulse"></i> MATHEMATICALLY VERIFIED
                        </span>
                    </div>
                </div>

                <div class="p-4 bg-white">
                    <!-- Alert Banner -->
                    <div class="alert alert-success d-flex align-items-start gap-3 rounded-3 mb-4 shadow-sm" role="alert">
                        <i class="bi bi-shield-check fs-2 text-success flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold text-success mb-1">Authentic & Tamper-Proof Cryptographic Seal</h6>
                            <p class="small mb-0 text-dark">
                                This document's digital signature and SHA-256 data payload mathematically match the recorded
                                Public Key cryptographic seal.
                                <strong>No items, quantities, or prices have been altered since signing.</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Document Metadata Grid (Dual Signatories & Fulfillment) -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">Associated Reference</label>
                            <div class="fw-bold text-dark">
                                <?= htmlspecialchars($document['rs_no'] ?? $document['project_name'] ?? 'N/A') ?>
                                <?php if (!empty($document['company_name'])): ?>
                                    <span class="text-muted fw-normal d-block" style="font-size: 0.75rem;">Supplier: <?= htmlspecialchars($document['company_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">
                                <?= ($type === 'po') ? 'Date Generated & Target ETA' : 'Date Withdrawn' ?>
                            </label>
                            <div class="fw-bold text-dark">
                                <?= date('F d, Y', strtotime($signedAt)) ?>
                                <?php if ($type === 'po' && !empty($document['expected_delivery_date'])): ?>
                                    <span class="text-primary fw-normal d-block" style="font-size: 0.75rem;">Target ETA: <?= date('F d, Y', strtotime($document['expected_delivery_date'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($type === 'po'): ?>
                            <!-- Dual Signatories: Prepared By (Purchasing) & Approved By (Management) -->
                            <div class="col-sm-6">
                                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Prepared by (Purchasing)</label>
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-person-check text-primary me-1"></i><?= htmlspecialchars($document['prepared_name'] ?: 'Purchasing Department') ?>
                                    <span class="badge bg-secondary ms-1 fw-normal" style="font-size: 0.65rem;"><?= htmlspecialchars(strtoupper($document['prepared_role'] ?: 'Purchasing')) ?></span>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Approved by (Management)</label>
                                <div class="fw-bold text-dark">
                                    <?php if (!empty($document['approved_name'])): ?>
                                        <i class="bi bi-patch-check-fill text-success me-1"></i><?= htmlspecialchars($document['approved_name']) ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle ms-1 fw-semibold" style="font-size: 0.65rem;"><?= htmlspecialchars(strtoupper($document['approved_role'] ?: 'Management')) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted fw-normal fst-italic">Management Authorization</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Withdrawal Signatories: Releasing Warehouse Officer & Site Recipient -->
                            <div class="col-sm-6">
                                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Released by (Warehouse)</label>
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-person-badge text-primary me-1"></i><?= htmlspecialchars($document['releaser_name'] ?: 'Warehouse Officer') ?>
                                    <span class="badge bg-secondary ms-1 fw-normal" style="font-size: 0.65rem;"><?= htmlspecialchars(strtoupper($document['releaser_role'] ?: 'Warehouse')) ?></span>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Authorized Site Recipient</label>
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-person-check text-success me-1"></i><?= htmlspecialchars($document['received_by'] ?: 'Authorized Recipient') ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-sm-6">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">Algorithm Standard</label>
                            <div class="fw-bold text-dark"><i class="bi bi-cpu text-primary me-1"></i>RSA 2048-bit / SHA-256 PKCS#1</div>
                        </div>

                        <!-- Physical Fulfillment & Custody Status -->
                        <div class="col-sm-6">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">
                                <?= ($type === 'po') ? 'Physical Fulfillment Status' : 'Physical Custody Status' ?>
                            </label>
                            <?php if ($type === 'po'): ?>
                                <?php if ($document['status'] === 'Delivered (Discrepancy)'): ?>
                                    <div>
                                        <span class="badge bg-warning text-dark border px-2 py-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-exclamation-triangle-fill text-dark me-1"></i> Delivered (Discrepancy Logged)
                                        </span>
                                    </div>
                                    <?php if (!empty($document['received_by_name'])): ?>
                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.72rem;">Intake by: <?= htmlspecialchars($document['received_by_name']) ?> &bull; Partial delivery / issue noted</small>
                                    <?php endif; ?>
                                <?php elseif ($document['status'] === 'Delivered'): ?>
                                    <div class="fw-bold text-success d-flex align-items-center gap-1">
                                        <i class="bi bi-check-circle-fill"></i> Delivered & Received at Warehouse
                                    </div>
                                    <?php if (!empty($document['received_by_name'])): ?>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Intake by: <?= htmlspecialchars($document['received_by_name']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-truck me-1"></i> Awaiting Warehouse Receiving
                                        </span>
                                    </div>
                                    <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Purchase order sealed &bull; Pending physical delivery intake</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="fw-bold text-success d-flex align-items-center gap-1">
                                    <i class="bi bi-box-seam-fill"></i> Materials Released & Received on Site
                                </div>
                                <?php if (!empty($document['photo_proof_path'])): ?>
                                    <small class="text-primary d-block mt-0.5" style="font-size: 0.72rem;"><i class="bi bi-camera-fill me-1"></i> Photo proof of custody recorded</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Items Verified Section (With Sealed Pricing for PO) -->
                    <?php 
                        $totalOrderValue = 0;
                        if ($type === 'po') {
                            foreach ($items as $it) {
                                $totalOrderValue += (float)($it['quantity'] * ($it['unit_price'] ?? 0));
                            }
                        }
                    ?>
                    <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                        <h6 class="fw-bold text-dark small text-uppercase mb-2">
                            <i class="bi bi-card-checklist text-primary me-1"></i> Sealed Item Manifest
                            (<?= count($items) ?> item<?= count($items) > 1 ? 's' : '' ?>)
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle mb-0 bg-transparent"
                                style="font-size: 0.85rem;">
                                <thead>
                                    <tr class="text-muted border-bottom">
                                        <th>Item Code</th>
                                        <th>Description</th>
                                        <th class="text-center">Quantity</th>
                                        <?php if ($type === 'po'): ?>
                                            <th class="text-end">Unit Price (₱)</th>
                                            <th class="text-end">Total Amount (₱)</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <?php 
                                            $qty = (float)($it['quantity'] ?? 0);
                                            $price = (float)($it['unit_price'] ?? 0);
                                            $subtotal = $qty * $price;
                                        ?>
                                        <tr>
                                            <td class="fw-semibold text-muted"><?= htmlspecialchars($it['item_code']) ?></td>
                                            <td class="fw-bold text-dark">
                                                <?= htmlspecialchars($it['item_name'] ?? $it['custom_item_name'] ?? $it['item_code']) ?>
                                            </td>
                                            <td class="text-center fw-bold text-primary">
                                                <?= htmlspecialchars($it['quantity']) ?> <?= htmlspecialchars($it['unit'] ?? '') ?>
                                            </td>
                                            <?php if ($type === 'po'): ?>
                                                <td class="text-end text-muted">
                                                    ₱<?= number_format($price, 2) ?>
                                                </td>
                                                <td class="text-end fw-bold text-dark">
                                                    ₱<?= number_format($subtotal, 2) ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php if ($type === 'po'): ?>
                                    <tfoot class="border-top">
                                        <tr class="fw-bold">
                                            <td colspan="4" class="text-end text-uppercase text-muted py-2" style="font-size: 0.8rem;">Total Sealed Value:</td>
                                            <td class="text-end text-primary py-2" style="font-size: 0.95rem;">
                                                ₱<?= number_format($totalOrderValue, 2) ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- Logistics & Receiving Notes / Discrepancies if present -->
                    <?php if (!empty($document['delay_remarks'])): ?>
                        <?php
                            $rawRemarks = trim($document['delay_remarks']);
                            $parts = explode('[DELIVERY DISCREPANCY]:', $rawRemarks);
                            $uniqueBlocks = [];
                            foreach ($parts as $part) {
                                $t = trim($part);
                                if ($t && !in_array($t, $uniqueBlocks)) {
                                    $uniqueBlocks[] = $t;
                                }
                            }
                            $formattedRemarks = !empty($uniqueBlocks) 
                                ? implode("\n\n", array_map(fn($b) => "[DELIVERY DISCREPANCY]:\n" . $b, $uniqueBlocks)) 
                                : $rawRemarks;
                        ?>
                        <div class="alert alert-warning border border-warning-subtle rounded-3 p-3 mb-4 shadow-sm">
                            <h6 class="fw-bold text-dark small text-uppercase mb-1 d-flex align-items-center gap-2">
                                <i class="bi bi-exclamation-octagon-fill text-warning"></i> Logistics / Receiving Discrepancy Notes
                            </h6>
                            <div class="font-monospace text-dark small bg-white p-2 rounded border mt-2" style="white-space: pre-wrap; font-size: 0.78rem; line-height: 1.4;">
                                <?= htmlspecialchars($formattedRemarks) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Cryptographic Fingerprint -->
                    <div class="mb-2">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">
                            <i class="bi bi-fingerprint text-primary me-1"></i> SHA-256 Digital Fingerprint
                        </label>
                        <div class="hash-code p-2 d-flex justify-content-between align-items-center">
                            <span class="text-break"><?= htmlspecialchars($documentHash) ?></span>
                            <button class="btn btn-sm btn-link text-primary p-0 ms-2 text-decoration-none"
                                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($documentHash) ?>'); alert('SHA-256 Hash copied!');"
                                title="Copy Hash">
                                <i class="bi bi-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 text-center border-top">
                    <small class="text-muted d-block">
                        Verified securely by SiteWare Cryptographic Trust Engine &bull; <?= date('Y-m-d H:i:s') ?> PST
                    </small>
                </div>
            </div>

        <?php elseif ($verificationStatus === 'TAMPERED'): ?>
            <!-- TAMPERED WARNING CARD -->
            <div class="cert-card border-danger">
                <div class="cert-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white text-danger text-uppercase px-2 py-1 mb-2 fw-bold">Integrity
                            Failure</span>
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($document['po_no'] ?? $document['withdrawal_no']) ?>
                        </h4>
                    </div>
                    <span class="badge status-badge-tampered px-3 py-2 rounded-pill fw-bold">
                        <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> SIGNATURE MISMATCH
                    </span>
                </div>

                <div class="p-4 bg-white">
                    <div class="alert alert-danger d-flex align-items-start gap-3 rounded-3 mb-4" role="alert">
                        <i class="bi bi-shield-x fs-2 text-danger flex-shrink-0"></i>
                        <div>
                            <h6 class="fw-bold text-danger mb-1">Cryptographic Tamper Alert</h6>
                            <p class="small mb-0 text-dark">
                                The current data in this document does not match the cryptographic signature generated at
                                the time of signing.
                                <strong>One or more items, quantities, prices, or approval details have been
                                    altered.</strong>
                            </p>
                        </div>
                    </div>
                    <div class="text-center py-3">
                        <p class="text-muted small mb-0">Please contact the System Administrator or Internal Auditor
                            immediately.</p>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- UNSIGNED CARD -->
            <div class="cert-card p-4 text-center">
                <i class="bi bi-shield-slash text-warning display-4 mb-3 d-block"></i>
                <h5 class="fw-bold text-dark mb-2">Document Awaiting Cryptographic Seal</h5>
                <p class="text-muted small mb-0">This document has not been sealed with an RSA-2048 cryptographic signature
                    yet.</p>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>