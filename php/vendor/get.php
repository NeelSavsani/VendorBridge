<?php

require_once '../config/config.php';
require_once '../config/helpers.php';
require_once '../config/auth.php';

// ========================================
// Authentication
// ========================================

$auth = require_auth();

// ========================================
// Vendor ID
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

$stmt = $db->prepare("
    SELECT
        id,
        company_name,
        contact_person,
        email,
        phone,
        gst_number,
        category,
        address,
        country,
        status,
        rating,
        created_at
    FROM vendors
    WHERE id = ?
");

$stmt->execute([$id]);

$vendor = $stmt->fetch();

// ========================================
// Not Found
// ========================================

if (!$vendor) {

    error_response(
        'Vendor not found',
        404
    );
}

// ========================================
// Success
// ========================================

success_response(
    'Vendor retrieved successfully',
    $vendor
);