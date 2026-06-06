<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'procurement_officer',
    'admin'
]);

// ========================================
// Input
// ========================================

$body = get_json_input();

require_fields(
    $body,
    [
        'quotation_id'
    ]
);

$quotationId = (int)$body['quotation_id'];

if ($quotationId <= 0) {

    error_response(
        'Invalid quotation ID',
        400
    );
}

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Get Approved Quotation
    // ========================================

    $stmt = $db->prepare("
        SELECT
            q.*,
            v.company_name,
            r.rfq_number
        FROM quotations q

        INNER JOIN vendors v
            ON v.id = q.vendor_id

        INNER JOIN rfqs r
            ON r.id = q.rfq_id

        WHERE q.id = ?
        AND q.status = 'approved'
    ");

    $stmt->execute([
        $quotationId
    ]);

    $quotation = $stmt->fetch();

    if (!$quotation) {

        throw new Exception(
            'Approved quotation not found'
        );
    }

    // ========================================
    // Existing PO Check
    // ========================================

    $stmt = $db->prepare("
        SELECT id
        FROM purchase_orders
        WHERE quotation_id = ?
    ");

    $stmt->execute([
        $quotationId
    ]);

    if ($stmt->fetch()) {

        throw new Exception(
            'Purchase Order already exists'
        );
    }

    // ========================================
    // Generate PO Number
    // ========================================

    $poNumber =
        'PO-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );

    // ========================================
    // Amount Calculation
    // ========================================

    $subtotal =
        (float)$quotation['total_amount'];

    $taxPercent =
        (float)($body['tax_percent'] ?? 18);

    $taxAmount =
        round(
            ($subtotal * $taxPercent) / 100,
            2
        );

    $grandTotal =
        round(
            $subtotal + $taxAmount,
            2
        );

    // ========================================
    // Delivery Date
    // ========================================

    $deliveryDays =
        (int)($quotation['delivery_days'] ?? 14);

    $deliveryDate =
        date(
            'Y-m-d',
            strtotime(
                "+{$deliveryDays} days"
            )
        );

    // ========================================
    // Create PO
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO purchase_orders
        (
            po_number,
            quotation_id,
            rfq_id,
            vendor_id,
            subtotal,
            tax_percent,
            tax_amount,
            total_amount,
            delivery_date,
            status,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?
        )
    ");

    $stmt->execute([
        $poNumber,
        $quotationId,
        $quotation['rfq_id'],
        $quotation['vendor_id'],
        $subtotal,
        $taxPercent,
        $taxAmount,
        $grandTotal,
        $deliveryDate,
        $auth['id']
    ]);

    $poId =
        $db->lastInsertId();

    // ========================================
    // Update Quotation
    // ========================================

    $stmt = $db->prepare("
        UPDATE quotations
        SET status = 'selected'
        WHERE id = ?
    ");

    $stmt->execute([
        $quotationId
    ]);

    // ========================================
    // Update RFQ
    // ========================================

    $stmt = $db->prepare("
        UPDATE rfqs
        SET status = 'awarded'
        WHERE id = ?
    ");

    $stmt->execute([
        $quotation['rfq_id']
    ]);

    // ========================================
    // Notify Vendor
    // ========================================

    $stmt = $db->prepare("
        SELECT user_id
        FROM vendors
        WHERE id = ?
    ");

    $stmt->execute([
        $quotation['vendor_id']
    ]);

    $vendor = $stmt->fetch();

    if (
        $vendor &&
        !empty($vendor['user_id'])
    ) {

        create_notification(
            $vendor['user_id'],
            'Purchase Order Created',
            'Purchase Order ' .
            $poNumber .
            ' has been issued.'
        );
    }

    // ========================================
    // Commit
    // ========================================

    $db->commit();

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'PO_CREATED',
        'purchase_order',
        $poId,
        'Purchase Order ' .
        $poNumber .
        ' created from quotation ' .
        $quotation['quotation_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Purchase Order created successfully',
        [
            'po_id' => $poId,
            'po_number' => $poNumber,
            'quotation_id' => $quotationId,
            'total_amount' => $grandTotal,
            'delivery_date' => $deliveryDate
        ]
    );

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_response(
        $e->getMessage(),
        400
    );
}