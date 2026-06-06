<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'vendor',
    'admin',
    'procurement_officer'
]);

// ========================================
// Quotation ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid quotation ID',
        400
    );
}

$db = getDB();

try {

    $db->beginTransaction();

    // ========================================
    // Get Quotation
    // ========================================

    $stmt = $db->prepare("
        SELECT
            q.*,
            r.rfq_number
        FROM quotations q
        INNER JOIN rfqs r
            ON r.id = q.rfq_id
        WHERE q.id = ?
    ");

    $stmt->execute([$id]);

    $quotation = $stmt->fetch();

    if (!$quotation) {

        throw new Exception(
            'Quotation not found'
        );
    }

    // ========================================
    // Vendor Permission Check
    // ========================================

    if ($auth['role'] === 'vendor') {

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
            $vendor['id'] != $quotation['vendor_id']
        ) {

            throw new Exception(
                'Access denied'
            );
        }
    }

    // ========================================
    // Already Submitted
    // ========================================

    if (
        strtolower($quotation['status']) ===
        'submitted'
    ) {

        throw new Exception(
            'Quotation already submitted'
        );
    }

    // ========================================
    // Submit Quotation
    // ========================================

    $stmt = $db->prepare("
        UPDATE quotations
        SET
            status = 'submitted',
            submitted_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    // ========================================
    // Notification
    // ========================================

    $stmt = $db->prepare("
        SELECT created_by
        FROM rfqs
        WHERE id = ?
    ");

    $stmt->execute([
        $quotation['rfq_id']
    ]);

    $rfq = $stmt->fetch();

    if ($rfq) {

        create_notification(
            $rfq['created_by'],
            'Quotation Submitted',
            'Quotation ' .
            $quotation['quotation_number'] .
            ' has been submitted.'
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
        'QUOTATION_SUBMITTED',
        'quotation',
        $id,
        'Quotation submitted: ' .
        $quotation['quotation_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Quotation submitted successfully',
        [
            'quotation_id' => $id,
            'quotation_number' =>
                $quotation['quotation_number'],
            'status' => 'submitted'
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
