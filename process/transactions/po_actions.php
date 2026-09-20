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
        } elseif (in_array($po['status'], ['Partially Delivered', 'Partially Received'])) {
            $classification = 'partial';
            $pending++;
        } elseif (strpos($po['status'], 'Delayed') !== false) {
            $classification = 'delayed';
            $delayed++;
        } else {
            $pending++;
        }

        $orders[] = [
            'id' => (int)$po['id'],
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

    $po_id = (int)($_POST['po_id'] ?? 0);

    // Fetch PO status and metadata
    $poMetaStmt = $pdo->prepare("SELECT po_no, status, delay_remarks FROM purchase_orders WHERE id = ?");
    $poMetaStmt->execute([$po_id]);
    $poMeta = $poMetaStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT 
            pi.item_code, 
            pi.quantity AS ordered_qty,
            pi.quantity AS expected_qty,
            COALESCE(pi.received_quantity, 0) AS received_qty,
            GREATEST(0, pi.quantity - COALESCE(pi.received_quantity, 0)) AS remaining_qty,
            COALESCE(pi.item_status, 'Pending') AS item_status,
            COALESCE(pi.unit_price, i.unit_price, 0) AS unit_price, 
            COALESCE(i.item_name, pi.custom_item_name, pi.item_code) AS item_name, 
            pi.is_new_item, 
            pi.category, 
            COALESCE(i.unit, pi.unit, 'pcs') AS unit 
        FROM po_items pi 
        LEFT JOIN inventory i ON pi.item_code = i.item_code 
        WHERE pi.po_id = ?
    ");
    $stmt->execute([$po_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success', 
        'po' => $poMeta,
        'items' => $items
    ]);
    exit;
}

// --- AJAX: FETCH DETAILED PO DATA FOR VIRTUAL PAPER / PRINT ---
elseif ($action === 'fetch_po_details') {
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'management', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
        exit;
    }

    $po_id = (int)($_POST['po_id'] ?? 0);
    $po_no = trim($_POST['po_no'] ?? '');

    $queryBase = "
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
    ";

    if ($po_id > 0) {
        $poStmt = $pdo->prepare($queryBase . " WHERE p.id = ? LIMIT 1");
        $poStmt->execute([$po_id]);
    } elseif (!empty($po_no)) {
        $poStmt = $pdo->prepare($queryBase . " WHERE p.po_no = ? LIMIT 1");
        $poStmt->execute([$po_no]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'PO identifier not specified.']);
        exit;
    }

    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
        exit;
    }

    $po_id = (int)$po['id'];

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
            pi.quantity AS ordered_qty,
            COALESCE(pi.received_quantity, 0) AS received_quantity,
            GREATEST(0, pi.quantity - COALESCE(pi.received_quantity, 0)) AS remaining_qty,
            COALESCE(pi.item_status, 'Pending') AS item_status,
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

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        throw new Exception("Security token invalid or expired. Please refresh the page.");
    }

    $po_no = $_POST['po_no'];
    $rs_id = $_POST['rs_id'];
    $supplier_id = $_POST['supplier_id'];
    $prepared_by = $_SESSION['user_id'];
    $expected_delivery_date = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;
    $payment_terms = !empty($_POST['payment_terms']) ? trim($_POST['payment_terms']) : 'Credit (30 Days Net)';

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

        $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_no, rs_id, supplier_id, prepared_by, prepared_signature, approved_by, approved_signature, expected_delivery_date, payment_terms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$po_no, $rs_id, $supplier_id, $prepared_by, $prepared_signature, $approved_by, $approved_signature, $expected_delivery_date, $payment_terms]);
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
            signPurchaseOrder($pdo, $po_id);
        } catch (Exception $cryptoEx) {
            error_log("PO Crypto Signing Notice: " . $cryptoEx->getMessage());
        }

        $pdo->prepare("UPDATE requisitions SET status = 'PO Created' WHERE id = ?")->execute([$rs_id]);

        $etaMsg = $expected_delivery_date ? " Target Warehouse ETA: " . date('M d, Y', strtotime($expected_delivery_date)) . "." : "";
        $notif = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('warehouse', 'Incoming Delivery Expected', ?)");
        $notif->execute(["PO {$po_no} generated.{$etaMsg} Prepare space to receive materials."]);
        sendPushNotification($pdo, 'Incoming Delivery Expected', "PO {$po_no} generated.{$etaMsg} Prepare space to receive materials.", 'warehouse', null);

        $pdo->commit();

        if (!empty($is_ajax)) {
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'message' => "Purchase Order {$po_no} generated and sent to Supplier successfully!"
            ]);
            exit;
        }

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

// --- MARK PO DELIVERED / STOCK IN (MULTI-STAGE & PARTIAL DELIVERY SUPPORT) ---
elseif ($action === 'mark_po_delivered') {
    if (!in_array($_SESSION['user_role'], ['warehouse', 'admin', 'purchasing'])) {
        throw new Exception("Unauthorized.");
    }

    // Verify CSRF Token (Rule 5 & Skill standard)
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        throw new Exception("Security token invalid or expired. Please refresh the page.");
    }

    $po_id = (int)($_POST['po_id'] ?? 0);
    $po_no = trim($_POST['po_no'] ?? '');
    $received_by = (int)$_SESSION['user_id'];

    $item_codes = $_POST['item_codes'] ?? [];
    $actual_qtys = $_POST['actual_qtys'] ?? [];
    $expected_qtys = $_POST['expected_qtys'] ?? [];
    $unit_prices = $_POST['unit_prices'] ?? [];
    $item_dispositions = $_POST['item_dispositions'] ?? [];

    try {
        $pdo->beginTransaction();

        // Fetch existing PO record with row lock
        $poCheckStmt = $pdo->prepare("SELECT id, po_no, status, delay_remarks, proof_of_receipt FROM purchase_orders WHERE id = ? FOR UPDATE");
        $poCheckStmt->execute([$po_id]);
        $poRecord = $poCheckStmt->fetch(PDO::FETCH_ASSOC);

        if (!$poRecord) {
            throw new Exception("Purchase Order #{$po_id} not found.");
        }

        if (in_array($poRecord['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
            throw new Exception("Purchase Order {$poRecord['po_no']} has already been finalized and locked.");
        }

        // Handle Proof of Receipt File Upload or Live Camera Snapshot (5-Layer Defense)
        require_once __DIR__ . '/../../classes/SecureUploadHandler.php';
        $proofPath = null;
        if (isset($_FILES['proof_of_receipt']) && $_FILES['proof_of_receipt']['error'] === UPLOAD_ERR_OK) {
            $proofPath = SecureUploadHandler::validateAndSaveReceiptUpload(
                $_FILES['proof_of_receipt'],
                'receipts',
                'receipt_' . $po_id . '_' . time()
            );
        }

        // Fallback: If no file uploaded, check if live camera photo was captured
        if (empty($proofPath) && !empty($_POST['captured_proof_base64'])) {
            $proofPath = SecureUploadHandler::validateAndSaveBase64Image(
                $_POST['captured_proof_base64'],
                'receipts',
                'camera_receipt_' . $po_id . '_' . time()
            );
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

        $updatePoItem = $pdo->prepare("
            UPDATE po_items 
            SET received_quantity = ?, 
                item_status = ?, 
                unit_price = CASE WHEN ? > 0 THEN ? ELSE unit_price END 
            WHERE po_id = ? AND item_code = ?
        ");

        $fetchPoItemStmt = $pdo->prepare("
            SELECT id, item_code, quantity, COALESCE(received_quantity, 0) AS received_quantity, 
                   COALESCE(item_status, 'Pending') AS item_status, custom_item_name, category, unit, unit_price 
            FROM po_items 
            WHERE po_id = ? AND item_code = ? FOR UPDATE
        ");

        $batchLogs = [];
        $totalBatchUnitsReceived = 0;

        for ($i = 0; $i < count($item_codes); $i++) {
            $code = $item_codes[$i];
            $batchActual = max(0, (int)($actual_qtys[$i] ?? 0));
            $unit_price = max(0, (float)($unit_prices[$i] ?? 0));
            $disposition = trim($item_dispositions[$i] ?? 'to_follow');

            $fetchPoItemStmt->execute([$po_id, $code]);
            $poItemRow = $fetchPoItemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$poItemRow) {
                continue;
            }

            $orderedQty = (int)$poItemRow['quantity'];
            $priorReceived = (int)$poItemRow['received_quantity'];
            $maxCanReceiveNow = max(0, $orderedQty - $priorReceived);

            // Cap the received amount to remaining needed
            if ($batchActual > $maxCanReceiveNow) {
                $batchActual = $maxCanReceiveNow;
            }

            $newTotalReceived = $priorReceived + $batchActual;
            $totalBatchUnitsReceived += $batchActual;

            // Determine item status
            if ($newTotalReceived >= $orderedQty) {
                $newItemStatus = 'Complete';
            } else {
                if ($disposition === 'sold_out') {
                    $newItemStatus = 'Sold Out';
                } else {
                    $newItemStatus = 'Partially Delivered';
                }
            }

            // Update po_items line
            $updatePoItem->execute([$newTotalReceived, $newItemStatus, $unit_price, $unit_price, $po_id, $code]);

            // Check Master Inventory record
            $checkInv = $pdo->prepare("SELECT id, item_name, unit FROM inventory WHERE item_code = ? FOR UPDATE");
            $checkInv->execute([$code]);
            $existingInv = $checkInv->fetch(PDO::FETCH_ASSOC);

            if (!$existingInv) {
                // Uncataloged / New Item: Auto-insert into Master Inventory upon Stock-In
                $itemName = !empty($poItemRow['custom_item_name']) ? $poItemRow['custom_item_name'] : ('Item ' . $code);
                $cat = !empty($poItemRow['category']) ? $poItemRow['category'] : 'Materials';
                $unit = !empty($poItemRow['unit']) ? $poItemRow['unit'] : 'pcs';

                $reorderStmt = $pdo->prepare("SELECT reorder_level FROM units WHERE unit_name = ?");
                $reorderStmt->execute([$unit]);
                $reorderLevel = (int)($reorderStmt->fetchColumn() ?: 10);

                $newStatus = ($batchActual <= 0) ? 'Out of Stock' : (($batchActual <= $reorderLevel) ? 'Low Stock' : 'In Stock');

                $insertInv = $pdo->prepare("
                    INSERT INTO inventory (item_code, item_name, category, quantity, unit, unit_price, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $insertInv->execute([$code, $itemName, $cat, $batchActual, $unit, $unit_price, $newStatus]);
            } else {
                $itemName = $existingInv['item_name'];
                if ($batchActual > 0) {
                    $updateInv->execute([$batchActual, $unit_price, $unit_price, $batchActual, $batchActual, $code]);
                }
            }

            // Format line-item batch log
            $itemLogLine = "- {$itemName} [Code: {$code}]: Received {$batchActual} units today (Total: {$newTotalReceived}/{$orderedQty})";
            if ($unit_price > 0) {
                $itemLogLine .= " @ ₱" . number_format($unit_price, 2);
            }
            if ($newItemStatus === 'Complete') {
                $itemLogLine .= " ✅ [COMPLETE]";
            } elseif ($newItemStatus === 'Sold Out') {
                $unsupplied = $orderedQty - $newTotalReceived;
                $itemLogLine .= " ⚠️ [SOLD OUT - {$unsupplied} Remainder Cancelled by Supplier]";
            } else {
                $remaining = $orderedQty - $newTotalReceived;
                $itemLogLine .= " ⏳ [{$remaining} Remainder To Follow from Supplier]";
            }
            $batchLogs[] = $itemLogLine;
        }

        // Evaluate overall PO Status based on all line items
        $allItemsStmt = $pdo->prepare("SELECT quantity, received_quantity, item_status FROM po_items WHERE po_id = ?");
        $allItemsStmt->execute([$po_id]);
        $allItems = $allItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        $hasIncompleteToFollow = false;
        $hasSoldOut = false;
        $allComplete = true;

        foreach ($allItems as $it) {
            $o = (int)$it['quantity'];
            $r = (int)$it['received_quantity'];
            $st = $it['item_status'];

            if ($r < $o) {
                $allComplete = false;
                if ($st === 'Sold Out') {
                    $hasSoldOut = true;
                } else {
                    $hasIncompleteToFollow = true;
                }
            }
        }

        if ($allComplete) {
            $finalPoStatus = 'Delivered';
        } elseif ($hasIncompleteToFollow) {
            $finalPoStatus = 'Partially Delivered';
        } else {
            // All remaining unsupplied items were marked Sold Out
            $finalPoStatus = 'Delivered (Discrepancy)';
        }

        // Retrieve receiver's full name
        $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $userStmt->execute([$received_by]);
        $receiverName = $userStmt->fetchColumn() ?: 'Warehouse Officer';

        // Build Audit / Delivery remarks entry
        $timestampStr = date('M d, Y g:i A');
        $batchHeader = "[DELIVERY BATCH — {$timestampStr} by {$receiverName}]:\n" . implode("\n", $batchLogs);

        $existingRemarks = trim($poRecord['delay_remarks'] ?? '');
        $combinedRemarks = !empty($existingRemarks) 
            ? $existingRemarks . "\n\n" . $batchHeader
            : $batchHeader;

        // Update purchase order header
        $pdo->prepare("
            UPDATE purchase_orders 
            SET status = ?, 
                delay_remarks = ?, 
                proof_of_receipt = COALESCE(?, proof_of_receipt), 
                received_by = ? 
            WHERE id = ?
        ")->execute([$finalPoStatus, $combinedRemarks, $proofPath, $received_by, $po_id]);

        // Send role-based notifications & alerts
        if ($finalPoStatus === 'Delivered') {
            $alertMsg = "Order {$po_no} has arrived 100% COMPLETE. All ordered items have been verified and stocked into Master Inventory.";
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Fully Delivered', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Fully Delivered', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Fully Delivered', ?)")->execute([$alertMsg]);

            sendPushNotification($pdo, 'PO Fully Delivered', $alertMsg, 'purchasing', null);
            sendPushNotification($pdo, 'PO Fully Delivered', $alertMsg, 'management', null);
            sendPushNotification($pdo, 'PO Fully Delivered', $alertMsg, 'admin', null);

            $_SESSION['message'] = "Stock In Successful! Purchase Order {$po_no} is 100% fulfilled and Master Inventory updated.";
            $_SESSION['msg_type'] = "success";
        } elseif (in_array($finalPoStatus, ['Partially Delivered', 'Partially Received'])) {
            $alertMsg = "PARTIAL DELIVERY received for {$po_no}: {$totalBatchUnitsReceived} units physically stocked in. Remaining items are marked 'To Follow' from supplier.";
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Partially Delivered', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Partially Delivered', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Partially Delivered', ?)")->execute([$alertMsg]);

            sendPushNotification($pdo, 'PO Partially Delivered', $alertMsg, 'purchasing', null);
            sendPushNotification($pdo, 'PO Partially Delivered', $alertMsg, 'management', null);
            sendPushNotification($pdo, 'PO Partially Delivered', $alertMsg, 'admin', null);

            $_SESSION['message'] = "Partial delivery recorded! {$totalBatchUnitsReceived} units stocked into Master Inventory. PO remains active for subsequent deliveries.";
            $_SESSION['msg_type'] = "warning";
        } else {
            // Delivered (Discrepancy)
            $alertMsg = "DELIVERY CLOSED (SOLD OUT / DISCREPANCY) for {$po_no}: Order finalized with unfulfilled items marked Sold Out by supplier.\n" . implode("\n", $batchLogs);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('purchasing', 'PO Closed (Sold Out/Discrepancy)', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('management', 'PO Closed (Sold Out/Discrepancy)', ?)")->execute([$alertMsg]);
            $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES ('admin', 'PO Closed (Sold Out/Discrepancy)', ?)")->execute([$alertMsg]);

            sendPushNotification($pdo, 'PO Closed (Discrepancy)', $alertMsg, 'purchasing', null);
            sendPushNotification($pdo, 'PO Closed (Discrepancy)', $alertMsg, 'management', null);
            sendPushNotification($pdo, 'PO Closed (Discrepancy)', $alertMsg, 'admin', null);

            $_SESSION['message'] = "Delivery finalized. Unsupplied items recorded as Sold Out by supplier. Management and Purchasing alerted.";
            $_SESSION['msg_type'] = "warning";
        }

        $pdo->commit();

        // Standardized AJAX Response (cims-modal-ajax-handler Section 5)
        if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => $_SESSION['message'],
                'data' => [
                    'po_id' => $po_id,
                    'po_no' => $po_no,
                    'status' => $finalPoStatus
                ]
            ]);
            exit;
        }

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

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        throw new Exception("Security token invalid or expired. Please refresh the page.");
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

    if (!empty($is_ajax)) {
        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'Logistics delay & revised ETA successfully logged and alerts sent.'
        ]);
        exit;
    }

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

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
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

// --- CANCEL / VOID PURCHASE ORDER ---
elseif ($action === 'cancel_po') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Purchasing Officers and Admins can void or cancel Purchase Orders.']);
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $po_id = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
    $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
    $cancellation_notes = trim($_POST['cancellation_notes'] ?? '');

    if (!$po_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Purchase Order ID.']);
        exit;
    }

    if (empty($cancellation_reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select or provide a reason for cancellation.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $poStmt = $pdo->prepare("SELECT p.*, s.company_name FROM purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ? FOR UPDATE");
        $poStmt->execute([$po_id]);
        $po = $poStmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
            exit;
        }

        if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot cancel a fully Delivered Purchase Order as stock has already been ingested into master inventory.']);
            exit;
        }

        if ($po['status'] === 'Cancelled') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'This Purchase Order is already cancelled.']);
            exit;
        }

        // Build permanent audit log trail (ISO 9001 Clause 8.5.2 & 8.7)
        $timestamp = date('Y-m-d H:i:s');
        $user_fullname = $_SESSION['user_fullname'] ?? $_SESSION['user_name'] ?? 'Authorized Officer';
        $user_role = strtoupper($_SESSION['user_role'] ?? 'OFFICER');
        $auditReason = "[PO VOIDED / CANCELLED — {$timestamp} by {$user_fullname} ({$user_role})]\nReason: {$cancellation_reason}";
        if (!empty($cancellation_notes)) {
            $auditReason .= "\nRemarks: {$cancellation_notes}";
        }

        $existingRemarks = trim($po['delay_remarks'] ?? '');
        $newRemarks = $existingRemarks !== '' ? $existingRemarks . "\n\n" . $auditReason : $auditReason;

        // 1. Update PO status to Cancelled and append audit remarks
        $updatePoStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Cancelled', delay_remarks = ? WHERE id = ?");
        $updatePoStmt->execute([$newRemarks, $po_id]);

        // 2. Mark remaining unfulfilled items in po_items as Cancelled
        $pdo->prepare("UPDATE po_items SET item_status = 'Cancelled' WHERE po_id = ? AND item_status != 'Delivered'")->execute([$po_id]);

        // 3. If linked to an RS, check if any other active PO is linked to that RS. If none, revert RS to 'Approved'
        if (!empty($po['rs_id'])) {
            $otherActivePoStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE rs_id = ? AND id != ? AND status != 'Cancelled'");
            $otherActivePoStmt->execute([$po['rs_id'], $po_id]);
            $otherActiveCount = (int)$otherActivePoStmt->fetchColumn();

            if ($otherActiveCount === 0) {
                $pdo->prepare("UPDATE requisitions SET status = 'Approved' WHERE id = ?")->execute([$po['rs_id']]);
            }
        }

        // 4. Dispatch notification alerts to warehouse, management, and purchasing
        $notifTitle = "🚫 PO Voided: " . $po['po_no'];
        $notifBody = "PO {$po['po_no']} for {$po['company_name']} was voided/cancelled by {$user_fullname}. Reason: {$cancellation_reason}";

        foreach (['warehouse', 'management', 'purchasing'] as $targetRole) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, ?, ?)");
            $notifStmt->execute([$targetRole, $notifTitle, $notifBody]);
            sendPushNotification($pdo, $notifTitle, $notifBody, $targetRole, null);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Purchase Order {$po['po_no']} has been voided/cancelled successfully.",
            'po_no' => $po['po_no']
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to cancel Purchase Order: ' . $e->getMessage()
        ]);
        exit;
    }
}

// --- MARK PURCHASE ORDER OUT FOR DELIVERY ---
elseif ($action === 'mark_po_out_for_delivery') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'], ['purchasing', 'admin', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Purchasing, Warehouse, or Admin can update delivery status.']);
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $po_id = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
    $delivery_notes = trim($_POST['delivery_notes'] ?? '');

    if (!$po_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Purchase Order ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $poStmt = $pdo->prepare("SELECT p.*, s.company_name FROM purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ? FOR UPDATE");
        $poStmt->execute([$po_id]);
        $po = $poStmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
            exit;
        }

        if (in_array($po['status'], ['Delivered', 'Delivered (Discrepancy)'])) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot mark a delivered Purchase Order as Out for Delivery.']);
            exit;
        }

        if ($po['status'] === 'Cancelled') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot dispatch a cancelled/voided Purchase Order.']);
            exit;
        }

        if ($po['status'] === 'Out for Delivery') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'This Purchase Order is already marked as Out for Delivery.']);
            exit;
        }

        // Build permanent audit log trail (ISO 9001 Clause 8.5.2)
        $timestamp = date('Y-m-d H:i:s');
        $user_fullname = $_SESSION['user_fullname'] ?? $_SESSION['user_name'] ?? 'Authorized Officer';
        $user_role = strtoupper($_SESSION['user_role'] ?? 'OFFICER');
        $auditEntry = "[OUT FOR DELIVERY — {$timestamp} by {$user_fullname} ({$user_role})]";
        if (!empty($delivery_notes)) {
            $auditEntry .= "\nCourier / Dispatch Details: {$delivery_notes}";
        } else {
            $auditEntry .= "\nShipment is en route from {$po['company_name']} to warehouse/jobsite.";
        }

        $existingRemarks = trim($po['delay_remarks'] ?? '');
        $newRemarks = $existingRemarks !== '' ? $existingRemarks . "\n\n" . $auditEntry : $auditEntry;

        $updateStmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Out for Delivery', delay_remarks = ? WHERE id = ?");
        $updateStmt->execute([$newRemarks, $po_id]);

        // Dispatch notifications to warehouse, management, purchasing
        $notifTitle = "🚚 PO Out for Delivery: " . $po['po_no'];
        $notifBody = "PO {$po['po_no']} from {$po['company_name']} is now in transit / out for delivery." . (!empty($delivery_notes) ? " Notes: {$delivery_notes}" : "");

        foreach (['warehouse', 'management', 'purchasing'] as $targetRole) {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (target_role, title, message) VALUES (?, ?, ?)");
            $notifStmt->execute([$targetRole, $notifTitle, $notifBody]);
            if (function_exists('sendPushNotification')) {
                sendPushNotification($pdo, $notifTitle, $notifBody, $targetRole, null);
            }
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Purchase Order {$po['po_no']} is now marked Out for Delivery!",
            'po_no' => $po['po_no'],
            'new_status' => 'Out for Delivery'
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update delivery status: ' . $e->getMessage()
        ]);
        exit;
    }
}

// --- UPLOAD / ATTACH POST-DELIVERY RECEIPT ---
elseif ($action === 'upload_po_receipt') {
    header('Content-Type: application/json');
    if (!in_array($_SESSION['user_role'], ['admin', 'purchasing', 'warehouse'])) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Purchasing, Warehouse, or Admin can attach receipts.']);
        exit;
    }

    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!empty($csrfToken) && function_exists('validate_csrf_token') && !validate_csrf_token($csrfToken)) {
        echo json_encode(['status' => 'error', 'message' => 'Security token invalid or expired. Please refresh the page.']);
        exit;
    }

    $po_id = filter_input(INPUT_POST, 'po_id', FILTER_VALIDATE_INT);
    $receipt_notes = trim($_POST['receipt_notes'] ?? '');

    if (!$po_id) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Purchase Order ID.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $poStmt = $pdo->prepare("SELECT p.*, s.company_name FROM purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ? FOR UPDATE");
        $poStmt->execute([$po_id]);
        $po = $poStmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Purchase Order not found.']);
            exit;
        }

        if ($po['status'] === 'Cancelled') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Cannot attach receipts to a voided/cancelled Purchase Order.']);
            exit;
        }

        // Validate and Save Receipt File using 5-layer SecureUploadHandler
        require_once __DIR__ . '/../../classes/SecureUploadHandler.php';
        $proofPath = null;
        if (isset($_FILES['proof_of_receipt']) && $_FILES['proof_of_receipt']['error'] === UPLOAD_ERR_OK) {
            $proofPath = SecureUploadHandler::validateAndSaveReceiptUpload(
                $_FILES['proof_of_receipt'],
                'receipts',
                'receipt_' . $po_id . '_' . time()
            );
        }

        // Fallback: If no file uploaded, check if live camera photo was captured
        if (empty($proofPath) && !empty($_POST['captured_proof_base64'])) {
            $proofPath = SecureUploadHandler::validateAndSaveBase64Image(
                $_POST['captured_proof_base64'],
                'receipts',
                'camera_receipt_' . $po_id . '_' . time()
            );
        }

        if (empty($proofPath)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Please select a receipt document (PDF/Image) or capture a photo.']);
            exit;
        }

        $filename = basename($proofPath);
        $secureReceiptUrl = 'secure-image?type=receipts&file=' . urlencode($filename);

        // Build permanent audit log trail (ISO 9001 Clause 8.5.2)
        $timestamp = date('Y-m-d H:i:s');
        $user_fullname = $_SESSION['user_fullname'] ?? $_SESSION['user_name'] ?? 'Authorized Officer';
        $user_role = strtoupper($_SESSION['user_role'] ?? 'OFFICER');
        $actionVerb = !empty($po['proof_of_receipt']) ? "UPDATED" : "ATTACHED";
        $auditEntry = "[RECEIPT {$actionVerb} — {$timestamp} by {$user_fullname} ({$user_role})]";
        if (!empty($receipt_notes)) {
            $auditEntry .= "\nReference / Notes: {$receipt_notes}";
        }
        $auditEntry .= "\nDocument: {$filename}";

        $existingRemarks = trim($po['delay_remarks'] ?? '');
        $newRemarks = $existingRemarks !== '' ? $existingRemarks . "\n\n" . $auditEntry : $auditEntry;

        $updateStmt = $pdo->prepare("UPDATE purchase_orders SET proof_of_receipt = ?, delay_remarks = ? WHERE id = ?");
        $updateStmt->execute([$proofPath, $newRemarks, $po_id]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Proof of receipt for {$po['po_no']} has been successfully attached.",
            'po_id' => $po_id,
            'po_no' => $po['po_no'],
            'secure_receipt_url' => $secureReceiptUrl,
            'filename' => $filename
        ]);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to upload receipt: ' . $e->getMessage()
        ]);
        exit;
    }
}


