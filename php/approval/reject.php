<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'manager',
    'admin'
]);

// ========================================
// Approval ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid approval ID',
        400
    );
}

$body = get_json_input();

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Get Approval Details
    // ========================================

    $stmt = $db->prepare("
        SELECT
            a.id,
            a.quotation_id,
            a.approver_id,
            a.status,

            q.quotation_number,
            q.vendor_id,
            q.rfq_id

        FROM approvals a

        INNER JOIN quotations q
            ON q.id = a.quotation_id

        WHERE a.id = ?
    ");

    $stmt->execute([$id]);

    $approval = $stmt->fetch();

    if (!$approval) {

        throw new Exception(
            'Approval request not found'
        );
    }

    // ========================================
    // Permission Check
    // ========================================

    if (
        $approval['approver_id'] != $auth['id']
        &&
        $auth['role'] !== 'admin'
    ) {

        throw new Exception(
            'You are not assigned to reject this request'
        );
    }

    // ========================================
    // Status Check
    // ========================================

    if (
        strtolower($approval['status']) !==
        'pending'
    ) {

        throw new Exception(
            'Approval already processed'
        );
    }

    // ========================================
    // Reject Approval
    // ========================================

    $stmt = $db->prepare("
        UPDATE approvals
        SET
            status = 'rejected',
            approved_at = NOW(),
            remarks = ?
        WHERE id = ?
    ");

    $stmt->execute([
        sanitize(
            $body['remarks'] ?? ''
        ),
        $id
    ]);

    // ========================================
    // Update Quotation
    // ========================================

    $stmt = $db->prepare("
        UPDATE quotations
        SET status = 'rejected'
        WHERE id = ?
    ");

    $stmt->execute([
        $approval['quotation_id']
    ]);

    // ========================================
    // Notify Procurement Team
    // ========================================

    $stmt = $db->prepare("
        SELECT created_by
        FROM rfqs
        WHERE id = ?
    ");

    $stmt->execute([
        $approval['rfq_id']
    ]);

    $rfq = $stmt->fetch();

    if ($rfq) {

        create_notification(
            $rfq['created_by'],
            'Quotation Rejected',
            'Quotation ' .
            $approval['quotation_number'] .
            ' has been rejected.'
        );
    }

    // ========================================
    // Notify Vendor
    // ========================================

    $stmt = $db->prepare("
        SELECT user_id
        FROM vendors
        WHERE id = ?
    ");

    $stmt->execute([
        $approval['vendor_id']
    ]);

    $vendor = $stmt->fetch();

    if (
        $vendor &&
        !empty($vendor['user_id'])
    ) {

        create_notification(
            $vendor['user_id'],
            'Quotation Rejected',
            'Your quotation ' .
            $approval['quotation_number'] .
            ' has been rejected.'
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
        'APPROVAL_REJECTED',
        'approval',
        $id,
        'Rejected quotation ' .
        $approval['quotation_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Quotation rejected successfully',
        [
            'approval_id' => $id,
            'quotation_id' =>
                $approval['quotation_id'],
            'quotation_number' =>
                $approval['quotation_number'],
            'status' => 'rejected'
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
