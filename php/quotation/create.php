<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'vendor',
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
        'rfq_id',
        'total_amount'
    ]
);

$rfqId = (int)$body['rfq_id'];

if ($rfqId <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Check RFQ Exists
    // ========================================

    $stmt = $db->prepare("
        SELECT
            id,
            rfq_number,
            title,
            status,
            created_by
        FROM rfqs
        WHERE id = ?
    ");

    $stmt->execute([$rfqId]);

    $rfq = $stmt->fetch();

    if (!$rfq) {

        throw new Exception(
            'RFQ not found'
        );
    }

    if ($rfq['status'] !== 'open') {

        throw new Exception(
            'RFQ is not open for quotations'
        );
    }

    // ========================================
    // Determine Vendor
    // ========================================

    if ($auth['role'] === 'vendor') {

        $stmt = $db->prepare("
            SELECT
                id,
                company_name
            FROM vendors
            WHERE user_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $auth['id']
        ]);

        $vendor = $stmt->fetch();

        if (!$vendor) {

            throw new Exception(
                'Vendor profile not found'
            );
        }

        $vendorId = $vendor['id'];

        // ====================================
        // Verify RFQ Assignment
        // ====================================

        $stmt = $db->prepare("
            SELECT id
            FROM rfq_vendors
            WHERE rfq_id = ?
            AND vendor_id = ?
        ");

        $stmt->execute([
            $rfqId,
            $vendorId
        ]);

        if (!$stmt->fetch()) {

            throw new Exception(
                'RFQ not assigned to this vendor'
            );
        }

    } else {

        $vendorId = (int)($body['vendor_id'] ?? 0);

        if ($vendorId <= 0) {

            throw new Exception(
                'vendor_id is required'
            );
        }
    }

    // ========================================
    // Prevent Duplicate Quotation
    // ========================================

    $stmt = $db->prepare("
        SELECT id
        FROM quotations
        WHERE rfq_id = ?
        AND vendor_id = ?
    ");

    $stmt->execute([
        $rfqId,
        $vendorId
    ]);

    if ($stmt->fetch()) {

        throw new Exception(
            'Quotation already submitted'
        );
    }

    // ========================================
    // Generate Quotation Number
    // ========================================

    $quotationNumber =
        'QT-' .
        date('Y') .
        '-' .
        strtoupper(
            substr(
                uniqid(),
                -6
            )
        );

    // ========================================
    // Create Quotation
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO quotations
        (
            quotation_number,
            rfq_id,
            vendor_id,
            total_amount,
            delivery_days,
            validity_days,
            notes,
            status
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, 'submitted'
        )
    ");

    $stmt->execute([
        $quotationNumber,
        $rfqId,
        $vendorId,
        (float)$body['total_amount'],
        (int)($body['delivery_days'] ?? 14),
        (int)($body['validity_days'] ?? 30),
        sanitize(
            $body['notes'] ?? ''
        )
    ]);

    $quotationId =
        $db->lastInsertId();

    // ========================================
    // Quotation Items
    // ========================================

    if (
        !empty($body['items']) &&
        is_array($body['items'])
    ) {

        $itemStmt = $db->prepare("
            INSERT INTO quotation_items
            (
                quotation_id,
                rfq_item_id,
                item_name,
                quantity,
                unit_price,
                total_price
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?
            )
        ");

        foreach ($body['items'] as $item) {

            if (
                empty($item['item_name'])
            ) {
                continue;
            }

            $quantity =
                (float)($item['quantity'] ?? 1);

            $unitPrice =
                (float)($item['unit_price'] ?? 0);

            $totalPrice =
                $quantity * $unitPrice;

            $itemStmt->execute([
                $quotationId,
                $item['rfq_item_id'] ?? null,
                sanitize(
                    $item['item_name']
                ),
                $quantity,
                $unitPrice,
                $totalPrice
            ]);
        }
    }

    // ========================================
    // Notification
    // ========================================

    create_notification(
        $rfq['created_by'],
        'New Quotation Submitted',
        "Quotation {$quotationNumber} submitted for RFQ {$rfq['rfq_number']}"
    );

    // ========================================
    // Commit
    // ========================================

    $db->commit();

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'QUOTATION_SUBMITTED',
        'quotation',
        $quotationId,
        "Quotation submitted: {$quotationNumber} for RFQ {$rfq['rfq_number']}"
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Quotation submitted successfully',
        [
            'quotation_id' => $quotationId,
            'quotation_number' => $quotationNumber,
            'rfq_id' => $rfqId
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
