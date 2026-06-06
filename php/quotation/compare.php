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
// RFQ ID
// ========================================

$rfqId = (int)($_REQUEST['_params']['rfq_id'] ?? 0);

if ($rfqId <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

$db = getDB();

// ========================================
// RFQ Details
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        rfq_number,
        title,
        category,
        budget,
        deadline,
        status
    FROM rfqs
    WHERE id = ?
");

$stmt->execute([$rfqId]);

$rfq = $stmt->fetch();

if (!$rfq) {

    error_response(
        'RFQ not found',
        404
    );
}

// ========================================
// Quotations
// ========================================

$stmt = $db->prepare("
    SELECT
        q.id,
        q.quotation_number,
        q.vendor_id,
        q.total_amount,
        q.delivery_days,
        q.validity_days,
        q.status,
        q.submitted_at,

        v.company_name,
        v.rating,
        v.gst_number,
        v.email AS vendor_email

    FROM quotations q

    INNER JOIN vendors v
        ON v.id = q.vendor_id

    WHERE q.rfq_id = ?

    ORDER BY q.total_amount ASC
");

$stmt->execute([$rfqId]);

$quotations = $stmt->fetchAll();

// ========================================
// Items
// ========================================

foreach ($quotations as &$quotation) {

    $stmt = $db->prepare("
        SELECT
            id,
            item_name,
            quantity,
            unit_price,
            total_price
        FROM quotation_items
        WHERE quotation_id = ?
        ORDER BY id ASC
    ");

    $stmt->execute([
        $quotation['id']
    ]);

    $quotation['items'] =
        $stmt->fetchAll();

    $quotation['is_best_price'] = false;
    $quotation['is_fastest'] = false;
    $quotation['is_best_rated'] = false;
}

// ========================================
// Comparison Logic
// ========================================

if (!empty($quotations)) {

    // Lowest Price

    $quotations[0]['is_best_price'] = true;

    // Fastest Delivery

    $fastest = array_reduce(
        $quotations,
        function ($carry, $item) {

            return (
                $carry === null ||
                $item['delivery_days'] <
                $carry['delivery_days']
            )
                ? $item
                : $carry;
        },
        null
    );

    // Best Rating

    $bestRated = array_reduce(
        $quotations,
        function ($carry, $item) {

            return (
                $carry === null ||
                $item['rating'] >
                $carry['rating']
            )
                ? $item
                : $carry;
        },
        null
    );

    foreach ($quotations as &$quotation) {

        $quotation['is_fastest'] =
            (
                $fastest &&
                $quotation['id'] ==
                $fastest['id']
            );

        $quotation['is_best_rated'] =
            (
                $bestRated &&
                $quotation['id'] ==
                $bestRated['id']
            );
    }
}

// ========================================
// Summary
// ========================================

$prices = array_column(
    $quotations,
    'total_amount'
);

$summary = [
    'total_quotations' => count($quotations),
    'lowest_price' => !empty($prices)
        ? min($prices)
        : 0,
    'highest_price' => !empty($prices)
        ? max($prices)
        : 0,
    'average_price' => !empty($prices)
        ? round(
            array_sum($prices) /
            count($prices),
            2
        )
        : 0
];

// ========================================
// Response
// ========================================

success_response(
    'Quotation comparison retrieved successfully',
    [
        'rfq' => $rfq,
        'summary' => $summary,
        'quotations' => $quotations
    ]
);