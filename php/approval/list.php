<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'manager',
    'admin'
]);

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

    $where[] = "a.status = ?";
    $binds[] = $status;
}

// ========================================
// Search Filter
// ========================================

if ($search) {

    $where[] = "
        (
            q.quotation_number LIKE ?
            OR r.rfq_number LIKE ?
            OR v.company_name LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

// ========================================
// Manager Restriction
// ========================================

if (
    $auth['role'] === 'manager'
) {

    $where[] = "a.approver_id = ?";
    $binds[] = $auth['id'];
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        a.id,
        a.quotation_id,
        a.approver_id,
        a.status,
        a.created_at,
        a.remarks,

        q.quotation_number,
        q.total_amount,

        v.company_name AS vendor_name,

        r.title AS rfq_title,
        r.rfq_number,

        u.name AS approver_name

    FROM approvals a

    INNER JOIN quotations q
        ON q.id = a.quotation_id

    INNER JOIN vendors v
        ON v.id = q.vendor_id

    INNER JOIN rfqs r
        ON r.id = q.rfq_id

    INNER JOIN users u
        ON u.id = a.approver_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY a.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);
$stmt->execute($binds);

$approvals = $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$summary = [
    'total_records' => count($approvals),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($approvals as $approval) {

    $statusValue = strtolower(
        $approval['status']
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
    'Approvals retrieved successfully',
    [
        'approvals' => $approvals,
        'summary' => $summary,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);