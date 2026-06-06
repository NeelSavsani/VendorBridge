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
        'title',
        'deadline'
    ]
);

// ========================================
// Validation
// ========================================

if (
    strtotime($body['deadline']) === false
) {
    error_response(
        'Invalid deadline',
        400
    );
}

if (
    strtotime($body['deadline']) < time()
) {
    error_response(
        'Deadline cannot be in the past',
        400
    );
}

// ========================================
// Database
// ========================================

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // RFQ Number
    // ========================================

    $rfqNumber = generate_rfq_number();

    // ========================================
    // Create RFQ
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO rfqs
        (
            rfq_number,
            title,
            description,
            category,
            deadline,
            budget,
            status,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $rfqNumber,
        sanitize($body['title']),
        sanitize($body['description'] ?? ''),
        sanitize($body['category'] ?? 'General'),
        $body['deadline'],
        $body['budget'] ?? null,
        'open',
        $auth['id']
    ]);

    $rfqId = $db->lastInsertId();

    // ========================================
    // RFQ Items
    // ========================================

    if (
        !empty($body['items']) &&
        is_array($body['items'])
    ) {

        $itemStmt = $db->prepare("
            INSERT INTO rfq_items
            (
                rfq_id,
                item_name,
                description,
                quantity,
                unit
            )
            VALUES
            (
                ?, ?, ?, ?, ?
            )
        ");

        foreach ($body['items'] as $item) {

            if (
                empty($item['item_name'])
            ) {
                continue;
            }

            $itemStmt->execute([
                $rfqId,
                sanitize($item['item_name']),
                sanitize($item['description'] ?? ''),
                (int)($item['quantity'] ?? 1),
                sanitize($item['unit'] ?? 'Nos')
            ]);
        }
    }

    // ========================================
    // Vendor Assignment
    // ========================================

    if (
        !empty($body['vendor_ids']) &&
        is_array($body['vendor_ids'])
    ) {

        $vendorStmt = $db->prepare("
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

        foreach ($body['vendor_ids'] as $vendorId) {

            $vendorStmt->execute([
                $rfqId,
                (int)$vendorId
            ]);

            // Notification

            create_notification(
                (int)$vendorId,
                'New RFQ Assigned',
                "RFQ {$rfqNumber} has been assigned to you"
            );
        }
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
        'RFQ_CREATED',
        'rfq',
        $rfqId,
        "RFQ created: {$rfqNumber} - {$body['title']}"
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'RFQ created successfully',
        [
            'rfq_id' => $rfqId,
            'rfq_number' => $rfqNumber
        ]
    );

} catch (Exception $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_response(
        'Failed to create RFQ',
        500
    );
}
