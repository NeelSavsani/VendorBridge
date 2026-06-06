<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'procurement_officer',
    'manager'
]);

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
        po.*,

        v.company_name,
        v.contact_person,
        v.email AS vendor_email,
        v.phone,
        v.gst_number,
        v.address,

        r.rfq_number,
        r.title AS rfq_title,

        q.quotation_number,
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
// Get PO Items
// ========================================

$stmt = $db->prepare("
    SELECT
        qi.id,
        qi.item_name,
        qi.quantity,
        qi.unit_price,
        qi.total_price

    FROM quotation_items qi

    WHERE qi.quotation_id = ?

    ORDER BY qi.id ASC
");

$stmt->execute([
    $po['quotation_id']
]);

$items = $stmt->fetchAll();

// ========================================
// Totals
// ========================================

$totalQuantity = 0;

foreach ($items as $item) {

    $totalQuantity +=
        (float)$item['quantity'];
}

// ========================================
// Activity Log
// ========================================

log_activity(
    $auth['id'],
    'PO_GENERATED',
    'purchase_order',
    $id,
    'Purchase Order document generated: ' .
    $po['po_number']
);

// ========================================
// Response
// ========================================

success_response(
    'Purchase Order generated successfully',
    [
        'purchase_order' => $po,
        'items' => $items,
        'summary' => [
            'total_items' => count($items),
            'total_quantity' => $totalQuantity,
            'subtotal' => $po['subtotal'],
            'tax_percent' => $po['tax_percent'],
            'tax_amount' => $po['tax_amount'],
            'grand_total' => $po['total_amount']
        ]
    ]
);