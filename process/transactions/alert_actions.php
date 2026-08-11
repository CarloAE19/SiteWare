<?php
// ==========================================
// ALERT ACTIONS
// ==========================================

if ($action === 'fetch_combined_alerts') {
    header('Content-Type: application/json');

    $userRole = $_SESSION['user_role'] ?? 'warehouse';
    $combinedAlerts = [];
    $totalUnread = 0;
    $today = date('Y-m-d');

    // 1. Fetch Supply ETA Alerts from Purchase Orders
    $poQuery = "
        SELECT p.id, p.po_no, p.status, p.expected_delivery_date, p.created_at, s.company_name
        FROM purchase_orders p
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.status NOT IN ('Delivered', 'Cancelled')
        ORDER BY 
            CASE 
                WHEN p.expected_delivery_date = ? THEN 1
                WHEN p.expected_delivery_date < ? THEN 2
                ELSE 3 
            END,
            p.expected_delivery_date ASC
        LIMIT 10
    ";
    $poStmt = $pdo->prepare($poQuery);
    $poStmt->execute([$today, $today]);
    $pos = $poStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pos as $po) {
        $eta = $po['expected_delivery_date'];
        $type = 'supply_eta';
        $badgeClass = 'bg-secondary';
        $icon = 'bi-truck';
        $category = 'on_track';
        $timeAgo = 'Scheduled';

        if (!empty($eta)) {
            $daysDiff = (int) (strtotime($eta) - strtotime($today)) / 86400;
            if ($daysDiff == 0) {
                $category = 'arriving_today';
                $badgeClass = 'bg-warning text-dark';
                $icon = 'bi-truck-flatbed';
                $title = "🚚 Arriving Today: PO {$po['po_no']}";
                $message = "Supplies from {$po['company_name']} scheduled to arrive at warehouse today (" . date('M d', strtotime($eta)) . ").";
                $timeAgo = "TODAY";
                $totalUnread++;
            } elseif ($daysDiff < 0) {
                $category = 'overdue';
                $badgeClass = 'bg-danger';
                $icon = 'bi-exclamation-triangle-fill';
                $daysOverdue = abs((int)$daysDiff);
                $title = "⚠️ Overdue Delivery: PO {$po['po_no']}";
                $message = "Supply delivery from {$po['company_name']} is overdue by {$daysOverdue} day(s). Target was " . date('M d', strtotime($eta)) . ".";
                $timeAgo = "Overdue";
                $totalUnread++;
            } else {
                $category = 'on_track';
                $badgeClass = 'bg-info text-dark';
                $icon = 'bi-box-seam';
                $title = "📦 Scheduled Supply: PO {$po['po_no']}";
                $message = "Supplies from {$po['company_name']} expected on " . date('M d, Y', strtotime($eta)) . " (in {$daysDiff} days).";
                $timeAgo = "In {$daysDiff}d";
            }
        } else {
            $title = "📦 Pending Delivery: PO {$po['po_no']}";
            $message = "Supplies from {$po['company_name']} pending delivery. Target ETA not set yet.";
        }

        $combinedAlerts[] = [
            'id' => 'po_' . $po['id'],
            'po_id' => $po['id'],
            'po_no' => $po['po_no'],
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'time_ago' => $timeAgo,
            'is_read' => ($category === 'on_track') ? 1 : 0,
            'badge_class' => $badgeClass,
            'icon' => $icon,
            'created_at' => $po['created_at']
        ];
    }



    // 3. Fetch System Notifications
    $notifStmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE (target_role = ? OR target_role = 'all')
        ORDER BY created_at DESC 
        LIMIT 6
    ");
    $notifStmt->execute([$userRole]);
    $systemNotifs = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($systemNotifs as $sys) {
        if ($sys['is_read'] == 0) $totalUnread++;
        $combinedAlerts[] = [
            'id' => 'sys_' . $sys['id'],
            'type' => 'system_alert',
            'category' => 'system',
            'title' => $sys['title'],
            'message' => $sys['message'],
            'time_ago' => time_elapsed_string($sys['created_at']),
            'is_read' => (int) $sys['is_read'],
            'badge_class' => ($sys['is_read'] == 0) ? 'bg-primary' : 'bg-secondary',
            'icon' => 'bi-bell-fill',
            'created_at' => $sys['created_at']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'total_unread' => $totalUnread,
        'alerts' => $combinedAlerts
    ]);
    exit;
}
