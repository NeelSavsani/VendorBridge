<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

// ========================================
// Input
// ========================================

$body = get_json_input();

require_fields(
    $body,
    [
        'rfq_id',
        'vendor_ids'
    ]
);

$rfqId = (int)$body['rfq_id'];

if ($rfqId <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

if (
    !is_array($body['vendor_ids']) ||
    empty($body['vendor_ids'])
) {

    error_response(
        'vendor_ids must be a non-empty array',
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
            title
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

    // ========================================
    // Assignment Statement
    // ========================================

    $assignStmt = $db->prepare("
        INSERT INTO rfq_vendors
        (
            rfq_id,
            vendor_id
        )
        VALUES
        (
            ?, ?
        )
    ");

    $assigned = [];

    foreach ($body['vendor_ids'] as $vendorId) {

        $vendorId = (int)$vendorId;

        if ($vendorId <= 0) {
            continue;
        }

        // ====================================
        // Check Vendor Exists
        // ====================================

        $stmt = $db->prepare("
            SELECT
                id,
                company_name
            FROM vendors
            WHERE id = ?
        ");

        $stmt->execute([
            $vendorId
        ]);

        $vendor = $stmt->fetch();

        if (!$vendor) {
            continue;
        }

        // ====================================
        // Prevent Duplicate Assignment
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

        if ($stmt->fetch()) {
            continue;
        }

        // ====================================
        // Assign Vendor
        // ====================================

        $assignStmt->execute([
            $rfqId,
            $vendorId
        ]);

        $assigned[] = [
            'id' => $vendorId,
            'company_name' => $vendor['company_name']
        ];

        // ====================================
        // Notification
        // ====================================

        create_notification(
            $vendorId,
            'New RFQ Assigned',
            "RFQ {$rfq['rfq_number']} ({$rfq['title']}) has been assigned to you."
        );
    }

    $db->commit();

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'RFQ_VENDOR_ASSIGNED',
        'rfq',
        $rfqId,
        'Assigned vendors to RFQ ' .
        $rfq['rfq_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Vendors assigned successfully',
        [
            'rfq_id' => $rfqId,
            'rfq_number' => $rfq['rfq_number'],
            'assigned_vendors' => $assigned
        ]
    );

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_response(
        $e->getMessage(),
        500
    );
}
