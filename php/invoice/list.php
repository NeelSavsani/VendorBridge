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

    $where[] = "i.status = ?";
    $binds[] = $status;
}

// ========================================
// Search Filter
// ========================================

if ($search) {

    $where[] = "
        (
            i.invoice_number LIKE ?
            OR po.po_number LIKE ?
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
            'No invoices found',
            [
                'invoices' => []
            ]
        );
    }

    $where[] = "i.vendor_id = ?";
    $binds[] = $vendor['id'];
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        i.id,
        i.invoice_number,
        i.po_id,
        i.vendor_id,
        i.total_amount,
        i.due_date,
        i.status,
        i.created_at,

        v.company_name AS vendor_name,

        po.po_number,

        r.rfq_number,
        r.title AS rfq_title

    FROM invoices i

    INNER JOIN vendors v
        ON v.id = i.vendor_id

    INNER JOIN purchase_orders po
        ON po.id = i.po_id

    INNER JOIN rfqs r
        ON r.id = po.rfq_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY i.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$invoices = $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$summary = [
    'total_records' => count($invoices),
    'draft' => 0,
    'sent' => 0,
    'paid' => 0,
    'overdue' => 0
];

$totalAmount = 0;

foreach ($invoices as $invoice) {

    $statusValue = strtolower(
        $invoice['status']
    );

    if (
        isset($summary[$statusValue])
    ) {
        $summary[$statusValue]++;
    }

    $totalAmount +=
        (float)$invoice['total_amount'];
}

$summary['total_amount'] =
    round($totalAmount, 2);

// ========================================
// Response
// ========================================

success_response(
    'Invoices retrieved successfully',
    [
        'invoices' => $invoices,
        'summary' => $summary,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);