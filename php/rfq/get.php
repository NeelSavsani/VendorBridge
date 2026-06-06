<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

// ========================================
// RFQ ID Validation
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

$db = getDB();

// ========================================
// Vendor Access Control
// ========================================

if (
    isset($auth['role']) &&
    $auth['role'] === 'vendor'
) {

    $stmt = $db->prepare("
        SELECT 1
        FROM rfq_vendors rv
        INNER JOIN vendors v
            ON v.id = rv.vendor_id
        WHERE rv.rfq_id = ?
        AND v.user_id = ?
    ");

    $stmt->execute([
        $id,
        $auth['id']
    ]);

    if (!$stmt->fetch()) {

        error_response(
            'Access denied',
            403
        );
    }
}

// ========================================
// RFQ Details
// ========================================

$stmt = $db->prepare("
    SELECT
        r.id,
        r.rfq_number,
        r.title,
        r.description,
        r.category,
        r.deadline,
        r.budget,
        r.status,
        r.created_at,
        r.created_by,
        u.name AS created_by_name
    FROM rfqs r
    LEFT JOIN users u
        ON u.id = r.created_by
    WHERE r.id = ?
");

$stmt->execute([$id]);

$rfq = $stmt->fetch();

if (!$rfq) {

    error_response(
        'RFQ not found',
        404
    );
}

// ========================================
// RFQ Items
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        item_name,
        description,
        quantity,
        unit
    FROM rfq_items
    WHERE rfq_id = ?
    ORDER BY id ASC
");

$stmt->execute([$id]);

$rfq['items'] = $stmt->fetchAll();

// ========================================
// Assigned Vendors
// ========================================

$stmt = $db->prepare("
    SELECT
        v.id,
        v.company_name,
        v.contact_person,
        v.email,
        v.phone,
        v.category,
        v.status
    FROM vendors v
    INNER JOIN rfq_vendors rv
        ON rv.vendor_id = v.id
    WHERE rv.rfq_id = ?
    ORDER BY v.company_name ASC
");

$stmt->execute([$id]);

$rfq['vendors'] = $stmt->fetchAll();

// ========================================
// Quotation Count
// ========================================

$stmt = $db->prepare("
    SELECT COUNT(*) AS quotation_count
    FROM quotations
    WHERE rfq_id = ?
");

$stmt->execute([$id]);

$quotationData = $stmt->fetch();

$rfq['quotation_count'] =
    (int)($quotationData['quotation_count'] ?? 0);

// ========================================
// Response
// ========================================

success_response(
    'RFQ retrieved successfully',
    $rfq
);
