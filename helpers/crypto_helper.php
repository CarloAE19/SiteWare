<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Unauthorized copying, distribution, modification, or deployment of this file,
 * via any medium, is strictly prohibited and constitutes intellectual property theft.
 * See LICENSE file in root directory for full legal terms and conditions.
 */

// ==========================================================
// CIMS (GB INVENTORY) - CRYPTOGRAPHIC PKI HELPER MODULE
// Implements Asymmetric RSA-2048 + SHA-256 Document Signing & Integrity Verification
// ==========================================================

if (!defined('OPENSSL_KEY_BITS')) {
    define('OPENSSL_KEY_BITS', 2048);
}

/**
 * Returns the path to openssl.cnf if needed on Windows/XAMPP environments.
 */
function getOpenSslConfig()
{
    $candidates = [
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/apache/bin/openssl.cnf',
        'C:/xampp/php/extras/openssl/openssl.cnf',
        '/etc/ssl/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf'
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * Generates an RSA-2048 key pair.
 * 
 * @return array ['public' => string, 'private' => string]|null
 */
function generateCryptoKeyPair()
{
    $config = [
        "digest_alg" => "sha256",
        "private_key_bits" => OPENSSL_KEY_BITS,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ];

    $cnfPath = getOpenSslConfig();
    if ($cnfPath) {
        $config["config"] = $cnfPath;
    }

    $res = openssl_pkey_new($config);
    if (!$res) {
        return null;
    }

    // Export Private Key
    $privateKey = '';
    if (!openssl_pkey_export($res, $privateKey, null, $config)) {
        return null;
    }

    // Export Public Key
    $details = openssl_pkey_get_details($res);
    $publicKey = $details['key'] ?? '';

    if (empty($privateKey) || empty($publicKey)) {
        return null;
    }

    return [
        'private' => $privateKey,
        'public' => $publicKey
    ];
}

/**
 * Retrieves existing user keypair or generates and saves a new one automatically.
 * 
 * @param PDO $pdo
 * @param int $userId
 * @return array ['public' => string, 'private' => string]|null
 */
function getOrCreateUserKeyPair($pdo, $userId)
{
    if (!$userId || !is_numeric($userId))
        return null;

    try {
        $stmt = $pdo->prepare("SELECT public_key, private_key FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['public_key']) && !empty($row['private_key'])) {
            return [
                'public' => $row['public_key'],
                'private' => $row['private_key']
            ];
        }

        // Generate new keypair
        $keys = generateCryptoKeyPair();
        if ($keys) {
            $upd = $pdo->prepare("UPDATE users SET public_key = ?, private_key = ? WHERE id = ?");
            $upd->execute([$keys['public'], $keys['private'], $userId]);
            return $keys;
        }
    } catch (Exception $e) {
        error_log("Crypto KeyPair Error: " . $e->getMessage());
    }

    return null;
}

/**
 * Builds a deterministic canonical JSON payload for a Purchase Order.
 */
function buildCanonicalPoPayload($po, $items)
{
    $normalizedItems = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            $name = '';
            if (!empty($item['custom_item_name'])) {
                $name = (string) $item['custom_item_name'];
            } elseif (!empty($item['item_name'])) {
                $name = (string) $item['item_name'];
            } elseif (!empty($item['new_item_name'])) {
                $name = (string) $item['new_item_name'];
            }

            $normalizedItems[] = [
                'code' => (string) ($item['item_code'] ?? ''),
                'name' => $name,
                'qty' => (float) ($item['quantity'] ?? 0),
                'price' => (float) ($item['unit_price'] ?? 0)
            ];
        }
    }
    // Sort items deterministically by item_code
    usort($normalizedItems, function ($a, $b) {
        return strcmp($a['code'], $b['code']);
    });

    $payload = [
        'doc_type' => 'PURCHASE_ORDER',
        'po_no' => (string) ($po['po_no'] ?? ''),
        'rs_no' => (string) ($po['rs_no'] ?? ''),
        'supplier' => (string) (!empty($po['company_name']) ? $po['company_name'] : ($po['supplier_id'] ?? '')),
        'prepared_by' => (int) ($po['prepared_by'] ?? 0),
        'approved_by' => (int) ($po['approved_by'] ?? 0),
        'created_at' => (string) ($po['created_at'] ?? ''),
        'items' => $normalizedItems
    ];

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Builds a deterministic canonical JSON payload for a Material Withdrawal.
 */
function buildCanonicalWdPayload($wd, $items)
{
    $normalizedItems = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            $normalizedItems[] = [
                'code' => (string) ($item['item_code'] ?? ''),
                'name' => (string) ($item['item_name'] ?? ''),
                'qty' => (float) ($item['quantity'] ?? 0),
                'unit' => (string) ($item['unit'] ?? '')
            ];
        }
    }
    // Sort items deterministically by item_code
    usort($normalizedItems, function ($a, $b) {
        return strcmp($a['code'], $b['code']);
    });

    $payload = [
        'doc_type' => 'MATERIAL_WITHDRAWAL',
        'withdrawal_no' => (string) ($wd['withdrawal_no'] ?? ''),
        'project_name' => (string) ($wd['project_name'] ?? ''),
        'released_by' => (int) ($wd['released_by'] ?? 0),
        'received_by' => (string) ($wd['received_by'] ?? ''),
        'date_withdrawn' => (string) ($wd['date_withdrawn'] ?? ''),
        'items' => $normalizedItems
    ];

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Cryptographically signs a payload with an RSA private key using SHA-256.
 * 
 * @param string $payload
 * @param string $privateKeyPem
 * @return array ['hash' => string, 'signature' => string]|null
 */
function cryptographicallySignPayload($payload, $privateKeyPem)
{
    if (empty($payload) || empty($privateKeyPem))
        return null;

    $documentHash = hash('sha256', $payload);
    $binarySignature = '';

    $success = openssl_sign($payload, $binarySignature, $privateKeyPem, OPENSSL_ALGO_SHA256);
    if (!$success || empty($binarySignature)) {
        return null;
    }

    return [
        'hash' => $documentHash,
        'signature' => base64_encode($binarySignature)
    ];
}

/**
 * Cryptographically verifies a signature against a payload using an RSA public key.
 * 
 * @param string $payload
 * @param string $signatureBase64
 * @param string $publicKeyPem
 * @return bool
 */
function cryptographicallyVerifyPayload($payload, $signatureBase64, $publicKeyPem)
{
    if (empty($payload) || empty($signatureBase64) || empty($publicKeyPem))
        return false;

    $binarySignature = base64_decode($signatureBase64);
    if ($binarySignature === false)
        return false;

    $result = openssl_verify($payload, $binarySignature, $publicKeyPem, OPENSSL_ALGO_SHA256);
    return ($result === 1);
}

/**
 * Retrieves full Purchase Order document header and items deterministically formatted for crypto signing/verification.
 * 
 * @param PDO $pdo
 * @param string|int $poIdOrNo
 * @return array ['document' => array, 'items' => array]|null
 */
function getPurchaseOrderDetailsForCrypto($pdo, $poIdOrNo)
{
    if (!$pdo || empty($poIdOrNo)) return null;

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
    $stmt->execute([$poIdOrNo, $poIdOrNo]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) return null;

    $itemStmt = $pdo->prepare("
        SELECT pi.*, i.item_name 
        FROM po_items pi 
        LEFT JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $itemStmt->execute([$document['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'document' => $document,
        'items' => $items
    ];
}

/**
 * Retrieves full Material Withdrawal document header and items deterministically formatted for crypto signing/verification.
 * 
 * @param PDO $pdo
 * @param string|int $wdIdOrNo
 * @return array ['document' => array, 'items' => array]|null
 */
function getWithdrawalDetailsForCrypto($pdo, $wdIdOrNo)
{
    if (!$pdo || empty($wdIdOrNo)) return null;

    $stmt = $pdo->prepare("
        SELECT w.*, u.name AS releaser_name, u.role AS releaser_role, u.public_key AS releaser_public_key, u.signature_path AS releaser_sig
        FROM withdrawals w
        LEFT JOIN users u ON w.released_by = u.id
        WHERE w.withdrawal_no = ? OR w.id = ?
    ");
    $stmt->execute([$wdIdOrNo, $wdIdOrNo]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) return null;

    $itemStmt = $pdo->prepare("
        SELECT wi.*, COALESCE(i.item_name, '') AS item_name, COALESCE(i.unit, '') AS unit 
        FROM withdrawal_items wi 
        LEFT JOIN inventory i ON wi.item_code = i.item_code 
        WHERE wi.withdrawal_id = ?
    ");
    $itemStmt->execute([$document['id']]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'document' => $document,
        'items' => $items
    ];
}

/**
 * Cryptographically seals a Purchase Order with RSA-2048 PKI Signature and SHA-256 hash.
 * 
 * @param PDO $pdo
 * @param string|int $poIdOrNo
 * @return array|null
 */
function signPurchaseOrder($pdo, $poIdOrNo)
{
    $data = getPurchaseOrderDetailsForCrypto($pdo, $poIdOrNo);
    if (!$data) return null;

    $document = $data['document'];
    $items = $data['items'];

    // Determine signer: approved_by takes precedence over prepared_by
    $signerUserId = $document['approved_by'] ?: $document['prepared_by'];
    if (!$signerUserId) {
        // Fallback to prepared_by if approved_by is 0/null
        $signerUserId = $document['prepared_by'] ?: 1;
    }

    $keys = getOrCreateUserKeyPair($pdo, $signerUserId);
    if (!$keys || empty($keys['private'])) return null;

    $canonicalPayload = buildCanonicalPoPayload($document, $items);
    $signed = cryptographicallySignPayload($canonicalPayload, $keys['private']);
    if ($signed) {
        $upd = $pdo->prepare("UPDATE purchase_orders SET crypto_signature = ?, document_hash = ?, signed_at = NOW() WHERE id = ?");
        $upd->execute([$signed['signature'], $signed['hash'], $document['id']]);

        return [
            'signature' => $signed['signature'],
            'hash' => $signed['hash'],
            'public_key' => $keys['public'],
            'canonical_payload' => $canonicalPayload
        ];
    }

    return null;
}

/**
 * Cryptographically seals a Material Withdrawal with RSA-2048 PKI Signature and SHA-256 hash.
 * 
 * @param PDO $pdo
 * @param string|int $wdIdOrNo
 * @return array|null
 */
function signWithdrawal($pdo, $wdIdOrNo)
{
    $data = getWithdrawalDetailsForCrypto($pdo, $wdIdOrNo);
    if (!$data) return null;

    $document = $data['document'];
    $items = $data['items'];

    $signerUserId = $document['released_by'] ?: 1;
    $keys = getOrCreateUserKeyPair($pdo, $signerUserId);
    if (!$keys || empty($keys['private'])) return null;

    $canonicalPayload = buildCanonicalWdPayload($document, $items);
    $signed = cryptographicallySignPayload($canonicalPayload, $keys['private']);
    if ($signed) {
        $upd = $pdo->prepare("UPDATE withdrawals SET crypto_signature = ?, document_hash = ?, signed_at = NOW() WHERE id = ?");
        $upd->execute([$signed['signature'], $signed['hash'], $document['id']]);

        return [
            'signature' => $signed['signature'],
            'hash' => $signed['hash'],
            'public_key' => $keys['public'],
            'canonical_payload' => $canonicalPayload
        ];
    }

    return null;
}

/**
 * Re-seals all existing Purchase Orders and Material Withdrawals to ensure 100% cryptographic integrity.
 * 
 * @param PDO $pdo
 * @return array ['pos' => int, 'withdrawals' => int]
 */
function reSealAllExistingDocuments($pdo)
{
    $poCount = 0;
    $wdCount = 0;

    // Reseal POs
    $poStmt = $pdo->query("SELECT id FROM purchase_orders ORDER BY id ASC");
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pos as $po) {
        if (signPurchaseOrder($pdo, $po['id'])) {
            $poCount++;
        }
    }

    // Reseal Withdrawals
    $wdStmt = $pdo->query("SELECT id FROM withdrawals ORDER BY id ASC");
    $wds = $wdStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wds as $wd) {
        if (signWithdrawal($pdo, $wd['id'])) {
            $wdCount++;
        }
    }

    return [
        'pos' => $poCount,
        'withdrawals' => $wdCount
    ];
}

