<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'procurement_officer',
    'admin',
    'manager'
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
    // Check Quotation Exists
    // ========================================

    $stmt = $db->prepare("
        SELECT
            q.id,
            q.quotation_number,
            q.status,
            q.rfq_id,
            q.vendor_id,
            r.rfq_number
        FROM quotations q
        INNER JOIN rfqs r
            ON r.id = q.rfq_id
        WHERE q.id = ?
    ");

    $stmt->execute([
        $quotationId
    ]);

    $quotation = $stmt->fetch();

    if (!$quotation) {

        throw new Exception(
            'Quotation not found'
        );
    }

    // ========================================
    // Status Validation
    // ========================================

    if (
        in_array(
            strtolower($quotation['status']),
            [
                'approved',
                'rejected'
            ]
        )
    ) {

        throw new Exception(
            'Quotation is already finalized'
        );
    }

    // ========================================
    // Existing Approval Check
    // ========================================

    $stmt = $db->prepare("
        SELECT id
        FROM approvals
        WHERE quotation_id = ?
    ");

    $stmt->execute([
        $quotationId
    ]);

    if ($stmt->fetch()) {

        throw new Exception(
            'Approval already exists for this quotation'
        );
    }

    // ========================================
    // Determine Approver
    // ========================================

    if (!empty($body['approver_id'])) {

        $approverId =
            (int)$body['approver_id'];

    } else {

        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE role IN
            (
                'manager',
                'admin'
            )
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute();

        $approver = $stmt->fetch();

        $approverId =
            $approver['id']
            ?? $auth['id'];
    }

    // ========================================
    // Create Approval Request
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO approvals
        (
            quotation_id,
            approver_id,
            status,
            requested_by,
            requested_at
        )
        VALUES
        (
            ?, ?, 'pending', ?, NOW()
        )
    ");

    $stmt->execute([
        $quotationId,
        $approverId,
        $auth['id']
    ]);

    $approvalId =
        $db->lastInsertId();

    // ========================================
    // Update Quotation Status
    // ========================================

    $stmt = $db->prepare("
        UPDATE quotations
        SET status = 'under_review'
        WHERE id = ?
    ");

    $stmt->execute([
        $quotationId
    ]);

    // ========================================
    // Notification
    // ========================================

    create_notification(
        $approverId,
        'Approval Required',
        'Quotation ' .
        $quotation['quotation_number'] .
        ' requires your approval.'
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
        'APPROVAL_REQUESTED',
        'approval',
        $approvalId,
        'Approval requested for quotation ' .
        $quotation['quotation_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Approval request submitted successfully',
        [
            'approval_id' => $approvalId,
            'quotation_id' => $quotationId,
            'quotation_number' =>
                $quotation['quotation_number'],
            'approver_id' => $approverId,
            'status' => 'pending'
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
