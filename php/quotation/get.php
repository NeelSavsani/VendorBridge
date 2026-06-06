<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

// ========================================
// Quotation ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid quotation ID',
        400
    );
}

$db = getDB();

// ========================================
// Get Quotation
// ========================================

$stmt = $db->prepare("
    SELECT
        q.id,
        q.quotation_number,
        q.rfq_id,
        q.vendor_id,
        q.total_amount,
        q.delivery_days,
        q.validity_days,
        q.notes,
        q.status,
        q.submitted_at,

        v.company_name,
        v.email AS vendor_email,
        v.phone,
        v.rating,
        v.gst_number,

        r.rfq_number,
        r.title AS rfq_title

    FROM quotations q

    INNER JOIN vendors v
        ON v.id = q.vendor_id

    INNER JOIN rfqs r
        ON r.id = q.rfq_id

    WHERE q.id = ?
");

$stmt->execute([$id]);

$quotation = $stmt->fetch();

if (!$quotation) {

    error_response(
        'Quotation not found',
        404
    );
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

    if (
        !$vendor ||
        $vendor['id'] != $quotation['vendor_id']
    ) {

        error_response(
            'Access denied',
            403
        );
    }
}

// ========================================
// Quotation Items
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        rfq_item_id,
        item_name,
        quantity,
        unit_price,
        total_price
    FROM quotation_items
    WHERE quotation_id = ?
    ORDER BY id ASC
");

$stmt->execute([$id]);

$quotation['items'] =
    $stmt->fetchAll();

// ========================================
// Response
// ========================================

success_response(
    'Quotation retrieved successfully',
    $quotation
);