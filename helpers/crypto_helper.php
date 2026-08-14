<?php
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
function getOpenSslConfig() {
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
function generateCryptoKeyPair() {
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
function getOrCreateUserKeyPair($pdo, $userId) {
    if (!$userId || !is_numeric($userId)) return null;

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
function buildCanonicalPoPayload($po, $items) {
    $normalizedItems = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            $normalizedItems[] = [
                'code' => (string)($item['item_code'] ?? ''),
                'name' => (string)($item['item_name'] ?? $item['custom_item_name'] ?? ''),
                'qty'  => (float)($item['quantity'] ?? 0),
                'price'=> (float)($item['unit_price'] ?? 0)
            ];
        }
    }
    // Sort items deterministically by item_code
    usort($normalizedItems, function($a, $b) {
        return strcmp($a['code'], $b['code']);
    });

    $payload = [
        'doc_type'     => 'PURCHASE_ORDER',
        'po_no'        => (string)($po['po_no'] ?? ''),
        'rs_no'        => (string)($po['rs_no'] ?? ''),
        'supplier'     => (string)($po['company_name'] ?? $po['supplier_id'] ?? ''),
        'prepared_by'  => (int)($po['prepared_by'] ?? 0),
        'approved_by'  => (int)($po['approved_by'] ?? 0),
        'created_at'   => (string)($po['created_at'] ?? ''),
        'items'        => $normalizedItems
    ];

    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Builds a deterministic canonical JSON payload for a Material Withdrawal.
 */
function buildCanonicalWdPayload($wd, $items) {
    $normalizedItems = [];
    if (is_array($items)) {
        foreach ($items as $item) {
            $normalizedItems[] = [
                'code' => (string)($item['item_code'] ?? ''),
                'name' => (string)($item['item_name'] ?? ''),
                'qty'  => (float)($item['quantity'] ?? 0),
                'unit' => (string)($item['unit'] ?? '')
            ];
        }
    }
    // Sort items deterministically by item_code
    usort($normalizedItems, function($a, $b) {
        return strcmp($a['code'], $b['code']);
    });

    $payload = [
        'doc_type'       => 'MATERIAL_WITHDRAWAL',
        'withdrawal_no'  => (string)($wd['withdrawal_no'] ?? ''),
        'project_name'   => (string)($wd['project_name'] ?? ''),
        'released_by'    => (int)($wd['released_by'] ?? 0),
        'received_by'    => (string)($wd['received_by'] ?? ''),
        'date_withdrawn' => (string)($wd['date_withdrawn'] ?? ''),
        'items'          => $normalizedItems
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
function cryptographicallySignPayload($payload, $privateKeyPem) {
    if (empty($payload) || empty($privateKeyPem)) return null;

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
function cryptographicallyVerifyPayload($payload, $signatureBase64, $publicKeyPem) {
    if (empty($payload) || empty($signatureBase64) || empty($publicKeyPem)) return false;

    $binarySignature = base64_decode($signatureBase64);
    if ($binarySignature === false) return false;

    $result = openssl_verify($payload, $binarySignature, $publicKeyPem, OPENSSL_ALGO_SHA256);
    return ($result === 1);
}
