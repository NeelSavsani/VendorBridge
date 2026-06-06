<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

// ========================================
// Invoice ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid Invoice ID',
        400
    );
}

$db = getDB();

// ========================================
// Get Invoice
// ========================================

$stmt = $db->prepare("
    SELECT
        i.id,
        i.invoice_number,
        i.po_id,
        i.vendor_id,
        i.subtotal,
        i.cgst,
        i.sgst,
        i.igst,
        i.total_amount,
        i.due_date,
        i.status,
        i.created_at,

        v.company_name,
        v.contact_person,
        v.email AS vendor_email,
        v.phone AS vendor_phone,
        v.gst_number,
        v.address AS vendor_address,

        po.po_number,
        po.delivery_date,
        po.tax_percent,

        r.rfq_number,
        r.title AS rfq_title,

        q.id AS quotation_id,
        q.quotation_number

    FROM invoices i

    INNER JOIN vendors v
        ON v.id = i.vendor_id

    INNER JOIN purchase_orders po
        ON po.id = i.po_id

    INNER JOIN rfqs r
        ON r.id = po.rfq_id

    INNER JOIN quotations q
        ON q.id = po.quotation_id

    WHERE i.id = ?
");

$stmt->execute([$id]);

$invoice = $stmt->fetch();

if (!$invoice) {

    error_response(
        'Invoice not found',
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
        $vendor['id'] != $invoice['vendor_id']
    ) {

        error_response(
            'Access denied',
            403
        );
    }
}

// ========================================
// Invoice Items
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

    WHERE qi.quotation_id = ?

    ORDER BY qi.id ASC
");

$stmt->execute([
    $invoice['quotation_id']
]);

$invoice['items'] =
    $stmt->fetchAll();

// ========================================
// Summary
// ========================================

$invoice['summary'] = [
    'item_count' => count(
        $invoice['items']
    ),
    'subtotal' => $invoice['subtotal'],
    'cgst' => $invoice['cgst'],
    'sgst' => $invoice['sgst'],
    'igst' => $invoice['igst'],
    'grand_total' => $invoice['total_amount']
];

// ========================================
// Response
// ========================================

success_response(
    'Invoice retrieved successfully',
    $invoice
);