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

$rfqId = (int)($_GET['rfq_id'] ?? 0);
$vendorId = (int)($_GET['vendor_id'] ?? 0);
$status = sanitize($_GET['status'] ?? '');

$pagination = get_pagination();

$where = ['1=1'];
$binds = [];

// ========================================
// RFQ Filter
// ========================================

if ($rfqId > 0) {

    $where[] = "q.rfq_id = ?";
    $binds[] = $rfqId;
}

// ========================================
// Vendor Filter
// ========================================

if ($vendorId > 0) {

    $where[] = "q.vendor_id = ?";
    $binds[] = $vendorId;
}

// ========================================
// Status Filter
// ========================================

if ($status) {

    $where[] = "q.status = ?";
    $binds[] = $status;
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
            'No quotations found',
            [
                'quotations' => []
            ]
        );
    }

    $where[] = "q.vendor_id = ?";
    $binds[] = $vendor['id'];
}

// ========================================
// Query
// ========================================

$sql = "
    SELECT
        q.id,
        q.quotation_number,
        q.rfq_id,
        q.vendor_id,
        q.total_amount,
        q.delivery_days,
        q.validity_days,
        q.status,
        q.submitted_at,

        v.company_name AS vendor_name,

        r.rfq_number,
        r.title AS rfq_title

    FROM quotations q

    INNER JOIN vendors v
        ON v.id = q.vendor_id

    INNER JOIN rfqs r
        ON r.id = q.rfq_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY q.submitted_at DESC

    LIMIT ?
    OFFSET ?
";

$binds[] = $pagination['limit'];
$binds[] = $pagination['offset'];

$stmt = $db->prepare($sql);

$stmt->execute($binds);

$quotations = $stmt->fetchAll();

// ========================================
// Response
// ========================================

success_response(
    'Quotations retrieved successfully',
    [
        'quotations' => $quotations,
        'page' => $pagination['page'],
        'limit' => $pagination['limit']
    ]
);