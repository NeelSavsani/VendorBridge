<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';

// ========================================
// Authentication Required
// ========================================

$authUser = require_auth();

// ========================================
// Get User Details
// ========================================

$db = getDB();

$stmt = $db->prepare("
    SELECT
        id,
        name,
        email,
        role,
        company,
        phone,
        country,
        created_at
    FROM users
    WHERE id = ?
    AND is_active = 1
");

$stmt->execute([
    $authUser['id']
]);

$user = $stmt->fetch();

// ========================================
// User Not Found
// ========================================

if (!$user) {

    error_response(
        'User not found',
        404
    );
}

// ========================================
// Success Response
// ========================================

success_response(
    'User profile retrieved',
    $user
);
