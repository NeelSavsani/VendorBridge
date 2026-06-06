<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

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
        'po_id'
    ]
);

$poId = (int)$body['po_id'];

if ($poId <= 0) {

    error_response(
        'Invalid Purchase Order ID',
        400
    );
}

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Existing Invoice Check
    // ========================================

    $stmt = $db->prepare("
        SELECT id
        FROM invoices
        WHERE po_id = ?
    ");

    $stmt->execute([
        $poId
    ]);

    if ($stmt->fetch()) {

        throw new Exception(
            'Invoice already exists for this Purchase Order'
        );
    }

    // ========================================
    // Get Purchase Order
    // ========================================

    $stmt = $db->prepare("
        SELECT
            po.*,
            v.user_id,
            v.company_name
        FROM purchase_orders po

        INNER JOIN vendors v
            ON v.id = po.vendor_id

        WHERE po.id = ?
    ");

    $stmt->execute([
        $poId
    ]);

    $po = $stmt->fetch();

    if (!$po) {

        throw new Exception(
            'Purchase Order not found'
        );
    }

    // ========================================
    // Status Validation
    // ========================================

    if (
        !in_array(
            strtolower($po['status']),
            [
                'accepted',
                'completed'
            ]
        )
    ) {

        throw new Exception(
            'Invoice can only be created for accepted or completed Purchase Orders'
        );
    }

    // ========================================
    // Generate Invoice Number
    // ========================================

    $invoiceNumber =
        'INV-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );

    // ========================================
    // Amounts
    // ========================================

    $subtotal =
        (float)$po['subtotal'];

    $taxAmount =
        (float)$po['tax_amount'];

    $interState =
        (bool)($body['inter_state'] ?? false);

    $cgst = 0;
    $sgst = 0;
    $igst = 0;

    if ($interState) {

        $igst = $taxAmount;

    } else {

        $cgst = round(
            $taxAmount / 2,
            2
        );

        $sgst = round(
            $taxAmount / 2,
            2
        );
    }

    $grandTotal =
        round(
            $subtotal + $taxAmount,
            2
        );

    // ========================================
    // Due Date
    // ========================================

    $dueDate =
        date(
            'Y-m-d',
            strtotime('+30 days')
        );

    // ========================================
    // Create Invoice
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO invoices
        (
            invoice_number,
            po_id,
            vendor_id,
            subtotal,
            cgst,
            sgst,
            igst,
            total_amount,
            due_date,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent'
        )
    ");

    $stmt->execute([
        $invoiceNumber,
        $poId,
        $po['vendor_id'],
        $subtotal,
        $cgst,
        $sgst,
        $igst,
        $grandTotal,
        $dueDate
    ]);

    $invoiceId =
        $db->lastInsertId();

    // ========================================
    // Vendor Notification
    // ========================================

    if (
        !empty($po['user_id'])
    ) {

        create_notification(
            $po['user_id'],
            'Invoice Generated',
            'Invoice ' .
            $invoiceNumber .
            ' has been generated for your Purchase Order.'
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
        'INVOICE_GENERATED',
        'invoice',
        $invoiceId,
        'Invoice ' .
        $invoiceNumber .
        ' generated for PO ' .
        $po['po_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Invoice generated successfully',
        [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'po_id' => $poId,
            'total_amount' => $grandTotal,
            'due_date' => $dueDate
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