<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

$db = getDB();

// ========================================
// Filters
// ========================================

$status = sanitize(
    $_GET['status'] ?? ''
);

$search = sanitize(
    $_GET['search'] ?? ''
);

$pagination = get_pagination();

$where = ['1=1'];
$binds = [];

// ========================================
// Status Filter
// ========================================

if ($status) {

    $where[] = "po.status = ?";
    $binds[] = $status;
}

// ========================================
// Search Filter
// ========================================

if ($search) {

    $where[] = "
        (
            po.po_number LIKE ?
            OR q.quotation_number LIKE ?
            OR r.rfq_number LIKE ?
            OR v.company_name LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

// ========================================
// Vendor Restriction
// ========================================

if (
    isset($auth['role']) &&
    $auth['role'] === 'vendor'
) {

    $stmt = $db->prepare("
        SELECT id
        FROM vendors
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $auth['id']
    ]);

    $vendor = $stmt->fetch();

    if (!$vendor) {

        success_response(
            'No purchase orders found',
            [
                'purchase_orders' => []
            ]
        );
    }

    $where[] = "po.vendor_id = ?";
    $binds[] = $vendor['id'];
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        po.id,
        po.po_number,
        po.quotation_id,
        po.rfq_id,
        po.vendor_id,
        po.total_amount,
        po.delivery_date,
        po.status,
        po.created_at,

        v.company_name AS vendor_name,

        r.title AS rfq_title,
        r.rfq_number,

        q.quotation_number,

        u.name AS created_by_name

    FROM purchase_orders po

    INNER JOIN vendors v
        ON v.id = po.vendor_id

    INNER JOIN rfqs r
        ON r.id = po.rfq_id

    INNER JOIN quotations q
        ON q.id = po.quotation_id

    INNER JOIN users u
        ON u.id = po.created_by

    WHERE " . implode(' AND ', $where) . "

    ORDER BY po.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$purchaseOrders = $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$summary = [
    'total_records' => count($purchaseOrders),
    'pending' => 0,
    'sent' => 0,
    'accepted' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($purchaseOrders as $po) {

    $statusValue = strtolower(
        $po['status']
    );

    if (
        isset($summary[$statusValue])
    ) {
        $summary[$statusValue]++;
    }
}

// ========================================
// Response
// ========================================

success_response(
    'Purchase Orders retrieved successfully',
    [
        'purchase_orders' => $purchaseOrders,
        'summary' => $summary,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);