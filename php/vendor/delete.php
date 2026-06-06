<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth([
    'admin',
    'procurement_officer'
]);

// ========================================
// Get Vendor ID
// ========================================

$id = (int)($_REQUEST['_params']['id'] ?? 0);

if ($id <= 0) {

    error_response(
        'Invalid vendor ID',
        400
    );
}

// ========================================
// Database
// ========================================

$db = getDB();

// ========================================
// Check Vendor Exists
// ========================================

$stmt = $db->prepare("
    SELECT
        id,
        company_name,
        status
    FROM vendors
    WHERE id = ?
");

$stmt->execute([$id]);

$vendor = $stmt->fetch();

if (!$vendor) {

    error_response(
        'Vendor not found',
        404
    );
}

// ========================================
// Already Inactive
// ========================================

if (
    isset($vendor['status']) &&
    strtolower($vendor['status']) === 'inactive'
) {

    error_response(
        'Vendor already inactive',
        400
    );
}

// ========================================
// Soft Delete Vendor
// ========================================

$stmt = $db->prepare("
    UPDATE vendors
    SET status = 'inactive'
    WHERE id = ?
");

$stmt->execute([$id]);

// ========================================
// Activity Log
// ========================================

log_activity(
    $auth['id'],
    'VENDOR_DELETED',
    'vendor',
    $id,
    'Vendor deactivated: ' .
    $vendor['company_name']
);

// ========================================
// Response
// ========================================

success_response(
    'Vendor deactivated successfully',
    [
        'vendor_id' => $id
    ]
);