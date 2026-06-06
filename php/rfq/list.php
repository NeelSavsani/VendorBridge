<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

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

    $where[] = "r.status = ?";
    $binds[] = $status;
}

// ========================================
// Search Filter
// ========================================

if ($search) {

    $where[] = "
        (
            r.rfq_number LIKE ?
            OR r.title LIKE ?
            OR r.category LIKE ?
        )
    ";

    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
    $binds[] = "%{$search}%";
}

// ========================================
// Vendor Access Control
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
            'No RFQs found',
            [
                'rfqs' => [],
                'page' => 1,
                'limit' => $pagination['limit']
            ]
        );
    }

    $where[] = "
        EXISTS
        (
            SELECT 1
            FROM rfq_vendors rv
            WHERE rv.rfq_id = r.id
            AND rv.vendor_id = ?
        )
    ";

    $binds[] = $vendor['id'];
}

// ========================================
// RFQ List Query
// ========================================

$sql = "
    SELECT
        r.id,
        r.rfq_number,
        r.title,
        r.category,
        r.deadline,
        r.budget,
        r.status,
        r.created_at,
        u.name AS created_by_name,
        COUNT(DISTINCT qi.id) AS item_count,
        COUNT(DISTINCT q.id) AS quotation_count
    FROM rfqs r

    LEFT JOIN users u
        ON u.id = r.created_by

    LEFT JOIN rfq_items qi
        ON qi.rfq_id = r.id

    LEFT JOIN quotations q
        ON q.rfq_id = r.id

    WHERE " . implode(' AND ', $where) . "

    GROUP BY r.id

    ORDER BY r.created_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$rfqs = $stmt->fetchAll();

// ========================================
// Response
// ========================================

success_response(
    'RFQs retrieved successfully',
    [
        'rfqs' => $rfqs,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);
