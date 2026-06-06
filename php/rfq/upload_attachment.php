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
// RFQ ID
// ========================================

$rfqId = (int)($_POST['rfq_id'] ?? 0);

if ($rfqId <= 0) {

    error_response(
        'Invalid RFQ ID',
        400
    );
}

// ========================================
// File Validation
// ========================================

if (
    !isset($_FILES['attachment']) ||
    $_FILES['attachment']['error'] !== UPLOAD_ERR_OK
) {

    error_response(
        'Attachment file is required',
        400
    );
}

$db = getDB();

// ========================================
// Check RFQ Exists
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        rfq_number
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
// Upload File
// ========================================

$fileInfo = upload_file(
    $_FILES['attachment']
);

if (!$fileInfo) {

    error_response(
        'Invalid file or upload failed',
        400
    );
}

try {

    // ========================================
    // Save Attachment Record
    // ========================================

    $stmt = $db->prepare("
        INSERT INTO rfq_attachments
        (
            rfq_id,
            file_name,
            original_name,
            file_path,
            file_size,
            uploaded_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $rfqId,
        $fileInfo['filename'],
        $fileInfo['original_name'],
        $fileInfo['path'],
        $fileInfo['size'],
        $auth['id']
    ]);

    $attachmentId = $db->lastInsertId();

    // ========================================
    // Activity Log
    // ========================================

    log_activity(
        $auth['id'],
        'RFQ_ATTACHMENT_UPLOADED',
        'rfq',
        $rfqId,
        'Attachment uploaded to RFQ ' .
        $rfq['rfq_number']
    );

    // ========================================
    // Response
    // ========================================

    success_response(
        'Attachment uploaded successfully',
        [
            'attachment_id' => $attachmentId,
            'rfq_id' => $rfqId,
            'rfq_number' => $rfq['rfq_number'],
            'file_name' => $fileInfo['filename'],
            'original_name' => $fileInfo['original_name'],
            'file_size' => $fileInfo['size']
        ]
    );

} catch (Exception $e) {

    error_response(
        'Failed to save attachment',
        500
    );
}