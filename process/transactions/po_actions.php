<?php
// ==========================================
// PURCHASE ORDERS (PO) ACTIONS
// ==========================================

// --- AJAX: FETCH SUPPLIER DELIVERY HISTORY ---
if ($action === 'fetch_supplier_delivery_history') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);

    // Fetch supplier info
    $supStmt = $pdo->prepare("SELECT company_name, supplier_code FROM suppliers WHERE id = ?");
    $supStmt->execute([$supplier_id]);
    $supplier = $supStmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        echo json_encode(['status' => 'error', 'message' => 'Supplier not found.']);
        exit;
    }

    // Fetch all POs for this supplier
    $poStmt = $pdo->prepare("
        SELECT p.id, p.po_no, p.status, p.created_at, p.delay_remarks
        FROM purchase_orders p
        WHERE p.supplier_id = ?
        ORDER BY p.created_at DESC
    ");
    $poStmt->execute([$supplier_id]);
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);

    // For each PO, fetch its items with expected vs actual quantities
    $itemStmt = $pdo->prepare("
        SELECT pi.item_code, pi.quantity AS expected_qty, i.item_name
        FROM po_items pi
        LEFT JOIN inventory i ON pi.item_code = i.item_code
        WHERE pi.po_id = ?
    ");

    $orders = [];
    $totalDelivered = 0;
    $goodDeliveries = 0;
    $discrepancies = 0;
    $delayed = 0;
    $pending = 0;

    foreach ($pos as $po) {
        $itemStmt->execute([$po['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $itemDetails = [];
        foreach ($items as $item) {
            $itemDetails[] = [
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'] ?? $item['item_code'],
                'expected_qty' => (int)$item['expected_qty']
            ];
        }

        // Classify the order
        $classification = 'pending';
        if (strpos($po['status'], 'Discrepancy') !== false) {
            $classification = 'discrepancy';
            $discrepancies++;
            $totalDelivered++;
        } elseif ($po['status'] === 'Delivered') {
            $classification = 'good';
            $goodDeliveries++;
            $totalDelivered++;
        } elseif (strpos($po['status'], 'Delayed') !== false) {
            $classification = 'delayed';
            $delayed++;
        } else {
            $pending++;
        }

        $orders[] = [
            'po_no' => $po['po_no'],
            'status' => $po['status'],
            'classification' => $classification,
            'date' => date('M d, Y', strtotime($po['created_at'])),
            'delay_remarks' => $po['delay_remarks'],
            'items' => $itemDetails
        ];
    }

    echo json_encode([
        'status' => 'success',
        'supplier' => $supplier,
        'orders' => $orders,
        'summary' => [
            'total' => count($pos),
            'delivered' => $totalDelivered,
            'good' => $goodDeliveries,
            'discrepancies' => $discrepancies,
            'delayed' => $delayed,
            'pending' => $pending
        ]
    ]);
    exit;
}

// --- AJAX: FETCH PO ITEMS FOR RECEIVING MODAL ---
elseif ($action === 'fetch_po_items') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin', 'purchasing'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? 0;

    $stmt = $pdo->prepare("
        SELECT pi.item_code, pi.quantity as expected_qty, COALESCE(pi.unit_price, i.unit_price, 0) as unit_price, COALESCE(i.item_name, pi.custom_item_name, pi.item_code) as item_name, pi.is_new_item, pi.category, pi.unit 
        FROM po_items pi 
        LEFT JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// --- AJAX: FETCH DETAILED PO DATA FOR VIRTUAL PAPER / PRINT ---
elseif ($action === 'fetch_po_details') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $po_id = (int)($_POST['po_id'] ?? 0);

    $poStmt = $pdo->prepare("
        SELECT 
            p.*, 
            s.company_name, 
            s.contact_person, 
            s.contact_number, 
            s.email AS supplier_email, 
            s.address AS supplier_address, 
            r.rs_no, 
            r.project_name, 
            u.name AS prepared_by_name,
            u.signature_path AS prepared_user_sig,
            app_u.name AS approved_by_name,
            app_u.signature_path AS approved_user_sig
        FROM purchase_orders p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN requisitions r ON p.rs_id = r.id
        LEFT JOIN users u ON p.prepared_by = u.id
        LEFT JOIN users app_u ON COALESCE(p.approved_by, r.approved_by) = app_u.id
        WHERE p.id = ?
    ");
    $poStmt->execute([$po_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
        exit;
    }

    $baseDir = dirname(__DIR__, 2) . '/';

    // Helper: checks if file exists on disk
    $checkSig = function($path) use ($baseDir) {
        if (empty($path)) return '';
        $clean = ltrim($path, '/');
        return file_exists($baseDir . $clean) ? $path : '';
    };

    // 1. Resolve Prepared By Signature
    $prepSig = $checkSig($po['prepared_signature'] ?? '');
    if (empty($prepSig)) {
        $prepSig = $checkSig($po['prepared_user_sig'] ?? '');
    }
    $po['prepared_signature'] = $prepSig;

    // 2. Resolve Approved By Signature & Name
    $appSig = $checkSig($po['approved_signature'] ?? '');
    if (empty($appSig)) {
        $appSig = $checkSig($po['approved_user_sig'] ?? '');
    }

    if (empty($po['approved_by_name']) || empty($appSig)) {
        $mgrStmt = $pdo->query("SELECT name, signature_path FROM users WHERE role IN ('management', 'admin') AND signature_path IS NOT NULL AND signature_path != '' ORDER BY (role='management') DESC, id ASC LIMIT 1");
        $mgr = $mgrStmt->fetch(PDO::FETCH_ASSOC);
        if ($mgr) {
            if (empty($po['approved_by_name'])) {
                $po['approved_by_name'] = $mgr['name'];
            }
            if (empty($appSig)) {
                $appSig = $checkSig($mgr['signature_path'] ?? '');
            }
        } else {
            if (empty($po['approved_by_name'])) {
                $po['approved_by_name'] = 'Management Authorization';
            }
        }
    }
    $po['approved_signature'] = $appSig;

    $itemsStmt = $pdo->prepare("
        SELECT 
            pi.item_code, 
            pi.quantity, 
            pi.unit_price, 
            COALESCE(i.item_name, pi.custom_item_name, pi.item_code) as item_name, 
            COALESCE(i.unit, pi.unit, 'pcs') as unit,
            pi.is_new_item,
            pi.category
        FROM po_items pi
        LEFT JOIN inventory i ON pi.item_code = i.item_code
        WHERE pi.po_id = ?
    ");
    $itemsStmt->execute([$po_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalAmount = 0;
    foreach ($items as &$item) {
        $item['subtotal'] = (float)$item['quantity'] * (float)$item['unit_price'];
        $totalAmount += $item['subtotal'];
    }

    echo json_encode([
        'status' => 'success',
        'po' => $po,
        'items' => $items,
        'total_amount' => $totalAmount,
        'formatted_date' => date('F d, Y', strtotime($po['created_at'])),
        'formatted_eta' => !empty($po['expected_delivery_date']) ? date('F d, Y', strtotime($po['expected_delivery_date'])) : 'Not Set'
    ]);
    exit;
}

// --- CREATE PURCHASE ORDER ---
elseif ($action === 'create_po') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        throw new Exception("Unauthorized action.");
    }

    $po_no = $_POST['po_no'];
    $rs_id = $_POST['rs_id'];
    $supplier_id = $_POST['supplier_id'];
    $prepared_by = $_SESSION['user_id'];
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;

    try {
        $pdo->beginTransaction();

        // Fetch Purchasing Officer Signature Path
        $prepUserStmt = $pdo->prepare("SELECT signature_path FROM users WHERE id = ?");
        $prepUserStmt->execute([$prepared_by]);
        $prepared_signature = $prepUserStmt->fetchColumn() ?: null;

        // Fetch Requisition Approved By & Signature Path
        $rsApprovedStmt = $pdo->prepare("
            SELECT r.approved_by, u.signature_path 
            FROM requisitions r 
            LEFT JOIN users u ON r.approved_by = u.id 
            WHERE r.id = ?
        ");
        $rsApprovedStmt->execute([$rs_id]);
        $rsApp = $rsApprovedStmt->fetch(PDO::FETCH_ASSOC);

        $approved_by = $rsApp['approved_by'] ?? null;
        $approved_signature = $rsApp['signature_path'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, rs_id, supplier_id, prepared_by, prepared_signature, approved_by, approved_signature, expected_delivery_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$po_no, $rs_id, $supplier_id, $prepared_by, $prepared_signature, $approved_by, $approved_signature, $expected_delivery_date]);
        $po_id = $pdo->lastInsertId();

        // Only copy items that management approved (excludes rejected items from Partially Approved RSes)
        $rsItemsStmt = $pdo->prepare("
            SELECT ri.item_code, ri.quantity, ri.is_new_item, ri.new_item_name, ri.new_category, ri.new_unit, i.unit_price 
            FROM requisition_items ri 
            LEFT JOIN inventory i ON ri.item_code = i.item_code 
            WHERE ri.requisition_id = ? AND ri.item_status = 'Approved'
        ");
        $rsItemsStmt->execute([$rs_id]);
        $rsItems = $rsItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $poItemStmt = $pdo->prepare("
            INSERT INTO po_items (po_id, item_code, quantity, unit_price, is_new_item, custom_item_name, category, unit) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($rsItems as $item) {
            $price = $item['unit_price'] ?? 0.00;
            $isNew = (int)($item['is_new_item'] ?? 0);
            $cName = $isNew ? $item['new_item_name'] : null;
            $cCat = $isNew ? $item['new_category'] : null;
            $cUnit = $isNew ? $item['new_unit'] : null;
            $poItemStmt->execute([$po_id, $item['item_code'], $item['quantity'], $price, $isNew, $cName, $cCat, $cUnit]);
        }

        // Cryptographically Seal Purchase Order with RSA-2048 PKI Signature
        try {
            require_once __DIR__ . '/../../helpers/crypto_helper.php';
            $signerUserId = $approved_by ?: $prepared_by;
            $signerKeys = getOrCreateUserKeyPair($pdo, $signerUserId);
            if ($signerKeys) {
                $poHeaderData = [
                    'po_no'       => $po_no,
                    'rs_no'       => $rsApp['rs_no'] ?? '',
                    'supplier_id' => $supplier_id,
                    'prepared_by' => $prepared_by,
                    'approved_by' => $approved_by,
                    'created_at'  => date('Y-m-d H:i:s')
                ];
                $payload = buildCanonicalPoPayload($poHeaderData, $rsItems);
                $signed = cryptographicallySignPayload($payload, $signerKeys['private']);
                if ($signed) {
                    $pdo->prepare("UPDATE purchase_orders SET crypto_signature = ?, document_hash = ?, signed_at = NOW() WHERE id = ?")
                        ->execute([$signed['signature'], $signed['hash'], $po_id]);
                }
            }
        } catch (Exception $cryptoEx) {
            error_log("PO Crypto Signing Notice: " . $cryptoEx->getMessage());
        }

        $pdo->prepare("UPDATE requisitions SET status = 'PO Created' WHERE id = ?")->execute([$rs_id]);

        $etaMsg = $expected_delivery_date ? " Target Warehouse ETA: " . date('M d, Y', strtotime($expected_delivery_date)) . "." : "";
        $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Incoming Delivery Expected', ?)");
        $notif->execute(["PO {$po_no} generated.{$etaMsg} Prepare space to receive materials."]);
        sendPushNotification($pdo, 'Incoming Delivery Expected', "PO {$po_no} generated.{$etaMsg} Prepare space to receive materials.", 'warehouse', null);

        $pdo->commit();

        $_SESSION['message'] = "Purchase Order generated and sent to Supplier successfully!";
        $_SESSION['msg_type'] = "success";
        header("Location: ../po");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// --- MARK PO DELIVERED / STOCK IN ---
elseif ($action === 'mark_po_delivered') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin', 'purchasing'])) {
        throw new Exception("Unauthorized.");
    }

    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $received_by = $_SESSION['user_id'];

    $item_codes = $_POST['item_codes'] ?? [];
    $actual_qtys = $_POST['actual_qtys'] ?? [];
    $expected_qtys = $_POST['expected_qtys'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];

    try {
        $pdo->beginTransaction();

        // Handle Proof of Receipt File Upload or Live Camera Snapshot
        $proofPath = null;
        if (isset($_FILES['proof_of_receipt']) && $_FILES['proof_of_receipt']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['proof_of_receipt']['tmp_name'];
            $origName = $_FILES['proof_of_receipt']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../../uploads/receipts/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $filename = 'receipt_' . $po_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($tmpName, $uploadDir . $filename)) {
                    $proofPath = 'uploads/receipts/' . $filename;
                }
            }
        }

        // Fallback: If no file uploaded, check if live camera photo was captured
        if (empty($proofPath) && !empty($_POST['captured_proof_base64'])) {
            $base64Str = $_POST['captured_proof_base64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Str, $type)) {
                $data = substr($base64Str, strpos($base64Str, ',') + 1);
                $typeStr = strtolower($type[1]);
                $data = base64_decode($data);
                if ($data !== false) {
                    $uploadDir = __DIR__ . '/../../uploads/receipts/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $ext = ($typeStr === 'jpeg') ? 'jpg' : $typeStr;
                    $filename = 'camera_receipt_' . $po_id . '_' . time() . '.' . $ext;
                    if (file_put_contents($uploadDir . $filename, $data)) {
                        $proofPath = 'uploads/receipts/' . $filename;
                    }
                }
            }
        }

        $updateInv = $pdo->prepare("
            UPDATE inventory i 
            JOIN units u ON i.unit = u.unit_name 
            SET i.quantity = i.quantity + ?, 
                i.unit_price = CASE WHEN ? > 0 THEN ? ELSE i.unit_price END,
                i.status = CASE 
                            WHEN (i.quantity + ?) <= 0 THEN 'Out of Stock'
                            WHEN (i.quantity + ?) <= u.reorder_level THEN 'Low Stock'
                            ELSE 'In Stock' 
                         END 
            WHERE i.item_code = ?
        ");

        $updatePoItem = $pdo->prepare("UPDATE po_items SET unit_price = ? WHERE po_id = ? AND item_code = ?");

        $discrepancyLog = "";
        $hasDiscrepancy = false;

        for ($i = 0; $i < count($item_codes); $i++) {
            $actual = (int)($actual_qtys[$i] ?? 0);
            $expected = (int)($expected_qtys[$i] ?? 0);
            $unit_price = (float)($unit_prices[$i] ?? 0);
            $code = $item_codes[$i];

            if ($unit_price > 0) {
                $updatePoItem->execute([$unit_price, $po_id, $code]);
            }

            // Check if item exists in master inventory with row lock
            $checkInv = $pdo->prepare("SELECT id, item_name, unit FROM inventory WHERE item_code = ? FOR UPDATE");
            $checkInv->execute([$code]);
            $existingInv = $checkInv->fetch(PDO::FETCH_ASSOC);

            if (!$existingInv) {
                // Uncataloged / New Item: Auto-insert into Master Inventory upon Stock-In
                $poMetaStmt = $pdo->prepare("SELECT custom_item_name, category, unit FROM po_items WHERE po_id = ? AND item_code = ?");
                $poMetaStmt->execute([$po_id, $code]);
                $poMeta = $poMetaStmt->fetch(PDO::FETCH_ASSOC);

                $itemName = !empty($poMeta['custom_item_name']) ? $poMeta['custom_item_name'] : ('New Item ' . $code);
                $cat = !empty($poMeta['category']) ? $poMeta['category'] : 'Materials';
                $unit = !empty($poMeta['unit']) ? $poMeta['unit'] : 'pcs';

                $reorderStmt = $pdo->prepare("SELECT reorder_level FROM units WHERE unit_name = ?");
                $reorderStmt->execute([$unit]);
                $reorderLevel = (int)($reorderStmt->fetchColumn() ?: 10);

                $newStatus = ($actual <= 0) ? 'Out of Stock' : (($actual <= $reorderLevel) ? 'Low Stock' : 'In Stock');

                $insertInv = $pdo->prepare("
                    INSERT INTO inventory (item_code, item_name, category, quantity, unit, unit_price, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertInv->execute([$code, $itemName, $cat, $actual, $unit, $unit_price, $newStatus]);
            } else {
                $itemName = $existingInv['item_name'];
                if ($actual > 0) {
                    $updateInv->execute([$actual, $unit_price, $unit_price, $actual, $actual, $code]);
                }
            }

            if ($actual != $expected) {
                $hasDiscrepancy = true;
                $soldOutNote = ($actual == 0) ? " ⚠️ [UNSUPPLIED / SOLD OUT]" : "";
                $discrepancyLog .= "\n- {$itemName} [Code: {$code}]: Expected {$expected}, Received {$actual}{$soldOutNote}";
                if ($unit_price > 0 && $actual > 0) {
                    $discrepancyLog .= " (Unit Price: ₱" . number_format($unit_price, 2) . ")";
                }
            }
        }

        if ($hasDiscrepancy) {
            $cleanDesc = trim($discrepancyLog);

            // Fetch existing remarks and strip previous discrepancy blocks to prevent duplicate appends
            $existingRemarksStmt = $pdo->prepare("SELECT delay_remarks FROM purchase_orders WHERE id = ?");
            $existingRemarksStmt->execute([$po_id]);
            $existingRemarks = $existingRemarksStmt->fetchColumn() ?: '';

            if (strpos($existingRemarks, '[DELIVERY DISCREPANCY]:') !== false) {
                $existingRemarks = preg_replace('/\[DELIVERY DISCREPANCY\]:.*$/s', '', $existingRemarks);
                $existingRemarks = trim($existingRemarks);
            }

            $newRemarks = !empty($existingRemarks) 
                ? $existingRemarks . "\n\n[DELIVERY DISCREPANCY]:\n" . $cleanDesc
                : "[DELIVERY DISCREPANCY]:\n" . $cleanDesc;

            $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered (Discrepancy)', delay_remarks = ?, proof_of_receipt = COALESCE(?, proof_of_receipt), received_by = ? WHERE id = ?")
                ->execute([$newRemarks, $proofPath, $received_by, $po_id]);

            $alertMsg = "DISCREPANCY ALERT for {$po_no}: Order arrived physically with missing or excess items!{$discrepancyLog}";
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Discrepancy Found', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Receiving Discrepancy', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Discrepancy Alert', ?)")->execute([$alertMsg]);

            sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'management', null);
            sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'purchasing', null);
            sendPushNotification($pdo, 'PO Discrepancy Found', $alertMsg, 'admin', null);

            $_SESSION['message'] = "Stock In partial/discrepancy recorded! Management has been notified of the mismatch.";
            $_SESSION['msg_type'] = "warning";
        } else {
            $pdo->prepare("UPDATE purchase_orders SET status = 'Delivered', proof_of_receipt = COALESCE(?, proof_of_receipt), received_by = ? WHERE id = ?")->execute([$proofPath, $received_by, $po_id]);

            $alertMsg = "Order {$po_no} has arrived complete. Exactly correct quantities and updated prices successfully STOCKED IN to Master Inventory.";
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Delivered & Verified', ?)")->execute([$alertMsg]);

            sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'purchasing', null);
            sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'management', null);
            sendPushNotification($pdo, 'PO Delivered & Verified', $alertMsg, 'admin', null);

            $_SESSION['message'] = "Stock In Successful! Delivered physical items and updated inventory value successfully saved.";
            $_SESSION['msg_type'] = "success";
        }

        $pdo->commit();

        header("Location: ../po");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// --- LOG PO DELAY ---
elseif ($action === 'log_po_delay') {
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        throw new Exception("Unauthorized.");
    }

    $po_id = $_POST['po_id'];
    $po_no = $_POST['po_no'];
    $newEta = !empty($_POST['new_eta']) ? $_POST['new_eta'] : null;

    $delayReason = $_POST['delay_type'];
    if (!empty($_POST['remarks'])) {
        $delayReason .= " - " . $_POST['remarks'];
    }

    if ($newEta) {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ?, expected_delivery_date = ? WHERE id = ?")
            ->execute([$delayReason, $newEta, $po_id]);
    } else {
        $pdo->prepare("UPDATE purchase_orders SET status = 'Delayed (Weather)', delay_remarks = ? WHERE id = ?")
            ->execute([$delayReason, $po_id]);
    }

    $alertMsg = "ALERT: {$po_no} is delayed. Reason: {$delayReason}";
    if ($newEta) {
        $alertMsg .= ". New ETA: " . date('M d, Y', strtotime($newEta));
    }

    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'Supply Chain Delay', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Expected Delivery Delayed', ?)")->execute([$alertMsg]);
    $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'Supply Chain Delay', ?)")->execute([$alertMsg]);

    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'management', null);
    sendPushNotification($pdo, 'Expected Delivery Delayed', $alertMsg, 'warehouse', null);
    sendPushNotification($pdo, 'Supply Chain Delay', $alertMsg, 'admin', null);

    $_SESSION['message'] = "Logistics delay & revised ETA successfully logged and alerts sent.";
    $_SESSION['msg_type'] = "warning";
    header("Location: ../po");
    exit;
}

// --- UPDATE PO ETA ---
elseif ($action === 'update_po_eta') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Purchasing Officers can update the ETA.']);
        exit;
    }

    $po_id = $_POST['po_id'] ?? null;
    $eta_date = $_POST['expected_delivery_date'] ?? null;

    if (!$po_id || !$eta_date) {
        echo json_encode(['status' => 'error', 'message' => 'PO ID and ETA date are required.']);
        exit;
    }

    $poStmt = $pdo->prepare("SELECT p.po_no, s.company_name FROM purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
    $poStmt->execute([$po_id]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
        exit;
    }

    $updateStmt = $pdo->prepare("UPDATE purchase_orders SET expected_delivery_date = ? WHERE id = ?");
    $updateStmt->execute([$eta_date, $po_id]);

    $formattedEta = date('M d, Y', strtotime($eta_date));
    $notifTitle = "🚚 Supply ETA Updated: " . $po['po_no'];
    $notifBody = "Delivery from {$po['company_name']} is now estimated to arrive at warehouse on {$formattedEta}.";

    foreach (['warehouse', 'management'] as $targetRole) {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, ?, ?)");
        $notifStmt->execute([$targetRole, $notifTitle, $notifBody]);
        sendPushNotification($pdo, $notifTitle, $notifBody, $targetRole, null);
    }

    echo json_encode([
        'status' => 'success',
        'message' => "ETA for {$po['po_no']} updated to {$formattedEta} successfully!",
        'eta_formatted' => $formattedEta
    ]);
    exit;
}
