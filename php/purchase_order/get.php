<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

// ========================================
// Purchase Order ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid Purchase Order ID',
        400
    );
}

$db = getDB();

// ========================================
// Get Purchase Order
// ========================================

$stmt = $db->prepare("
    SELECT
        po.id,
        po.po_number,
        po.quotation_id,
        po.rfq_id,
        po.vendor_id,
        po.subtotal,
        po.tax_percent,
        po.tax_amount,
        po.total_amount,
        po.delivery_date,
        po.status,
        po.created_at,

        v.company_name,
        v.contact_person,
        v.email AS vendor_email,
        v.phone AS vendor_phone,
        v.gst_number,
        v.address AS vendor_address,

        r.rfq_number,
        r.title AS rfq_title,

        q.quotation_number,
        q.delivery_days,
        q.notes,

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

    WHERE po.id = ?
");

$stmt->execute([$id]);

$po = $stmt->fetch();

if (!$po) {

    error_response(
        'Purchase Order not found',
        404
    );
}

// ========================================
// Vendor Access Restriction
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

    if (
        !$vendor ||
        $vendor['id'] != $po['vendor_id']
    ) {

        error_response(
            'Access denied',
            403
        );
    }
}

// ========================================
// Purchase Order Items
// ========================================

$stmt = $db->prepare("
    SELECT
        qi.id,
        qi.rfq_item_id,
        qi.item_name,
        qi.quantity,
        qi.unit_price,
        qi.total_price

    FROM quotation_items qi

    INNER JOIN quotations q
        ON q.id = qi.quotation_id

    WHERE q.id = ?

    ORDER BY qi.id ASC
");

$stmt->execute([
    $po['quotation_id']
]);

$po['items'] =
    $stmt->fetchAll();

// ========================================
// Response
// ========================================

success_response(
    'Purchase Order retrieved successfully',
    $po
);